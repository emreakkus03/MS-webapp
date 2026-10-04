<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\R2PendingUpload;
use Illuminate\Support\Facades\Artisan;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;


class R2ManagementController extends Controller
{

    private function checkAdminAccess()
    {
        $user = Auth::user();

        // Als er geen user is OF de rol is geen admin -> STOP.
        if (!$user || $user->role !== 'admin') {
            abort(403, 'Geen toegang. Alleen voor beheerders.');
        }
    }

    public function index()
    {
        $this->checkAdminAccess();
        $uploads = R2PendingUpload::whereIn('status', ['pending', 'failed'])->orderBy('created_at', 'desc')->get();

        // 2. 👇 DIT IS HET STUK DAT JE MISTE (De Previews genereren)
        // In je foreach loop:

        foreach ($uploads as $upload) {
            try {
                /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                $disk = Storage::disk('r2'); // 👈 We vertellen VS Code: "Dit is een Adapter"

                // Link is 10 minuten geldig
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
            Artisan::call('r2:retry-all');
            return redirect()->back()->with('success', 'Herstart van alle vastgelopen uploads is gestart.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Fout bij het herstarten van uploads: ' . $e->getMessage());
        }
    }

    public function clearBucket()
    {
        $this->checkAdminAccess();
        try {
            Artisan::call('r2:clear', ['--force' => true]);
            return redirect()->back()->with('Waarschuwing', 'Bucket en database zijn leeggemaakt.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Kon niet legen: ' . $e->getMessage());
        }
    }
}
