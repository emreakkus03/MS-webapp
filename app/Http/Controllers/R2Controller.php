<?php

namespace App\Http\Controllers;

use App\Models\R2PendingUpload;
use App\Models\Task;
use App\Services\PhotoDelivery;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class R2Controller extends Controller
{
    public function registerUpload(Request $request, PhotoDelivery $delivery)
    {
        $data = $request->validate([
            'task_id' => 'required|integer', 'r2_path' => 'required|string|max:255',
            'namespace_id' => 'required|string|max:255', 'adres_path' => 'required|string|max:255',
        ]);
        abort_if(in_array($data['r2_path'], ['undefined', 'null']), 422);
        $lock = Cache::lock(PhotoDelivery::lockKey($data['r2_path']), 600);
        abort_unless($lock->get(), 409, 'Upload is being processed; retry.');
        try {
            $row = R2PendingUpload::where('r2_path', $data['r2_path'])->where('task_id', $data['task_id'])->first();
            if (! $row) {
                Task::findOrFail($data['task_id']);
                abort_unless(Storage::disk('r2')->exists($data['r2_path']), 409, 'R2 object is not persisted.');
                $row = R2PendingUpload::create($data + ['status' => 'pending']);
            }
            abort_unless($row->namespace_id === $data['namespace_id'] && $row->adres_path === $data['adres_path'], 409, 'Upload destination conflict.');
            if (! $row->completed_at) {
                $delivery->confirm($row);
                $delivery->dispatch($row);
            }

            return response()->json(['success' => true, 'persisted' => true, 'path' => $row->r2_path, 'status' => $row->status]);
        } catch (\Throwable $e) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                throw $e;
            }
            Log::warning('PHOTO_REGISTRATION_FAILED', ['task_id' => $data['task_id'], 'r2_key' => $data['r2_path'],
                'exception' => $e::class, 'message' => PhotoDelivery::errorMessage($e)]);
            throw new \RuntimeException(PhotoDelivery::errorMessage($e));
        } finally {
            $lock->release();
        }
    }

    public function checkFile(Request $request)
    {
        $request->validate(['path' => 'required|string']);
        $path = $request->query('path');

        $client = new S3Client([
            'region' => 'auto',
            'version' => 'latest',
            'endpoint' => env('R2_ENDPOINT'),
            'credentials' => [
                'key' => env('R2_ACCESS_KEY_ID'),
                'secret' => env('R2_SECRET_ACCESS_KEY'),
            ],
        ]);

        try {
            $client->headObject(['Bucket' => env('R2_BUCKET'), 'Key' => $path]);

            return response()->json(['exists' => true]);
        } catch (S3Exception $e) {
            if ($e->getStatusCode() !== 404) {
                throw $e;
            }

            return response()->json(['exists' => false]);
        }
    }

    public function uploadFromSW(Request $request, PhotoDelivery $delivery)
    {
        $data = $request->validate([
            'file' => 'required|file|max:30720', 'task_id' => 'required|integer',
            'namespace_id' => 'required|string|max:255', 'adres_path' => 'required|string|max:255',
            'unique_id' => 'nullable|string|max:64|regex:/^[a-zA-Z0-9-]+$/',
        ]);
        $file = $request->file('file');
        $id = $request->input('unique_id') ?: (string) Str::uuid();
        $uuid = Str::isUuid($id) ? $id : null;
        // Keep the old integer-key path for old workers; new workers persist UUIDs.
        $folder = trim($data['adres_path'], '/');
        $filename = $id.'_'.basename($file->getClientOriginalName());
        $path = $folder.'/'.$filename;
        abort_if(strlen($path) > 255 || preg_match('#(^|/)\.\.(/|$)#', $path), 422, 'Invalid upload path.');
        $sha = hash_file('sha256', $file->getRealPath());
        $lock = Cache::lock(PhotoDelivery::lockKey($path), 600);
        abort_unless($lock->get(), 409, 'Upload is being processed; retry.');
        try {
            $row = $uuid ? R2PendingUpload::where('upload_id', $uuid)->first() :
                R2PendingUpload::where('r2_path', $path)->where('task_id', $data['task_id'])->first();
            if ($row) {
                abort_unless((int) $row->task_id === (int) $data['task_id'] && $row->r2_path === $path &&
                    $row->namespace_id === $data['namespace_id'] && $row->adres_path === $data['adres_path'], 409, 'Upload ID conflict.');
                abort_if($row->content_sha256 && ! hash_equals($row->content_sha256, $sha), 409, 'Upload content conflict.');
                if ($row->completed_at) {
                    return response()->json(['success' => true, 'persisted' => true, 'upload_id' => $id, 'path' => $path]);
                }
            } else {
                Task::findOrFail($data['task_id']);
                $row = R2PendingUpload::create([
                    'upload_id' => $uuid, 'task_id' => $data['task_id'], 'r2_path' => $path,
                    'namespace_id' => $data['namespace_id'], 'adres_path' => $data['adres_path'],
                    'status' => 'receiving', 'content_sha256' => $sha, 'byte_size' => $file->getSize(),
                ]);
            }
            Log::info('PHOTO_RECEIVED', PhotoDelivery::context($row));
            $disk = Storage::disk('r2');
            if (! $disk->exists($path)) {
                abort_if($row->confirmed_at !== null, 409, 'Acknowledged R2 source missing; recovery required.');
                if (! $disk->putFileAs($folder, $file, $filename)) {
                    throw new \RuntimeException('R2 write failed.');
                }
            }
            // Existing objects must match this request too (including legacy integer IDs).
            $row->content_sha256 = $sha;
            $row->byte_size = $file->getSize();
            $delivery->confirm($row);
            $delivery->dispatch($row);

            return response()->json(['success' => true, 'persisted' => true, 'upload_id' => $id, 'path' => $path]);
        } catch (\Throwable $e) {
            Log::warning('PHOTO_RECEIVE_FAILED', ['upload_id' => $id, 'task_id' => $data['task_id'],
                'r2_key' => $path, 'exception' => $e::class, 'message' => PhotoDelivery::errorMessage($e)]);
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                throw $e;
            }
            throw new \RuntimeException(PhotoDelivery::errorMessage($e));
        } finally {
            $lock->release();
        }
    }
}
