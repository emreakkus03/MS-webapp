<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\R2PendingUpload;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Jobs\MoveToDropboxJob; // 👈 Vergeet deze import niet!

class R2ManagementController extends Controller
{
    private function checkAdminAccess()
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'admin') {
            abort(403, 'Geen toegang. Alleen voor beheerders.');
        }
    }

    public function index()
    {
        $this->checkAdminAccess();
        $uploads = R2PendingUpload::whereIn('status', ['pending', 'failed'])
                    ->orderBy('created_at', 'desc')
                    ->get();

        foreach ($uploads as $upload) {
            try {
                $disk = Storage::disk('r2');
                $upload->preview_url = $disk->temporaryUrl(
                    $upload->r2_path,
                    now()->addMinutes(10)
                );
            } catch (\Exception $e) {
                $upload->preview_url = null;
            }
        }

        return view('admin.r2-manager', compact('uploads'));
    }

    public function retryAll()
    {
        $this->checkAdminAccess();
        
        try {
            // 1. Haal alle items op die vastzitten
            $uploads = R2PendingUpload::whereIn('status', ['pending', 'failed'])->get();

            if ($uploads->isEmpty()) {
                return redirect()->back()->with('warning', 'Geen uploads gevonden om te herstarten.');
            }

            $count = 0;

            foreach ($uploads as $upload) {
                // 2. Check of bestand bestaat (extra veiligheid)
                if (!Storage::disk('r2')->exists($upload->r2_path)) {
                    continue; 
                }

                // 3. Reset status
                $upload->update(['status' => 'pending']);

                // 4. Dispatch DIRECT met de 5 parameters
                dispatch(new MoveToDropboxJob(
                    [$upload->r2_path],
                    $upload->adres_path,
                    $upload->namespace_id,
                    $upload->task_id,
                    $upload->root_path // 👈 DE FIX: Hier sturen we de juiste mapinfo mee
                ))->onQueue('uploads');

                $count++;
            }

            return redirect()->back()->with('success', "🚀 {$count} uploads zijn opnieuw in de wachtrij geplaatst.");

        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Fout bij herstarten: ' . $e->getMessage());
        }
    }

    public function clearBucket()
    {
        $this->checkAdminAccess();
        try {
            // Roep de artisan command aan met --force
            \Illuminate\Support\Facades\Artisan::call('r2:clear', ['--force' => true]);
            return redirect()->back()->with('warning', 'Bucket en database zijn leeggemaakt.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Kon niet legen: ' . $e->getMessage());
        }
    }
}