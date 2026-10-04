<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\R2PendingUpload; // <--- Toevoegen

class ClearR2Bucket extends Command
{
   protected $signature = 'r2:clear {--force : Forceer verwijdering zonder vraag}';
    protected $description = 'Verwijdert ALLE bestanden uit R2 én schoont de database op.';

    public function handle()
    {
        $this->warn("⚠️  LET OP: Je gaat de volledige R2 bucket leegmaken!");
        $this->warn("⚠️  Dit verwijdert ook alle 'pending' records uit de database.");
        
        if (! $this->option('force')) {
            if (! $this->confirm('Weet je zeker dat je ALLES wil verwijderen?', true)) {
                $this->info("❌ Actie geannuleerd.");
                return 1;
            }
        }

        // 1. Database leegmaken (Truncate)
        // Dit zorgt dat je command 'retry-all' niet meer probeert te uploaden
        $this->info("🗄️  Database tabel opschonen...");
        R2PendingUpload::truncate(); 

        // 2. R2 Bestanden verwijderen
        $files = Storage::disk('r2')->allFiles();

        if (empty($files)) {
            $this->info("📭 R2 is al leeg!");
            return 0;
        }

        $this->info("🗑️  Verwijderen van " . count($files) . " bestanden uit R2...");

        foreach ($files as $file) {
            Storage::disk('r2')->delete($file);
        }

        $this->info("🎉 Alles is schoon! Bucket leeg & Database leeg.");
        return 0;
    }
}