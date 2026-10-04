<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Batchable;
use Illuminate\Foundation\Bus\Dispatchable;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Task;
use App\Services\DropboxService;
use GuzzleHttp\Client as HttpClient;

class UploadToDropboxJob implements ShouldQueue
{
   use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;


    /**
     * 🔁 Pogingen bij mislukte uploads
     */
    public $tries = 3;

    /**
     * ⏱️ Max uitvoeringstijd per job (in seconden)
     */
    public $timeout = 120;

    /**
     * ⏳ Wachttijd tussen retries (exponential backoff)
     */
    public function backoff(): array
    {
        return [10, 30, 60]; // 10s → 30s → 60s
    }

    public function __construct(
        public int $taskId,
        public string $path,
        public string $adresPath,
        public ?string $namespaceId = null
    ) {}

    public function handle(): void
    {
        $dropbox = app(DropboxService::class);
        $task = Task::with('team')->find($this->taskId);

        if (!$task) {
            Log::warning("❌ Task niet gevonden: {$this->taskId}");
            return;
        }

        if (!Storage::exists($this->path)) {
            Log::warning("⚠️ Bestand niet gevonden: {$this->path}");
            return;
        }

        // ------------------------------------------------------
        // 🔹 Ophalen van namespace_id's
        // ------------------------------------------------------
        $fluviusNamespaceId = $dropbox->getFluviusNamespaceId(); // Perceel 2
        $namespaceId = $this->namespaceId;

        // 🔹 Als geen namespace_id is meegegeven → probeer te bepalen
        if (!$namespaceId) {
            $namespaceId = $this->getPerceel1Namespace($dropbox);
        }

        // 🔹 Bepaal perceel pas nádat namespaceId bekend is
        $isPerceel1 = $namespaceId && $namespaceId !== $fluviusNamespaceId;

        if (!$namespaceId) {
            Log::error("❌ Geen namespace gevonden voor task {$this->taskId}");
            return;
        }

        // ------------------------------------------------------
        // 🔹 Uploadpad voorbereiden
        // ------------------------------------------------------
        $stream = Storage::readStream($this->path);
        if (!$stream) {
            Log::error("⚠️ Kon geen stream openen voor {$this->path}");
            return;
        }

        $filename = basename($this->path);

        // Normaliseer adresPad
        $adresPath = preg_replace('#^/PERCEEL\s*[12]/#i', '', $this->adresPath);
        $adresPath = preg_replace('#^/+|/+$#', '', $adresPath);

        // 🔧 Alleen Perceel 2 (Fluvius) krijgt expliciet "/PERCEEL 2"
        if ($isPerceel1) {
            $uploadPath = "/{$adresPath}/{$filename}";
        } else {
            $uploadPath = "/PERCEEL 2/{$adresPath}/{$filename}";
        }

        Log::info("📂 Upload path resolved → {$uploadPath}");
        Log::info("🧭 Namespace gebruikt: {$namespaceId} | Perceel: " . ($isPerceel1 ? '1 (Aansluitingen)' : '2 (Graafwerk)'));

        // ------------------------------------------------------
        // 🔹 Upload uitvoeren naar Dropbox
        // ------------------------------------------------------
        try {
            $accessToken = $dropbox->getAccessToken();
            $http = new HttpClient();

            $headers = [
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type'  => 'application/octet-stream',
                'Dropbox-API-Arg' => json_encode([
                    'path' => $uploadPath,
                    'mode' => 'add',
                    'autorename' => true,
                    'mute' => false,
                ]),
                'Dropbox-API-Path-Root' => json_encode([
                    '.tag' => 'namespace_id',
                    'namespace_id' => $namespaceId,
                ]),
                'Dropbox-API-Select-User' => config('services.dropbox.team_member_id'),
            ];

            $response = $http->post('https://content.dropboxapi.com/2/files/upload', [
                'headers' => $headers,
                'body' => $stream,
                'timeout' => 60, // korte HTTP-timeout per upload
            ]);

            if (is_resource($stream)) {
                fclose($stream);
            }
            Storage::delete($this->path);

            if ($response->getStatusCode() === 200) {
                Log::info("✅ Upload gelukt voor task {$task->id} → {$uploadPath} [namespace: {$namespaceId}]");

                // ✅ Corrigeer pad voor opslag in database
                if ($isPerceel1 && !str_starts_with($uploadPath, '/PERCEEL 1')) {
                    $dbPath = '/PERCEEL 1' . $uploadPath;
                } else {
                    $dbPath = $uploadPath;
                }

                $existing = $task->photo ? explode(',', $task->photo) : [];
                $existing[] = $dbPath;
                $task->photo = implode(',', $existing);
                $task->save();

                Log::info("💾 DB-pad opgeslagen → {$dbPath}");
            } else {
                Log::error("⚠️ Dropbox upload status: {$response->getStatusCode()} voor {$uploadPath}");
            }

        } catch (\Throwable $e) {
            Log::error("❌ Dropbox upload mislukt ({$this->path}): " . $e->getMessage());
            throw $e; // 👉 gooit opnieuw zodat retry getriggerd wordt
        }
    }

    // ------------------------------------------------------
    // 🔹 Helper: namespace ophalen van Perceel 1
    // ------------------------------------------------------
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
