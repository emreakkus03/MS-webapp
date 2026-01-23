<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\Task;
use App\Services\DropboxService;
use GuzzleHttp\Client as HttpClient;

class MoveToDropboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 600;

    protected array $photos;
    protected string $adresPath;
    protected ?string $namespaceId; 
    protected ?int $taskId;
    protected string $rootPath;

    public function __construct(array $photos, string $adresPath, ?string $namespaceId, ?int $taskId = null, ?string $rootPath = "/Webapp uploads")
    {
        $this->photos = $photos;
        $this->adresPath = $adresPath;
        $this->namespaceId = $namespaceId;
        $this->taskId = $taskId;
        $this->rootPath = $rootPath ?? "/Webapp uploads";
    }

    public function handle(): void
    {
        $dropbox = app(DropboxService::class);
        $task = Task::find($this->taskId);
        
        // Dit is de ID van de hoofdschijf (waar Perceel 2 als map op staat)
        $fluviusNamespaceId = $dropbox->getFluviusNamespaceId();

        if (!$task) {
            return;
        }

        // ============================================================
        // 1. HARDE LOGICA: P1 (Namespace) vs P2 (Map)
        // ============================================================
        
        // Is het Perceel 1? (Alleen als ID gevuld is EN het niet de standaard Fluvius ID is)
        $isPerceel1 = !empty($this->namespaceId) && $this->namespaceId !== $fluviusNamespaceId;

        if ($isPerceel1) {
            // >>> PERCEEL 1 (Namespace Mode) <<<
            $targetNamespace = $this->namespaceId;
            
            // Omdat we via de namespace al IN de map Perceel 1 zitten,
            // hoeven we hier alleen de upload map op te geven.
            $cleanRoot = "/Webapp uploads";
            
        } else {
            // >>> PERCEEL 2 (Folder Mode) <<<
            $targetNamespace = $fluviusNamespaceId;
            
            // 🛑 DE SIMPELE FIX VOOR JOU:
            // We checken gewoon of 'Perceel 2' in het pad staat.
            // Staat het er niet? Dan zetten we het er hard voor.
            
            if (stripos($this->rootPath, 'Perceel 2') === false) {
                // Hij mist de map, dus we zetten hem er hard in.
                $cleanRoot = "/Perceel 2/Webapp uploads"; 
            } else {
                // Het stond er al in (via JS), dus we gebruiken wat we kregen.
                // We zorgen alleen dat /Webapp uploads erachter staat.
                if (stripos($this->rootPath, 'Webapp uploads') === false) {
                    $cleanRoot = rtrim($this->rootPath, '/') . "/Webapp uploads";
                } else {
                    $cleanRoot = $this->rootPath;
                }
            }
        }

        // Bouw het uiteindelijke pad: [Root] / [AdresMap]
        // rtrim/ltrim zorgt dat we geen dubbele slashes // krijgen
        $finalUploadPath = rtrim($cleanRoot, '/') . '/' . ltrim($this->adresPath, '/');

        Log::info('🚀 Start Dropbox Job', [
            'mode' => $isPerceel1 ? 'P1 (NS)' : 'P2 (Map)',
            'forced_path' => $finalUploadPath
        ]);

        // ============================================================
        // 2. UPLOADEN
        // ============================================================
        $chunks = array_chunk($this->photos, 5);
        
        foreach ($chunks as $batchIndex => $batchPhotos) {
            foreach ($batchPhotos as $photo) {
                try {
                    if (!Storage::disk('r2')->exists($photo)) continue;

                    $r2Stream = Storage::disk('r2')->readStream($photo);
                    $filename = basename($photo);
                    
                    // Het volledige pad op Dropbox
                    $fileDestPath = "{$finalUploadPath}/{$filename}";

                    $client = new HttpClient();
                    $accessToken = $dropbox->getAccessToken();

                    $headers = [
                        'Authorization' => "Bearer {$accessToken}",
                        'Content-Type' => 'application/octet-stream',
                        'Dropbox-API-Arg' => json_encode([
                            'path' => $fileDestPath,
                            'mode' => 'add',
                            'autorename' => true,
                            'mute' => false,
                        ]),
                        'Dropbox-API-Select-User' => config('services.dropbox.team_member_id'), 
                    ];

                    // Header alleen toevoegen als het P1 is (of P2 in specifieke gevallen, maar hier via targetNamespace)
                    if ($targetNamespace) {
                        $headers['Dropbox-API-Path-Root'] = json_encode([
                            '.tag' => 'namespace_id',
                            'namespace_id' => $targetNamespace,
                        ]);
                    }

                    $response = $client->post('https://content.dropboxapi.com/2/files/upload', [
                        'headers' => $headers,
                        'body' => $r2Stream,
                        'timeout' => 120,
                    ]);

                    if (is_resource($r2Stream)) fclose($r2Stream);

                    if ($response->getStatusCode() === 200) {
                        Storage::disk('r2')->delete($photo);
                        \App\Models\R2PendingUpload::where('r2_path', $photo)->delete();

                        // DB Pad update (Visueel voor jouw CMS)
                        if ($isPerceel1) {
                            // Voor P1 plakken we het visueel ervoor
                            $dbPath = "/PERCEEL 1" . $fileDestPath;
                        } else {
                            // Voor P2 is het pad al volledig
                            $dbPath = $fileDestPath;
                        }
                        $this->updateTaskPhoto($task, $dbPath);
                    } 
                    usleep(500000); 

                } catch (\Throwable $e) {
                    Log::error("Fout: " . $e->getMessage());
                    \App\Models\R2PendingUpload::where('r2_path', $photo)->update(['status' => 'failed']);
                }
            }
            sleep(1); 
        }
    }

    private function updateTaskPhoto(Task $task, string $newPath)
    {
        $existing = $task->photo ? explode(',', $task->photo) : [];
        $existing[] = $newPath;
        $task->photo = implode(',', array_unique($existing));
        $task->save();
    }
}