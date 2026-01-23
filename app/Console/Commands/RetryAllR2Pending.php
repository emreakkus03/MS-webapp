<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\R2PendingUpload;
use App\Jobs\MoveToDropboxJob;

class RetryAllR2Pending extends Command
{
    protected $signature = 'r2:retry-all';
    protected $description = 'Veilige herstart van alle vastgelopen uploads (pending & failed) ouder dan 5 minuten.';

    public function handle()
    {
        $this->info("🔍 Analyseren van vastgelopen uploads in database...");

        // 1. Zoek items (failed of pending, ouder dan 5 min)
        $rows = R2PendingUpload::whereIn('status', ['pending', 'failed'])
            ->where('created_at', '<', now()->subMinutes(5))
            ->get();

        if ($rows->isEmpty()) {
            $this->info("✅ Geen vastgelopen uploads gevonden.");
            return 0;
        }

        $this->info("📦 {$rows->count()} bestanden gevonden.");

        foreach ($rows as $row) {
            
            // 2. Check of bestand fysiek bestaat in R2
            if (!Storage::disk('r2')->exists($row->r2_path)) {
                $this->error("❌ Bestand mist in R2 bucket: {$row->r2_path}");
                // $row->update(['status' => 'lost']); 
                continue;
            }

            // 3. Reset status
            $row->update(['status' => 'pending']);

            $this->info("🚀 Opnieuw gequeued: Task {$row->task_id}");

            // 4. Dispatch Job MET de nieuwe 5e parameter (root_path)
            dispatch(new MoveToDropboxJob(
                [$row->r2_path],       // 1. Array van paths
                $row->adres_path,      // 2. Doelmap
                $row->namespace_id,    // 3. Namespace ID (of null)
                $row->task_id,         // 4. Task ID
                $row->root_path        // 5. 👈 DE FIX: De root path (voor Perceel onderscheid)
            ))->onQueue('uploads');
        }

        $this->info("\n🎉 Klaar! Alle bestanden zijn opnieuw onderweg.");
        return 0;
    }
}