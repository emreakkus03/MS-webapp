<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Task;
use App\Jobs\MoveToDropboxJob;
use Illuminate\Support\Facades\Log;

class RetryR2Uploads extends Command
{
    protected $signature = 'r2:retry-missing';

    protected $description = 'Scan R2 bucket en herstel alle vastgelopen foto’s met correcte task, adresPath en namespace.';

    public function handle()
    {
        $this->info("🔍 R2 scannen…");

        $files = Storage::disk('r2')->allFiles();

        if (!$files) {
            $this->warn("R2 is leeg — niets om te herstellen.");
            return 0;
        }

        $this->info("📦 Gevonden bestanden: " . count($files));

        foreach ($files as $file) {

            // ⛑️ TaskId ophalen uit bestandsnaam (bijv: uploads/12345/xxx.jpg)
            preg_match('/(\d+)/', $file, $matches);
            $taskId = $matches[1] ?? null;

            if (!$taskId || !($task = Task::find($taskId))) {
                $this->error("❌ Kan taskId niet vinden voor: {$file}");
                continue;
            }

            // 💡 TaskController doet hetzelfde:
            $adresPath = trim(optional($task->address)->folder ?? '', '/');
            if (!$adresPath) {
                $this->warn("⚠️ Geen adresPad voor task {$taskId}, foto: {$file}");
                continue;
            }

            // Perceel bepalen (exact dezelfde logic als TaskController)
            $dropbox = app(\App\Services\DropboxService::class);
            $fluviusNamespace = $dropbox->getFluviusNamespaceId();

            $namespaceId = $task->namespace_id ?? $fluviusNamespace;

            $this->info("➡️ Herstart upload:");
            $this->line("📌 Task: {$taskId}");
            $this->line("📁 AdresPath: {$adresPath}");
            $this->line("🗂 Namespace: {$namespaceId}");
            $this->line("🖼 File: {$file}");

            dispatch(new MoveToDropboxJob(
                [$file],
                $adresPath,
                $namespaceId,
                $taskId
            ))->onQueue('uploads');
        }

        $this->info("🎉 Alles opnieuw in queue gezet zoals originele upload!");
        return 0;
    }
}
