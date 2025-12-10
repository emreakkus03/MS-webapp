<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\R2PendingUpload;
use App\Jobs\MoveToDropboxJob;

class RetryAllR2Pending extends Command
{
    // De naam van het commando
    protected $signature = 'r2:retry-all';
    
    protected $description = 'Veilige herstart van alle vastgelopen uploads (pending & failed) ouder dan 5 minuten.';

    public function handle()
    {
        $this->info("🔍 Analyseren van vastgelopen uploads in database...");

        // 1. Zoek naar items die:
        // - Status 'pending' OF 'failed' hebben
        // - EN ouder zijn dan 5 minuten (zodat we geen verse uploads storen)
        $rows = R2PendingUpload::whereIn('status', ['pending', 'failed'])
            ->where('created_at', '<', now()->subMinutes(5))
            ->get();

        if ($rows->isEmpty()) {
            $this->info("✅ Geen vastgelopen uploads gevonden. De wachtrij is schoon! 😊");
            return 0;
        }

        $this->info("📦 {$rows->count()} bestanden gevonden die hulp nodig hebben.");

        foreach ($rows as $row) {
            
            // 2. SAFETY CHECK: Bestaat het bestand wel in R2?
            // Als de file in R2 weg is, heeft uploaden naar Dropbox geen zin.
            if (!Storage::disk('r2')->exists($row->r2_path)) {
                $this->error("❌ Bestand mist in R2 bucket (overgeslagen): {$row->r2_path}");
                
                // Optioneel: Zet op 'lost' zodat we het niet blijven proberen in de toekomst
                // $row->update(['status' => 'lost']); 
                continue;
            }

            // 3. Reset status naar 'pending' in database
            // Zodat we weten dat hij nu weer actief verwerkt wordt.
            $row->update(['status' => 'pending']);

            $this->info("🚀 Gered en opnieuw gequeued: Task {$row->task_id}");

            // 4. Dispatch de Job met de EXACTE data uit de DB
            dispatch(new MoveToDropboxJob(
                [$row->r2_path],       // Bestand in R2
                $row->adres_path,      // Doelmap in Dropbox
                $row->namespace_id,    // Namespace ID
                $row->task_id          // Taak ID
            ))->onQueue('uploads');
        }

        $this->info("\n🎉 Klaar! Alle {$rows->count()} bestanden zijn opnieuw onderweg naar Dropbox.");
        return 0;
    }
}