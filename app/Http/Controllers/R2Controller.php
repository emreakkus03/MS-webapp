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
        $data = $request->validate([
            'task_id' => 'required|integer',
            'r2_path' => 'required|string', // Mag niet 'undefined' zijn
            'namespace_id' => 'required|string',
            'adres_path' => 'required|string',
        ]);

        // 🛑 CHECK: Is deze job al aangemaakt in de afgelopen 5 minuten?
        $duplicate = R2PendingUpload::where('task_id', $data['task_id'])
            ->where('r2_path', $data['r2_path'])
            ->where('created_at', '>', now()->subMinutes(5)) // Alleen recente dubbels blokkeren
            ->first();

        if ($duplicate) {
            Log::info("♻️ Dubbele Job registratie genegeerd voor: " . $data['r2_path']);
            return response()->json(['success' => true, 'status' => 'already_queued']);
        }

        // Als hij niet bestaat, maak hem aan
        $row = R2PendingUpload::create([
            'task_id'        => $data['task_id'],
            'r2_path'        => $data['r2_path'],
            'namespace_id'   => $data['namespace_id'],
            'adres_path'     => $data['adres_path'],
            'status'         => 'pending',
        ]);

        dispatch(new \App\Jobs\MoveToDropboxJob(
            [$row->r2_path],
            $row->adres_path,
            $row->namespace_id,
            $row->task_id
        ))->onQueue('uploads');

        return response()->json(['success' => true]);
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
        } catch (\Aws\S3\Exception\S3Exception $e) {
            return response()->json(['exists' => false]);
        }
    }

    public function uploadFromSW(Request $request)
    {
        $request->validate([
            'file' => 'required|file',
            'task_id' => 'required',
            'namespace_id' => 'required',
            'adres_path' => 'required',
            'unique_id' => 'nullable',
        ]);

        $file = $request->file('file');
        $uniqueId = $request->input('unique_id', uniqid());

        // 1. Bereken ALTIJD eerst het pad, zodat we het terug kunnen geven bij een lock
        $cleanFolder = ltrim($request->adres_path, '/');
        $filename = $uniqueId . "_" . $file->getClientOriginalName();
        $fullPath = $cleanFolder . "/" . $filename;

        // 2. Lock aanmaken op basis van originele bestandsnaam (zonder unique ID)
        $filenameHash = md5($file->getClientOriginalName() . $request->adres_path);
        $lockKey = 'upload_lock_file_' . $filenameHash;

        // 🛑 CHECK 1: Lock actief?
        if (Cache::has($lockKey)) {
            Log::info("✋ Upload geblokkeerd door Lock (al bezig): " . $file->getClientOriginalName());
            
            // 👇 ESSENTIEEL: We sturen het 'path' mee terug! Anders stuurt SW 'undefined' naar registerUpload.
            return response()->json([
                'success' => true, 
                'status' => 'duplicate_ignored',
                'path' => $fullPath 
            ]);
        }

        Cache::put($lockKey, true, 60);

        try {
            Log::info("🔄 Upload start: $fullPath");

            // 🛑 CHECK 2: Bestaat bestand al in R2?
            if (Storage::disk('r2')->exists($fullPath)) {
                Cache::forget($lockKey);
                return response()->json(['success' => true, 'path' => $fullPath]);
            }

            // 3. Upload uitvoeren
            $uploadedPath = Storage::disk('r2')->putFileAs($cleanFolder, $file, $filename);

            if ($uploadedPath) {
                // Size check
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