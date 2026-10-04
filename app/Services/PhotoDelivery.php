<?php

namespace App\Services;

use App\Jobs\MoveToDropboxJob;
use App\Models\R2PendingUpload;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PhotoDelivery
{
    public static function lockKey(string $path): string
    {
        return 'photo-delivery:'.hash('sha256', $path);
    }

    public static function context(R2PendingUpload $row): array
    {
        return ['upload_id' => $row->upload_id ?? 'legacy-'.$row->id,
            'task_id' => $row->task_id, 'r2_key' => $row->r2_path, 'attempt' => $row->attempts];
    }

    public static function errorMessage(\Throwable $error): string
    {
        // Vendor HTTP exception strings may embed credentials or signed URLs.
        if ($error::class === RuntimeException::class || $error instanceof DropboxPhotoRetry) {
            return mb_substr($error->getMessage(), 0, 1000);
        }
        if ($error instanceof \Aws\Exception\AwsException) {
            return 'R2 request failed (HTTP '.($error->getStatusCode() ?? 'network').'). Source retained.';
        }
        return 'Remote/storage operation failed; source retained.';
    }

    public function dispatch(R2PendingUpload $row): void
    {
        try {
            // Explicit dispatch: exceptions must be caught here, not in PendingDispatch's destructor.
            Bus::dispatch((new MoveToDropboxJob([$row->r2_path], $row->adres_path, $row->namespace_id, $row->task_id))->onQueue('uploads'));
            Log::info('DROPBOX_JOB_DISPATCHED', self::context($row));
        } catch (\Throwable $e) {
            // The committed receipt is an outbox. The scheduler will retry dispatch.
            Log::error('DROPBOX_JOB_DISPATCH_FAILED', self::context($row) + ['exception' => $e::class, 'message' => self::errorMessage($e)]);
        }
    }

    public function confirm(R2PendingUpload $row): void
    {
        $disk = Storage::disk('r2');
        if (! $disk->exists($row->r2_path)) {
            throw new RuntimeException('R2 object is missing; retain receipt and retry from browser/backup.');
        }
        $remoteSize = $disk->size($row->r2_path);
        if ($row->byte_size !== null && $remoteSize !== (int) $row->byte_size) {
            throw new RuntimeException('R2 size mismatch; source retained.');
        }
        $stream = $disk->readStream($row->r2_path);
        if (! is_resource($stream)) {
            throw new RuntimeException('R2 object cannot be read.');
        }
        try {
            $hash = hash_init('sha256');
            $bytes = hash_update_stream($hash, $stream);
            $sha = hash_final($hash);
            if ($bytes !== $remoteSize) {
                throw new RuntimeException('R2 verification stream was incomplete; source retained.');
            }
            if ($row->content_sha256 && ! hash_equals($row->content_sha256, $sha)) {
                throw new RuntimeException('R2 content mismatch; source retained.');
            }
            $row->content_sha256 = $sha;
            $row->byte_size ??= $bytes;
        } finally {
            fclose($stream);
        }
        $row->update(['status' => 'pending', 'confirmed_at' => now(), 'error_message' => null]);
        Log::info('R2_UPLOAD_CONFIRMED', self::context($row));
    }

    public function cleanup(R2PendingUpload $row): void
    {
        if (! $row->completed_at || ! $row->target_dropbox_path) {
            throw new RuntimeException('Refusing cleanup without a durable Dropbox receipt.');
        }
        if ($row->cleaned_at) {
            return;
        }
        // Old duplicate rows may refer to the same object. Never remove their source.
        if (R2PendingUpload::where('r2_path', $row->r2_path)->whereNull('completed_at')->exists()) {
            throw new RuntimeException('Shared R2 object still has unconfirmed deliveries.');
        }
        $disk = Storage::disk('r2');
        if ($disk->exists($row->r2_path) && ! $disk->delete($row->r2_path)) {
            throw new RuntimeException('R2 cleanup failed.');
        }
        $row->update(['cleaned_at' => now(), 'error_message' => null]);
        Log::info('R2_CLEANUP_SUCCEEDED', self::context($row));
    }
}
