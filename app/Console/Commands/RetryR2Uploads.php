<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Task;
use App\Services\DropboxService;
use App\Jobs\MoveToDropboxJob;
use Illuminate\Support\Facades\Log;

class RetryR2Uploads extends Command
{
    protected $signature = 'r2:retry-missing';
    protected $description = 'Herstart alle achtergebleven foto’s in R2 met correcte Dropbox-pad en namespace.';

    public function handle()
    {
        $this->info("🔍 Scannen van R2...");

        $files = Storage::disk('r2')->allFiles();

        if (!$files) {
            $this->warn("📭 Geen bestanden in R2. Klaar.");
            return 0;
        }

        $this->info("📦 Totaal gevonden: " . count($files));

        $dropbox = app(DropboxService::class);
        $fluviusNamespaceId = $dropbox->getFluviusNamespaceId();

        foreach ($files as $file) {

            /**
             * 1️⃣ TaskId halen uit padstructuur:
             *    frontend uploadt zoals:
             *    r2/12345/filename.jpg
             */
            if (!preg_match('/(\d+)/', $file, $m)) {
                $this->error("❌ Geen taskId gevonden in bestandsnaam: {$file}");
                continue;
            }

            $taskId = $m[1];
            $task = Task::find($taskId);

            if (!$task) {
                $this->error("❌ Task {$taskId} bestaat niet voor bestand: {$file}");
                continue;
            }

            /**
             * 2️⃣ AdresPath bepalen — exact zoals TaskController → uploadPhoto()
             */
            if (!$task->address || !$task->address->folder) {
                $this->error("❌ Geen adresmap gevonden voor task {$taskId}. Bestand: {$file}");
                continue;
            }

            $adresPath = trim($task->address->folder, '/');

            /**
             * 3️⃣ Namespace bepalen — exacte frontend logica:
             *    -> Perceel 1 = NIET gelijk aan fluviusNamespaceId
             *    -> Perceel 2 = fluviusNamespaceId
             */
            $isPerceel1 = str_contains(strtolower($adresPath), 'perceel 1');

            $namespaceId = $isPerceel1
                ? $this->guessPerceel1Namespace($dropbox)
                : $fluviusNamespaceId;

            if (!$namespaceId) {
                $this->error("❌ Geen namespace gevonden voor perceel. File: {$file}");
                continue;
            }

            $this->info("\n➡️ Herstart upload:");
            $this->line("🗂 Task:       {$taskId}");
            $this->line("📁 AdresPath:  {$adresPath}");
            $this->line("🗃 Namespace:  {$namespaceId}");
            $this->line("🖼 Bestand:     {$file}");

            dispatch(new MoveToDropboxJob(
                [$file],
                $adresPath,
                $namespaceId,
                $taskId
            ))->onQueue('uploads');
        }

        $this->info("\n🎉 Alle resterende R2 bestanden opnieuw gequeued!");
        return 0;
    }

    private function guessPerceel1Namespace(DropboxService $dropbox)
    {
        $namespaces = $dropbox->listNamespaces();

        $match = collect($namespaces)
            ->first(fn($ns) => stripos($ns['name'], 'perceel 1') !== false);

        return $match['namespace_id'] ?? null;
    }
}
