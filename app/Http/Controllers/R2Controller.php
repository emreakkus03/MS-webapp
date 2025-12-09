<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\R2PendingUpload;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class R2Controller extends Controller
{
    public function registerUpload(Request $request)
    {
        $data = $request->validate([
            'task_id' => 'required|integer',
            'r2_path' => 'required|string',
            'namespace_id' => 'required|string',
            'adres_path' => 'required|string',
        ]);

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
        $request->validate([
            'path' => 'required|string',
        ]);

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
            $client->headObject([
                'Bucket' => env('R2_BUCKET'),
                'Key'    => $path,
            ]);

            return response()->json(['exists' => true]);

        } catch (\Aws\S3\Exception\S3Exception $e) {
            if ($e->getStatusCode() === 404) {
                Log::warning("R2 HEAD 404: $path");
                return response()->json(['exists' => false]);
            }

            Log::error("R2 HEAD error:", ['err' => $e->getMessage()]);
            return response()->json([
                'exists' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

  public function uploadFromSW(Request $request)
    {
        
        Log::info("📥 R2 Upload gestart vanuit SW", $request->except(['file']));

        try {
            // 1. Validatie
            $request->validate([
                'file' => 'required|file',
                'task_id' => 'required',
                'namespace_id' => 'required',
                'adres_path' => 'required',
            ]);

            $file = $request->file('file');

            // 2. Pad en Bestandsnaam voorbereiden
            // Let op: putFileAs wil als eerste argument de MAP, niet het volledige pad.
            $cleanFolder = ltrim($request->adres_path, '/');
            $filename = uniqid() . "_" . $file->getClientOriginalName();
            
            // Voor logging
            $fullPath = $cleanFolder . "/" . $filename;
            Log::info("🔄 Uploaden naar map: $cleanFolder met naam: $filename");

            // 3. UPLOADEN MET putFileAs (De veilige Laravel manier)
            // Dit regelt streaming automatisch en voorkomt het 'false' probleem vaak.
            // Argumenten: (Folder, File Object, Bestandsnaam)
            $uploadedPath = Storage::disk('r2')->putFileAs($cleanFolder, $file, $filename);

            // 4. Controle
            if ($uploadedPath) {
                Log::info("✅ R2 upload GESLAAGD: " . $uploadedPath);
                
                return response()->json([
                    'success' => true,
                    'path' => $uploadedPath // Dit pad sturen we terug naar de SW
                ]);
            } else {
                throw new Exception("Storage::putFileAs retourneerde false.");
            }

        } catch (Exception $e) {
            Log::error("🔥 CRITICAL R2 UPLOAD ERROR: " . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}