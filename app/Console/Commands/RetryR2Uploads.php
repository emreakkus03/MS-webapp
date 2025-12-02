<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Jobs\MoveToDropboxJob;

class RetryR2Uploads extends Command
{
    protected $signature = 'r2:retry-missing 
                            {--task= : Forceer upload onder een bepaald taskId}';

    protected $description = 'Scan R2 bucket en herstart alle overgebleven foto-upload jobs';

    public function handle()
    {
        $this->info("🔍 Scannen van R2...");

        $files = Storage::disk('r2')->allFiles();

        if (empty($files)) {
            $this->warn("❌ Geen bestanden gevonden in R2 — bucket is leeg.");
            return 0;
        }

        $this->info("📦 Gevonden: " . count($files) . " bestanden in R2");

        $taskId = $this->option('task') ?: null;

        foreach ($files as $file) {
            $this->info("➡️ Nieuwe upload job: {$file}");

            dispatch(new MoveToDropboxJob(
                [$file],                  // array → MoveToDropboxJob verwacht array
                "UNKNOWN-PATH",           // we pushen later die path zelf in Dropbox
                config('services.dropbox.team_member_id'), 
                $taskId
            ))->onQueue('uploads');
        }

        $this->info("🎉 Klaar! Alle foto’s zijn opnieuw in de wachtrij gezet.");
        return 0;
    }
}
