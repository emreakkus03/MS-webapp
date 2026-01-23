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
        $isPerceel1 = str_starts_with($this->rootPath, '/Webapp uploads');
        
        $targetNamespace = $isPerceel1 ? $this->namespaceId : $fluviusNamespaceId;
        $finalUploadPath = rtrim($this->rootPath, '/') . '/' . ltrim($this->adresPath, '/');

        Log::info('🚀 Start Dropbox Job', [
            'task_id' => $this->taskId,
            'target_namespace' => $targetNamespace,
            'upload_path' => $finalUploadPath,
            'is_perceel1' => $isPerceel1
        ]);

        // 2. Upload in batches
        $chunks = array_chunk($this->photos, 5);
        
        foreach ($chunks as $batchIndex => $batchPhotos) {
            foreach ($batchPhotos as $photo) {
                try {
                    if (!Storage::disk('r2')->exists($photo)) {
                        Log::warning("⚠️ Bestand weg op R2: {$photo}");
                        continue;
                    }

                    $r2Stream = Storage::disk('r2')->readStream($photo);
                    $filename = basename($photo);
                    
                    // Dit is het TECHNISCHE pad voor de Dropbox API
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

                        // 👇 🔥 FIX: VISUEEL PAD VOOR DE DATABASE
                        // We maken een apart pad aan voor de database opslag
                        if ($isPerceel1) {
                            // Plak "/PERCEEL 1" ervoor voor de database
                            $dbPath = "/PERCEEL 1" . $fileDestPath;
                        } else {
                            // Perceel 2 is al goed
                            $dbPath = $fileDestPath;
                        }

                        // Opslaan in database met het VISUELE pad
                        $this->updateTaskPhoto($task, $dbPath);

                    } else {
                        Log::error("⚠️ Upload mislukt: {$response->getStatusCode()}");
                    }

                    usleep(500000); 

                } catch (\Throwable $e) {
                    Log::error("❌ Fout bij {$photo}: " . $e->getMessage());
                    \App\Models\R2PendingUpload::where('r2_path', $photo)->update(['status' => 'failed']);
                }
            }
            sleep(1); 
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