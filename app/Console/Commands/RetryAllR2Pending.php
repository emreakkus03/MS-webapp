<?php

namespace App\Console\Commands;

use App\Models\R2PendingUpload;
use App\Services\PhotoDelivery;
use Illuminate\Console\Command;

class RetryAllR2Pending extends Command
{
    protected $signature = 'r2:retry-all';

    protected $description = 'Retry stale photo deliveries and unfinished cleanup without deleting recovery records.';

    public function handle(PhotoDelivery $delivery): int
    {
        R2PendingUpload::whereNull('cleaned_at')->where('updated_at', '<', now()->subMinutes(15))
            ->chunkById(100, function ($rows) use ($delivery) {
                foreach ($rows as $row) {
                    // Includes receiving, processing, failed, and done awaiting cleanup.
                    // Worker takes the same per-object lock as ingress and manual cleanup.
                    $delivery->dispatch($row);
                }
            });
        $this->info('Stale deliveries and cleanup queued. Missing sources remain visible for recovery.');

        return self::SUCCESS;
    }
}
