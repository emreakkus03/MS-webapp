<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ClearR2Bucket extends Command
{
 protected $signature = 'r2:clear';
    protected $description = 'Verwijdert ALLE bestanden uit de Cloudflare R2 bucket.';

    public function handle()
    {
        $this->warn("⚠️ LET OP: Je gaat de volledige R2 bucket leegmaken!");
        
        // Indien je een extra confirm wil:
        if (!$this->confirm('Weet je zeker dat je ALLES wil verwijderen?', true)) {
            $this->info("❌ Actie geannuleerd.");
            return 1;
        }

        $files = Storage::disk('r2')->allFiles();

        if (empty($files)) {
            $this->info("📭 R2 is al leeg!");
            return 0;
        }

        $this->info("🗑️ Verwijderen van " . count($files) . " bestanden...");

        foreach ($files as $file) {
            Storage::disk('r2')->delete($file);
        }

        $this->info("🎉 R2 bucket is volledig opgeschoond!");
        return 0;
    }
}
