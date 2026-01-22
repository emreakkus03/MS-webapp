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
    public $timeout = 600; // Ruime tijd voor grote batches

    protected array $photos;
    protected string $adresPath;
    protected string $namespaceId;
    protected ?int $taskId;
    protected string $rootPath; // 👈 Nieuw: De basismap

    public function __construct(array $photos, string $adresPath, string $namespaceId, ?int $taskId = null, string $rootPath = "/Webapp uploads")
    {
        $this->photos = $photos;
        $this->adresPath = $adresPath;
        $this->namespaceId = $namespaceId;
        $this->taskId = $taskId;
        $this->rootPath = $rootPath; // Standaard P1, maar wordt overschreven door controller
    }

    public function handle(): void
    {
        $dropbox = app(DropboxService::class);
        $task = Task::find($this->taskId);
        $fluviusNamespaceId = $dropbox->getFluviusNamespaceId();

        if (!$task) {
            Log::warning("❌ Task niet gevonden: {$this->taskId}");
            return;
        }

        // 1. Bepaal de juiste Namespace & Pad
        // Als de rootPath begint met /Webapp uploads -> Perceel 1 (Namespace modus)
        // Anders (bijv /Fluvius/Perceel 2...) -> Perceel 2 (Folder modus, default NS)
        
        $isPerceel1 = str_starts_with($this->rootPath, '/Webapp uploads');
        
        // Als het Perceel 1 is, gebruiken we de meegeleverde namespace.
        // Als het Perceel 2 is, forceren we de Fluvius namespace (want P2 is een gewone map daarin).
        $targetNamespace = $isPerceel1 ? $this->namespaceId : $fluviusNamespaceId;

        // Bouw het volledige pad: Root + Submap (Straatnaam)
        // rtrim/ltrim zorgt dat we geen dubbele slashes // krijgen
        $finalUploadPath = rtrim($this->rootPath, '/') . '/' . ltrim($this->adresPath, '/');

        Log::info('🚀 Start Dropbox Job', [
            'task_id' => $this->taskId,
            'target_namespace' => $targetNamespace,
            'upload_path' => $finalUploadPath,
            'is_perceel1' => $isPerceel1
        ]);

        // 2. Upload in batches
        $chunks = array_chunk($this->photos, 5); // Kleinere batches voor stabiliteit
        
        foreach ($chunks as $batchIndex => $batchPhotos) {
            foreach ($batchPhotos as $photo) {
                try {
                    // Check of file bestaat op R2
                    if (!Storage::disk('r2')->exists($photo)) {
                        Log::warning("⚠️ Bestand weg op R2: {$photo}");
                        continue;
                    }

                    $r2Stream = Storage::disk('r2')->readStream($photo);
                    $filename = basename($photo);
                    
                    // Volledige pad voor dit bestand
                    $fileDestPath = "{$finalUploadPath}/{$filename}";

                    $client = new HttpClient();
                    $accessToken = $dropbox->getAccessToken();

                    $response = $client->post('https://content.dropboxapi.com/2/files/upload', [
                        'headers' => [
                            'Authorization' => "Bearer {$accessToken}",
                            'Content-Type' => 'application/octet-stream',
                            'Dropbox-API-Arg' => json_encode([
                                'path' => $fileDestPath,
                                'mode' => 'add',
                                'autorename' => true,
                                'mute' => false,
                            ]),
                            'Dropbox-API-Path-Root' => json_encode([
                                '.tag' => 'namespace_id',
                                'namespace_id' => $targetNamespace,
                            ]),
                            // User Select header alleen als je een Team Member ID hebt
                            'Dropbox-API-Select-User' => config('services.dropbox.team_member_id'), 
                        ],
                        'body' => $r2Stream,
                        'timeout' => 120,
                    ]);

                    if (is_resource($r2Stream)) fclose($r2Stream);

                    if ($response->getStatusCode() === 200) {
                        Log::info("✅ Upload OK: {$filename}");
                        
                        // Opruimen R2
                        Storage::disk('r2')->delete($photo);
                        \App\Models\R2PendingUpload::where('r2_path', $photo)->delete();

                        // Opslaan in database (pad updaten)
                        $this->updateTaskPhoto($task, $fileDestPath);

                    } else {
                        Log::error("⚠️ Upload mislukt: {$response->getStatusCode()}");
                    }

                    // Korte pauze voor API rate limits
                    usleep(500000); 

                } catch (\Throwable $e) {
                    Log::error("❌ Fout bij {$photo}: " . $e->getMessage());
                    \App\Models\R2PendingUpload::where('r2_path', $photo)->update(['status' => 'failed']);
                }
            }
            sleep(1); // Rust tussen batches
        }
    }

    private function updateTaskPhoto(Task $task, string $newPath)
    {
        // Haal bestaande foto's op
        $existing = $task->photo ? explode(',', $task->photo) : [];
        
        // Voeg nieuwe toe
        $existing[] = $newPath;
        
        // Opslaan (unieke waarden)
        $task->photo = implode(',', array_unique($existing));
        $task->save();
    }
}