<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DropboxService;
use App\Models\DropboxFolder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

class DropboxController extends Controller
{
    protected $dropbox;

    // We injecteren jouw nieuwe DropboxService hier automatisch
    public function __construct(DropboxService $dropbox)
    {
        $this->dropbox = $dropbox;
    }

    private function checkAdminAccess()
    {
        $user = Auth::user();
        
        // Als er geen user is OF de rol is geen admin -> STOP.
        if (!$user || $user->role !== 'admin') {
            abort(403, 'Geen toegang. Alleen voor beheerders.');
        }
    }

    /**
     * Toon de lijst met hoofdmappen aan de Admin
     */
  public function index()
    {
        $this->checkAdminAccess();
        // Dit moet exact matchen met wat we hierboven hebben ingesteld
        $targetPath = '/MS INFRA/Signalisatieplan Vergunningen';

        $folders = DropboxFolder::where('parent_path', $targetPath)
            ->orderBy('name')
            ->get();

        return view('admin.dropbox.index', compact('folders'));
    }
    /**
     * STAP 1: De "Oppervlakkige Scan"
     * Haalt alleen de mappen op in /MS INFRA (bijv: 2024, 2025)
     */
 public function scanRoot()
    {
        $this->checkAdminAccess();
        try {
            $nsId = $this->dropbox->getInfraNamespaceId();

            // Het pad dat we net gevonden hebben
            $targetPath = '/MS INFRA/Signalisatieplan Vergunningen'; 

            $hasMore = true;
            $cursor = null;
            $count = 0;

            // 🔄 DE LOOP: Blijf vragen tot Dropbox zegt "Ik ben klaar"
            while ($hasMore) {
                
                if ($cursor) {
                    // Volgende pagina ophalen
                    $result = $this->dropbox->listFoldersContinue($nsId, $cursor);
                } else {
                    // Eerste aanvraag (recursive = false, want we willen alleen de jaren zien)
                    $result = $this->dropbox->listFoldersInNamespace($nsId, $targetPath, false);
                }

                $entries = $result['entries'];
                $cursor = $result['cursor'];
                $hasMore = $result['has_more']; // Dropbox vertelt hier of er nog meer is

                foreach ($entries as $entry) {
                    // Check of het een map is
                    if (($entry['.tag'] ?? '') === 'folder') {
                        
                        DropboxFolder::updateOrCreate(
                            ['dropbox_id' => $entry['id']], 
                            [
                                'name' => $entry['name'],
                                'path_display' => $entry['path_display'],
                                'parent_path' => $targetPath, 
                            ]
                        );
                        $count++;
                    }
                }
            }

            return redirect()->back()->with('success', "Scan voltooid! {$count} mappen gevonden in '{$targetPath}'.");

        } catch (\Exception $e) {
            Log::error("Dropbox Scan Error: " . $e->getMessage());
            return redirect()->back()->with('error', "Fout: " . $e->getMessage());
        }
    }

  public function toggleVisibility($id)
    {
        $this->checkAdminAccess(); // 🔒 Veiligheid

        $folder = DropboxFolder::findOrFail($id);

        // 1. Bepaal de nieuwe status (Aan of Uit)
        $newState = !$folder->is_visible;
        
        // 2. Update de map zelf
        $folder->is_visible = $newState;
        
        // Als we hem uitzetten, resetten we sync ook
        if (!$newState) {
            $folder->is_synced = false;
        }
        $folder->save();

        // 3. 🔥 DE FIX: Update ALLE onderliggende mappen (diep)
        // We zoeken alles wat begint met het pad van de hoofdmap + een slash
        // Bv: "/MS INFRA/2025" -> pakt ook "/MS INFRA/2025/Dossier A/Submap B"
        DropboxFolder::where('path_display', 'like', $folder->path_display . '/%')
            ->update(['is_visible' => $newState]);

        $status = $newState ? 'zichtbaar' : 'verborgen';
        
        return redirect()->back()->with('success', "Map '{$folder->name}' en ALLE submappen (diep) zijn nu {$status}.");
    }

    public function syncSubfolders()
    {
        $this->checkAdminAccess();
        // Zorg dat het script oneindig lang mag duren (anders krijg je een error na 60s)
        set_time_limit(0); 

        try {
            // Roep het commando aan dat we eerder in de terminal deden
            Artisan::call('dropbox:sync-subfolders');

            // Haal de output op (wat je normaal in de terminal ziet)
            $output = Artisan::output();

            return redirect()->back()->with('success', "De grote synchronisatie is voltooid! Alles is bijgewerkt.");

        } catch (\Exception $e) {
            return redirect()->back()->with('error', "Er ging iets mis tijdens de sync: " . $e->getMessage());
        }
    }
}