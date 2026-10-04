<?php

namespace App\Jobs;

use App\Models\R2PendingUpload;
use App\Models\Task;
use App\Services\DropboxPhotoRetry;
use App\Services\DropboxPhotoUploader;
use App\Services\DropboxService;
use App\Services\PhotoDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MoveToDropboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    public $timeout = 300;

    protected array $photos;

    protected string $adresPath;

    protected string $namespaceId;

    protected ?int $taskId;

    // Preserve serialized old jobs and their constructor signature.
    public function __construct(array $photos, string $adresPath, string $namespaceId, ?int $taskId = null)
    {
        $this->photos = $photos;
        $this->adresPath = $adresPath;
        $this->namespaceId = $namespaceId;
        $this->taskId = $taskId;
    }

    public function backoff(): array
    {
        return [30, 120, 300, 900];
    }

    public function handle(): void
    {
        $delivery = app(PhotoDelivery::class);
        if (count($this->photos) > 1) {
            foreach ($this->photos as $photo) {
                Bus::dispatch(
                    (new self([$photo], $this->adresPath, $this->namespaceId, $this->taskId))->onQueue('uploads')
                );
            }

            return;
        }
        foreach ($this->photos as $photo) {
            $lock = Cache::lock(PhotoDelivery::lockKey($photo), 600);
            if (! $lock->get()) {
                $this->release(30);

                return;
            }
            $row = null;
            try {
                $row = R2PendingUpload::where('r2_path', $photo)->where('task_id', $this->taskId)->first();
                if (! $row) {
                    // A pre-deployment job may have no receipt. Never silently discard it.
                    $row = R2PendingUpload::create(['r2_path' => $photo, 'task_id' => $this->taskId,
                        'adres_path' => $this->adresPath, 'namespace_id' => $this->namespaceId, 'status' => 'pending']);
                }
                if (! $row->completed_at) {
                    $row->increment('attempts');
                    $row->update(['status' => 'processing']);
                    Log::info('DROPBOX_UPLOAD_STARTED', PhotoDelivery::context($row));
                    $delivery->confirm($row);
                    $row->update(['status' => 'processing']);
                    $dropbox = app(DropboxService::class);
                    $isPerceel1 = $row->namespace_id !== $dropbox->getFluviusNamespaceId();
                    $folder = $this->normalizeAdresPath($row->adres_path, $isPerceel1);
                    // Legacy keys can collide across tasks/namespaces; allocate a stable suffix.
                    $filename = $row->upload_id ? basename($photo) :
                        'legacy-'.substr(hash('sha256', $row->task_id.'|'.$row->namespace_id.'|'.$photo), 0, 24).'_'.basename($photo);
                    $path = $folder.'/'.$filename;
                    $source = Storage::disk('r2')->readStream($photo);
                    if (! is_resource($source)) {
                        throw new \RuntimeException('Cannot read R2 photo.');
                    }
                    try {
                        $meta = app(DropboxPhotoUploader::class)->upload($dropbox, $row->namespace_id, $path, $source, $row->content_sha256, (int) $row->byte_size);
                    } finally {
                        fclose($source);
                    }
                    DB::transaction(function () use ($row, $meta, $isPerceel1) {
                        $task = Task::whereKey($row->task_id)->lockForUpdate()->first();
                        if ($task) {
                            $dbPath = ($isPerceel1 ? '/PERCEEL 1' : '').$meta['path_display'];
                            // Existing readers split on commas and URL-decode individual paths.
                            $dbPath = str_replace(['%', ','], ['%25', '%2C'], $dbPath);
                            $paths = $task->photo ? explode(',', $task->photo) : [];
                            $paths[] = $dbPath;
                            $task->photo = implode(',', array_unique($paths));
                            $task->save();
                        }
                        // Keep duplicate legacy rows in sync, but never delete another task's receipt.
                        R2PendingUpload::where('r2_path', $row->r2_path)->where('task_id', $row->task_id)
                            ->where('namespace_id', $row->namespace_id)->where('adres_path', $row->adres_path)
                            ->update(['status' => 'done', 'completed_at' => now(), 'confirmed_at' => now(),
                                'target_dropbox_path' => $meta['path_display'], 'error_message' => null]);
                    });
                    $row->refresh();
                    Log::info('DROPBOX_UPLOAD_SUCCEEDED', PhotoDelivery::context($row));
                }
                $delivery->cleanup($row);
            } catch (\Throwable $e) {
                if ($row) {
                    // Exception text from HTTP clients can contain signed URLs. Persist only safe messages.
                    $message = PhotoDelivery::errorMessage($e);
                    $row->update(['status' => $row->completed_at ? 'done' : 'failed', 'error_message' => mb_substr($message, 0, 1000)]);
                    Log::warning($row->completed_at ? 'R2_CLEANUP_RETRY' : 'DROPBOX_UPLOAD_RETRY',
                        PhotoDelivery::context($row) + ['exception' => $e::class, 'message' => $row->error_message]);
                }
                if ($e instanceof DropboxPhotoRetry && $this->job) {
                    $this->release(max(30, $e->retryAfter));

                    return;
                }
                throw new \RuntimeException(PhotoDelivery::errorMessage($e));
            } finally {
                $lock->release();
            }
        }
    }

    public function failed(?\Throwable $e): void
    {
        foreach ($this->photos as $photo) {
            $row = R2PendingUpload::where('r2_path', $photo)->where('task_id', $this->taskId)->first();
            if ($row && ! $row->completed_at) {
                $row->update(['status' => 'failed', 'error_message' => 'Queue attempts exhausted; retained for scheduled recovery.']);
                Log::error('DROPBOX_UPLOAD_FAILED', PhotoDelivery::context($row) + ['exception' => $e ? $e::class : null]);
            }
        }
    }

    private function normalizeAdresPath(string $path, bool $isPerceel1): string
    {
        $path = trim($path, '/');
        $path = preg_replace('#^(MS INFRA/Fluvius Aansluitingen/)+#i', '', $path);

        if (preg_match('#^PERCEEL\s*[12]/#i', $path)) {
            if ($isPerceel1) {
                $path = preg_replace('#^PERCEEL\s*1/#i', '', $path);
            }

            return '/'.ltrim($path, '/');
        }

        if (preg_match('#^Webapp uploads#i', $path)) {
            if ($isPerceel1) {
                return '/'.$path;
            } else {
                return "/PERCEEL 2/{$path}";
            }
        }

        if ($isPerceel1) {
            return "/Webapp uploads/{$path}";
        } else {
            return "/PERCEEL 2/Webapp uploads/{$path}";
        }
    }
}
