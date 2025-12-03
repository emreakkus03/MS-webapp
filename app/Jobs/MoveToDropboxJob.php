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
    public $timeout = 300; // iets langer, veilig bij grote batches

    protected array $photos;
    protected string $adresPath;
    protected string $namespaceId;
    protected ?int $taskId;

    public function __construct(array $photos, string $adresPath, string $namespaceId, ?int $taskId = null)
    {
        $this->photos = $photos;
        $this->adresPath = $adresPath;
        $this->namespaceId = $namespaceId;
        $this->taskId = $taskId;
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

        $isPerceel1 = $this->namespaceId !== $fluviusNamespaceId;
        $usedNamespace = $isPerceel1
            ? $this->namespaceId
            : $fluviusNamespaceId;

        Log::info('🧭 Namespace gebruikt voor upload', [
            'frontend_namespace' => $this->namespaceId,
            'used_namespace'     => $usedNamespace,
            'fluvius_namespace'  => $fluviusNamespaceId,
            'is_perceel1'        => $isPerceel1,
        ]);

        $adresPath = $this->normalizeAdresPath($this->adresPath, $isPerceel1);
        Log::info('📂 Normalized adresPath', [
            'raw'        => $this->adresPath,
            'normalized' => $adresPath
        ]);

        // 🔹 Verwerk in batches van max. 10 bestanden tegelijk
        $chunks = array_chunk($this->photos, 10);
        foreach ($chunks as $batchIndex => $batchPhotos) {
            Log::info("🚀 Start batch " . ($batchIndex + 1) . "/" . count($chunks));

            foreach ($batchPhotos as $photo) {
                try {
                    $r2Stream = Storage::disk('r2')->readStream($photo);
                    if (!$r2Stream) {
                        Log::warning("⚠️ Kan R2-bestand niet lezen: {$photo}");
                        continue;
                    }

                    $filename = basename($photo);
                    $uploadPath = "{$adresPath}/{$filename}";

                    $client = new HttpClient();
                    $accessToken = $dropbox->getAccessToken();

                    $response = $client->post('https://content.dropboxapi.com/2/files/upload', [
                        'headers' => [
                            'Authorization' => "Bearer {$accessToken}",
                            'Content-Type' => 'application/octet-stream',
                            'Dropbox-API-Arg' => json_encode([
                                'path' => $uploadPath,
                                'mode' => 'add',
                                'autorename' => true,
                                'mute' => false,
                            ]),
                            'Dropbox-API-Path-Root' => json_encode([
                                '.tag' => 'namespace_id',
                                'namespace_id' => $usedNamespace,
                            ]),
                            'Dropbox-API-Select-User' => config('services.dropbox.team_member_id'),
                        ],
                        'body' => $r2Stream,
                        'timeout' => 90,
                    ]);

                    if (is_resource($r2Stream)) {
                        fclose($r2Stream);
                    }

                    if ($response->getStatusCode() === 200) {
                        Log::info("✅ Bestand geüpload naar Dropbox", [
                            'path' => $uploadPath,
                            'namespace' => $usedNamespace,
                        ]);
                        Storage::disk('r2')->delete($photo);
                         \App\Models\R2PendingUpload::where('r2_path', $photo)->delete();

                        // 📋 DB-pad aanpassen
                        $dbPath = preg_replace('#^/MS INFRA/Fluvius Aansluitingen#i', '', $uploadPath);
                        if ($isPerceel1 && !preg_match('#^/PERCEEL\s*1/#i', $dbPath)) {
                            $dbPath = '/PERCEEL 1' . (str_starts_with($dbPath, '/') ? '' : '/') . $dbPath;
                        }

                        $existing = $task->photo ? explode(',', $task->photo) : [];
                        $existing[] = $dbPath;
                        $task->photo = implode(',', $existing);
                        $task->save();

                        Log::info("💾 Task {$task->id} bijgewerkt met DB-pad: {$dbPath}");
                    } else {
                        Log::error("⚠️ Dropbox upload mislukt: status {$response->getStatusCode()} voor {$uploadPath}");
                    }

                    // 🕐 Kleine pauze (0.4s) om Dropbox te ontlasten
                    usleep(400000);

                } catch (\Throwable $e) {
                    Log::error("❌ Fout bij verplaatsen naar Dropbox: {$photo} → " . $e->getMessage());
                     // 🔴 Mislukt: zet status failed
    \App\Models\R2PendingUpload::where('r2_path', $photo)
        ->update(['status' => 'failed']);
                }
            }

            // ⏸️ 2s rust na elke batch
            sleep(2);
            Log::info("✅ Batch " . ($batchIndex + 1) . " afgerond, kleine pauze voor stabiliteit");
        }

        Log::info("🎉 Alle batches succesvol geüpload voor task {$this->taskId}");
    }

    private function normalizeAdresPath(string $path, bool $isPerceel1): string
    {
        $path = trim($path, '/');
        $path = preg_replace('#^(MS INFRA/Fluvius Aansluitingen/)+#i', '', $path);

        if (preg_match('#^PERCEEL\s*[12]/#i', $path)) {
            if ($isPerceel1) {
                $path = preg_replace('#^PERCEEL\s*1/#i', '', $path);
            }
            return '/' . ltrim($path, '/');
        }

        if (preg_match('#^Webapp uploads#i', $path)) {
            if ($isPerceel1) {
                return '/' . $path;
            } else {
                return "/PERCEEL 2/{$path}";
            }
        }

        if ($isPerceel1) {
            return "/Webapp uploads/{$path}";
        } else {
            return "/PERCEEL 2/Webapp uploads/{$path}";
        }
    }

    private function getPerceel1Namespace(DropboxService $dropbox): ?string
    {
        try {
            $namespaces = $dropbox->listNamespaces();
            $match = collect($namespaces)->first(fn($ns) =>
                stripos($ns['name'], 'perceel 1') !== false ||
                stripos($ns['name'], 'aansluitingen') !== false
            );
            return $match['namespace_id'] ?? null;
        } catch (\Throwable $e) {
            Log::error("⚠️ Kon Perceel 1 namespace niet ophalen: " . $e->getMessage());
            return null;
        }
    }
}
