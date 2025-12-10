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
                'unique_id' => 'nullable', // 👈 NIEUW: We accepteren nu de ID van de SW
            ]);

            $file = $request->file('file');

            // 2. Pad en Bestandsnaam voorbereiden
            $cleanFolder = ltrim($request->adres_path, '/');

            // 👇 DE FIX: Gebruik de ID van de tablet. Als die er niet is (oude versie), gebruik uniqid().
            $uniqueId = $request->input('unique_id', uniqid());

            // Gebruik die vaste ID in de bestandsnaam. 
            // Hierdoor blijft de naam ALTIJD hetzelfde, ook bij retries.
            $filename = $uniqueId . "_" . $file->getClientOriginalName();
            
            $fullPath = $cleanFolder . "/" . $filename;

            Log::info("🔄 Upload poging voor: $fullPath");

            // 👇 IDEMPOTENCY CHECK (De anti-dubbel maatregel)
            // Als de Service Worker dit bestand al eens heeft gestuurd (maar geen antwoord kreeg),
            // dan bestaat het bestand al in R2.
            if (Storage::disk('r2')->exists($fullPath)) {
                Log::info("♻️ Dubbele upload overgeslagen (bestand bestaat al): " . $fullPath);
                
                // We sturen direct 'succes' terug. 
                // De Service Worker denkt: "Mooi, gelukt!" en verwijdert het uit IndexedDB.
                return response()->json([
                    'success' => true,
                    'path' => $fullPath
                ]);
            }

            // 3. UPLOADEN MET putFileAs (Alleen als hij nog niet bestond)
            Log::info("🚀 Nieuwe upload starten naar: $fullPath");
            $uploadedPath = Storage::disk('r2')->putFileAs($cleanFolder, $file, $filename);

            // 4. Controleer resultaat (De "Paranoïde Check")
            if ($uploadedPath) {
                // Stap A: Bestaat het echt?
                if (!Storage::disk('r2')->exists($fullPath)) {
                    throw new Exception("Bestand geüpload maar niet gevonden in R2 (exists check failed).");
                }

                // Stap B: Is de grootte exact hetzelfde? (Cruciaal voor integriteit)
                $localSize = $file->getSize(); // Grootte van de upload
                $remoteSize = Storage::disk('r2')->size($fullPath); // Grootte in de cloud

                if ($localSize !== $remoteSize) {
                    // ALARM! R2 heeft een corrupt/half bestand.
                    Log::error("❌ R2 Data Corruptie! Lokaal: $localSize bytes, R2: $remoteSize bytes.");
                    
                    // Verwijder het corrupte bestand zodat we het de volgende keer vers kunnen proberen
                    Storage::disk('r2')->delete($fullPath);
                    
                    throw new Exception("Data corruptie: bestandsgrootte komt niet overeen.");
                }

                Log::info("✅ R2 upload GESLAAGD & GEVERIFIEERD ($localSize bytes): " . $fullPath);
                
                return response()->json([
                    'success' => true,
                    'path' => $fullPath
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