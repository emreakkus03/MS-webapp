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
    // 👇 AANPASSING 1: Deduplicatie voor de Job Registratie
    public function registerUpload(Request $request)
    {
        // 🛠️ FIX: We valideren nu ook de 'root_path' die van de JS komt
        $data = $request->validate([
            'task_id'      => 'required|integer',
            'r2_path'      => 'required|string',
            'namespace_id' => 'nullable|string', // Mag nullable zijn voor Perceel 2
            'adres_path'   => 'required|string',
            'root_path'    => 'nullable|string', // 👈 NIEUW: Dit veld miste!
        ]);

        // 🛑 CHECK: Is deze job al aangemaakt in de afgelopen 5 minuten?
        $duplicate = R2PendingUpload::where('task_id', $data['task_id'])
            ->where('r2_path', $data['r2_path'])
            ->where('created_at', '>', now()->subMinutes(5)) 
            ->first();

        if ($duplicate) {
            Log::info("♻️ Dubbele Job registratie genegeerd voor: " . $data['r2_path']);
            return response()->json(['success' => true, 'status' => 'already_queued']);
        }

        // Als hij niet bestaat, maak hem aan
        // 🛠️ FIX: We slaan root_path nu op in de DB
        $row = R2PendingUpload::create([
            'task_id'      => $data['task_id'],
            'r2_path'      => $data['r2_path'],
            'namespace_id' => $data['namespace_id'],
            'adres_path'   => $data['adres_path'],
            'root_path'    => $data['root_path'] ?? '', // 👈 NIEUW: Opslaan
            'status'       => 'pending',
        ]);

        // 🛠️ FIX: We geven root_path mee aan de Job
        // ⚠️ LET OP: Je moet straks ook je MoveToDropboxJob.php aanpassen zodat die dit 5e argument accepteert!
        dispatch(new \App\Jobs\MoveToDropboxJob(
            [$row->r2_path],
            $row->adres_path,
            $row->namespace_id,
            $row->task_id,
            $row->root_path // 👈 NIEUW: Meegeven aan de job
        ))->onQueue('uploads');

        return response()->json(['success' => true]);
    }

    // ... De rest van je bestand (checkFile en uploadFromSW) hoeft niet gewijzigd te worden ...
    
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
        } catch (\Aws\S3\Exception\S3Exception $e) {
            return response()->json(['exists' => false]);
        }
    }

    public function uploadFromSW(Request $request)
    {
        // Deze functie blijft hetzelfde, want deze doet puur de upload naar R2.
        // De metadata wordt geregeld in registerUpload hierboven.
        
        $request->validate([
            'file' => 'required|file',
            'task_id' => 'required',
            // namespace en root worden hier niet gebruikt voor opslag, enkel voor lock hash
            'adres_path' => 'required', 
            'unique_id' => 'nullable',
        ]);

        $file = $request->file('file');
        $uniqueId = $request->input('unique_id', uniqid());

        $cleanFolder = ltrim($request->adres_path, '/');
        $filename = $uniqueId . "_" . $file->getClientOriginalName();
        $fullPath = $cleanFolder . "/" . $filename;

        $filenameHash = md5($file->getClientOriginalName() . $request->adres_path);
        $lockKey = 'upload_lock_file_' . $filenameHash;

        if (Cache::has($lockKey)) {
            Log::info("✋ Upload geblokkeerd door Lock (al bezig): " . $file->getClientOriginalName());
            return response()->json([
                'success' => false, 
                'error' => 'Upload already in progress',
                'status' => 'locked'
            ], 429); 
        }
        Cache::put($lockKey, true, 60);

        try {
            Log::info("🔄 Upload start: $fullPath");

            if (Storage::disk('r2')->exists($fullPath)) {
                Cache::forget($lockKey);
                return response()->json(['success' => true, 'path' => $fullPath]);
            }

            $uploadedPath = Storage::disk('r2')->putFileAs($cleanFolder, $file, $filename);

            if ($uploadedPath) {
                $localSize = $file->getSize();
                $remoteSize = Storage::disk('r2')->size($fullPath);

                if ($localSize !== $remoteSize) {
                    Storage::disk('r2')->delete($fullPath);
                    throw new Exception("Corruptie: sizes match niet.");
                }

                Log::info("✅ Upload geslaagd: " . $fullPath);
                Cache::forget($lockKey);
                
                return response()->json(['success' => true, 'path' => $fullPath]);
            } else {
                throw new Exception("PutFileAs false");
            }

        } catch (Exception $e) {
            Cache::forget($lockKey);
            Log::error("🔥 R2 Fout: " . $e->getMessage());

            if (str_contains($e->getMessage(), '429') || str_contains($e->getMessage(), 'ServiceUnavailable')) {
                return response()->json(['error' => 'Rate Limit'], 429);
            }

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}