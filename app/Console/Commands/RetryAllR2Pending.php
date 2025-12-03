<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\R2PendingUpload;
use App\Jobs\MoveToDropboxJob;

class RetryAllR2Pending extends Command
{
    protected $signature = 'r2:retry-all';
    protected $description = 'Retry alle pending R2 uploads';

    public function handle()
    {
        $rows = R2PendingUpload::all();

        if ($rows->isEmpty()) {
            $this->info("Geen pending uploads 😊");
            return;
        }

        foreach ($rows as $row) {
            dispatch(new MoveToDropboxJob(
                [$row->r2_path],
                $row->adres_path,
                $row->namespace_id,
                $row->task_id
            ))->onQueue('uploads');

            $this->info("🔁 opnieuw gequeued: {$row->r2_path}");
        }

        $this->info("🎉 Alles opnieuw gestart!");
    }
}
