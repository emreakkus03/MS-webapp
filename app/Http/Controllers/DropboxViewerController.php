<?php

namespace App\Http\Controllers;

use App\Models\DropboxFolder;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Services\DropboxService;

class DropboxViewerController extends Controller
{
    protected $dropbox;

    public function __construct(DropboxService $dropbox)
    {
        $this->dropbox = $dropbox;
    }

    public function index(Request $request)
    {
        $query = DropboxFolder::query()->where('is_visible', true);

        if ($request->has('search') && !empty($request->search)) {
            $query->where('name', 'like', '%' . $request->search . '%');
        } else {
             $query->where('name', '!=', '2025 - VERGUNNINGEN'); 
        }

        $folders = $query->orderBy('name', 'asc')->paginate(24);
        return view('files.index', compact('folders'));
    }

    public function show($id)
    {
        $folder = DropboxFolder::findOrFail($id);

        try {
            $nsId = $this->dropbox->getInfraNamespaceId();
            
            // 👇 HIER IS DE WIJZIGING:
            // Verander 'false' naar 'true'. 
            // Dit betekent: "Haal alles op wat in deze map zit, ook in submappen."
            $result = $this->dropbox->listFoldersInNamespace($nsId, $folder->path_display, true);
            
            // Filter: we willen alleen bestanden tonen
            $files = collect($result['entries'])->filter(function($entry) {
                return ($entry['.tag'] ?? '') === 'file';
            });

            // Sorteer zodat bestanden in submappen netjes bij elkaar staan
            $files = $files->sortBy('path_display');

            return view('files.show', compact('folder', 'files', 'nsId'));

        } catch (\Exception $e) {
            return back()->with('error', 'Fout: ' . $e->getMessage());
        }
    }

    /**
     * 🆕 NIEUW: Slimme Preview Functie
     * Bepaalt of we een PDF direct openen of een Foto in een viewer tonen.
     */
    public function preview(Request $request)
    {
        $path = $request->input('path');
        $nsId = $request->input('ns_id');
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // Als het een afbeelding is, toon de mooie viewer pagina
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'])) {
            return view('files.preview', compact('path', 'nsId'));
        }

        // Als het een PDF of iets anders is, open direct de stream
        return redirect()->route('dossiers.stream', ['path' => $path, 'ns_id' => $nsId]);
    }
    
    /**
     * De Stream Functie (Haalt de data op)
     * Nu met ondersteuning voor ALLE afbeeldingstypes!
     */
   public function stream(Request $request)
    {
        $path = $request->input('path');
        $nsId = $request->input('ns_id');

        // DEBUG 1: Check of de parameters aankomen
        if (empty($path) || empty($nsId)) {
            dd("FOUT: Parameters ontbreken.", $request->all());
        }
        
        try {
            // DEBUG 2: Toon wat we naar Dropbox sturen (haal dit weg als het werkt)
            // dd("We vragen link aan voor:", $nsId, $path);

            $link = $this->dropbox->getTemporaryLink($nsId, $path);
            
            // Als we hier zijn, is de link gelukt!
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            
            $mimeType = match($extension) {
                'pdf' => 'application/pdf',
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
                'bmp' => 'image/bmp',
                'txt' => 'text/plain',
                default => 'application/octet-stream',
            };

            $fileStream = fopen($link, 'r');

            return response()->stream(function() use ($fileStream) {
                fpassthru($fileStream);
                fclose($fileStream);
            }, 200, [
                "Content-Type" => $mimeType,
                "Content-Disposition" => "inline; filename=\"" . basename($path) . "\"",
            ]);

        } catch (\Exception $e) {
            // 🛑 HIER ZIT DE FOUT: We gooien hem nu op het scherm!
            dd([
                'ERROR_MESSAGE' => $e->getMessage(),
                'NAMESPACE_ID' => $nsId,
                'PATH' => $path,
                'TRACE' => $e->getTraceAsString()
            ]);
        }
    }
}