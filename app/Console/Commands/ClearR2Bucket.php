<?php

namespace App\Console\Commands;

use App\Models\R2PendingUpload;
use App\Services\PhotoDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearR2Bucket extends Command
{
    protected $signature = 'r2:clear {--force : Skip confirmation (never skip delivery verification)}';

    protected $description = 'Clean only R2 photos with a durable Dropbox receipt; retain all recovery metadata.';

    public function handle(PhotoDelivery $delivery): int
    {
        if (! $this->option('force') && ! $this->confirm('Clean only confirmed Dropbox deliveries?', false)) {
            return self::FAILURE;
        }
        $failed = false;
        R2PendingUpload::whereNotNull('completed_at')->whereNull('cleaned_at')->chunkById(100, function ($rows) use ($delivery, &$failed) {
            foreach ($rows as $row) {
                $lock = Cache::lock(PhotoDelivery::lockKey($row->r2_path), 600);
                if (! $lock->get()) {
                    continue;
                }
                try {
                    $delivery->cleanup($row);
                } catch (\Throwable $e) {
                    $failed = true;
                    $this->warn('Retained upload '.$row->id.'; cleanup could not be confirmed.');
                } finally {
                    $lock->release();
                }
            }
        });
        $this->info('Unconfirmed objects and database receipts have been retained.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
