<?php

namespace App\Jobs;

use App\Models\Task;
use App\Services\DropboxPhotoUploader;
use App\Services\DropboxService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UploadToDropboxJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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

        if (! $task) {
            Log::warning("❌ Task niet gevonden: {$this->taskId}");
            throw new \RuntimeException('Legacy photo upload cannot proceed; local source retained.');
        }

        if (! Storage::disk('local')->exists($this->path)) {
            Log::warning("⚠️ Bestand niet gevonden: {$this->path}");
            throw new \RuntimeException('Legacy photo upload cannot proceed; local source retained.');
        }

        // ------------------------------------------------------
        // 🔹 Ophalen van namespace_id's
        // ------------------------------------------------------
        $fluviusNamespaceId = $dropbox->getFluviusNamespaceId(); // Perceel 2
        $namespaceId = $this->namespaceId;

        // 🔹 Als geen namespace_id is meegegeven → probeer te bepalen
        if (! $namespaceId) {
            $namespaceId = $this->getPerceel1Namespace($dropbox);
        }

        // 🔹 Bepaal perceel pas nádat namespaceId bekend is
        $isPerceel1 = $namespaceId && $namespaceId !== $fluviusNamespaceId;

        if (! $namespaceId) {
            Log::error("❌ Geen namespace gevonden voor task {$this->taskId}");
            throw new \RuntimeException('Legacy photo upload cannot proceed; local source retained.');
        }

        // ------------------------------------------------------
        // 🔹 Uploadpad voorbereiden
        // ------------------------------------------------------
        $stream = Storage::disk('local')->readStream($this->path);
        if (! $stream) {
            Log::error("⚠️ Kon geen stream openen voor {$this->path}");
            throw new \RuntimeException('Legacy photo upload cannot proceed; local source retained.');
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
        Log::info("🧭 Namespace gebruikt: {$namespaceId} | Perceel: ".($isPerceel1 ? '1 (Aansluitingen)' : '2 (Graafwerk)'));

        // ------------------------------------------------------
        // 🔹 Upload uitvoeren naar Dropbox
        // ------------------------------------------------------
        try {
            $metadata = app(DropboxPhotoUploader::class)->upload($dropbox, $namespaceId, $uploadPath, $stream,
                hash_file('sha256', Storage::disk('local')->path($this->path)), Storage::disk('local')->size($this->path));
            DB::transaction(function () use ($task, $metadata, $isPerceel1) {
                $task = Task::whereKey($task->id)->lockForUpdate()->firstOrFail();
                $dbPath = ($isPerceel1 ? '/PERCEEL 1' : '').$metadata['path_display'];
                $dbPath = str_replace(['%', ','], ['%25', '%2C'], $dbPath);
                $existing = $task->photo ? explode(',', $task->photo) : [];
                $existing[] = $dbPath;
                $task->photo = implode(',', array_unique($existing));
                $task->save();
            });
            // Positive Dropbox acknowledgement AND committed task reference precede deletion.
            if (! Storage::disk('local')->delete($this->path)) {
                throw new \RuntimeException('Legacy local cleanup failed.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    // ------------------------------------------------------
    // 🔹 Helper: namespace ophalen van Perceel 1
    // ------------------------------------------------------
    private function getPerceel1Namespace(DropboxService $dropbox): ?string
    {
        try {
            $namespaces = $dropbox->listNamespaces();
            $match = collect($namespaces)->first(fn ($ns) => stripos($ns['name'], 'perceel 1') !== false ||
                stripos($ns['name'], 'aansluitingen') !== false
            );

            return $match['namespace_id'] ?? null;
        } catch (\Throwable $e) {
            Log::error('⚠️ Kon Perceel 1 namespace niet ophalen: '.$e->getMessage());

            return null;
        }
    }
}
