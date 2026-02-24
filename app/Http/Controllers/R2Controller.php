<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\R2PendingUpload;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Exception;

class R2Controller extends Controller
{
    /**
     * Register een upload en dispatch MoveToDropboxJob
     * 
     * FIX: Betere deduplicatie + altijd een job aanmaken als er nog geen actieve is
     */
    public function registerUpload(Request $request)
    {
        $data = $request->validate([
            'task_id'      => 'required|integer',
            'r2_path'      => 'required|string',
            'namespace_id' => 'required|string',
            'adres_path'   => 'required|string',
        ]);

        // 🛑 Validatie: r2_path mag niet 'undefined' of leeg zijn
        if (in_array($data['r2_path'], ['undefined', 'null', ''])) {
            Log::warning("❌ registerUpload: ongeldige r2_path", $data);
            return response()->json(['success' => false, 'error' => 'Invalid r2_path'], 422);
        }

        // Deduplicatie: check of exact deze upload al pending/processing is
        $duplicate = R2PendingUpload::where('task_id', $data['task_id'])
            ->where('r2_path', $data['r2_path'])
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if ($duplicate) {
            Log::info("♻️ Upload al in queue: " . $data['r2_path']);
            return response()->json(['success' => true, 'status' => 'already_queued']);
        }

        // Check of dit bestand al eerder succesvol is verwerkt (voorkom dubbele Dropbox uploads)
        $alreadyDone = R2PendingUpload::where('task_id', $data['task_id'])
            ->where('r2_path', $data['r2_path'])
            ->where('status', 'done')
            ->where('created_at', '>', now()->subHour()) // Binnen het afgelopen uur
            ->first();

        if ($alreadyDone) {
            Log::info("✅ Upload al verwerkt: " . $data['r2_path']);
            return response()->json(['success' => true, 'status' => 'already_done']);
        }

        try {
            $row = R2PendingUpload::create([
                'task_id'      => $data['task_id'],
                'r2_path'      => $data['r2_path'],
                'namespace_id' => $data['namespace_id'],
                'adres_path'   => $data['adres_path'],
                'status'       => 'pending',
            ]);

            dispatch(new \App\Jobs\MoveToDropboxJob(
                [$row->r2_path],
                $row->adres_path,
                $row->namespace_id,
                $row->task_id
            ))->onQueue('uploads');

            Log::info("📦 Job dispatched voor: " . $data['r2_path']);

            return response()->json(['success' => true, 'status' => 'queued']);
        } catch (\Throwable $e) {
            Log::error("❌ registerUpload fout: " . $e->getMessage(), $data);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function checkFile(Request $request)
    {
        $request->validate(['path' => 'required|string']);
        $path = $request->query('path');

        $client = new S3Client([
            'region'      => 'auto',
            'version'     => 'latest',
            'endpoint'    => env('R2_ENDPOINT'),
            'credentials' => [
                'key'    => env('R2_ACCESS_KEY_ID'),
                'secret' => env('R2_SECRET_ACCESS_KEY'),
            ],
        ]);

        try {
            $client->headObject(['Bucket' => env('R2_BUCKET'), 'Key' => $path]);
            return response()->json(['exists' => true]);
        } catch (\Aws\S3\Exception\S3Exception $e) {
            return response()->json(['exists' => false]);
        }
    }

    /**
     * Upload vanuit Service Worker naar R2
     * 
     * FIX: Betere lock handling + geen 429 bij lock (dat stopte de hele SW queue)
     */
    public function uploadFromSW(Request $request)
    {
        $request->validate([
            'file'         => 'required|file',
            'task_id'      => 'required',
            'namespace_id' => 'required',
            'adres_path'   => 'required',
            'unique_id'    => 'nullable',
        ]);

        $file = $request->file('file');
        $uniqueId = $request->input('unique_id', uniqid());

        $cleanFolder = ltrim($request->adres_path, '/');
        $filename = $uniqueId . "_" . $file->getClientOriginalName();
        $fullPath = $cleanFolder . "/" . $filename;

        // Lock op basis van bestandsnaam + adres (voorkom parallelle uploads van hetzelfde bestand)
        $filenameHash = md5($file->getClientOriginalName() . $request->adres_path . $request->task_id);
        $lockKey = 'upload_lock_' . $filenameHash;

        // 👇 FIX: Bij een actieve lock, geef een 200 terug met het verwachte pad
        // De SW kan dan gewoon doorgaan met register-upload
        if (Cache::has($lockKey)) {
            Log::info("✋ Lock actief voor: " . $file->getClientOriginalName());

            // Check of het bestand al in R2 staat (vorige upload was succesvol)
            if (Storage::disk('r2')->exists($fullPath)) {
                return response()->json(['success' => true, 'path' => $fullPath, 'status' => 'already_exists']);
            }

            // Bestand nog niet in R2 maar lock is actief → wacht even
            return response()->json([
                'success' => false,
                'error'   => 'Upload in progress',
                'status'  => 'locked',
                'retry_after' => 5
            ], 409); // 409 Conflict i.p.v. 429
        }

        // Zet lock (60 seconden)
        Cache::put($lockKey, true, 60);

        try {
            // Check of bestand al bestaat
            if (Storage::disk('r2')->exists($fullPath)) {
                Cache::forget($lockKey);
                Log::info("📁 Bestand bestaat al in R2: " . $fullPath);
                return response()->json(['success' => true, 'path' => $fullPath, 'status' => 'already_exists']);
            }

            // Upload
            $uploadedPath = Storage::disk('r2')->putFileAs($cleanFolder, $file, $filename);

            if ($uploadedPath) {
                // Verificatie
                $localSize = $file->getSize();
                $remoteSize = Storage::disk('r2')->size($fullPath);

                if (abs($localSize - $remoteSize) > 1024) { // 1KB tolerance
                    Storage::disk('r2')->delete($fullPath);
                    throw new Exception("Size mismatch: local={$localSize}, remote={$remoteSize}");
                }

                Log::info("✅ R2 upload OK: " . $fullPath);
                Cache::forget($lockKey);

                return response()->json(['success' => true, 'path' => $fullPath]);
            } else {
                throw new Exception("putFileAs returned false");
            }

        } catch (Exception $e) {
            Cache::forget($lockKey);
            Log::error("🔥 R2 upload fout: " . $e->getMessage());

            // Rate limit van R2 zelf
            if (str_contains($e->getMessage(), '429') || str_contains($e->getMessage(), 'SlowDown')) {
                return response()->json(['error' => 'R2 Rate Limit', 'retry_after' => 10], 429);
            }

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}