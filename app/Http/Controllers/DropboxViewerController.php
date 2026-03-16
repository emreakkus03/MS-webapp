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

    if (empty($path) || empty($nsId)) {
        abort(400, 'Parameters ontbreken.');
    }

    // 🛡️ Path traversal check
    if (str_contains($path, '../') || str_contains($path, '..\\') || str_contains($path, "\0")) {
        abort(403, 'Ongeldig bestandspad gedetecteerd.');
    }

    try {
        // Haal tijdelijke Dropbox link op
        $link = $this->dropbox->getTemporaryLink($nsId, $path);

        // 🛡️ Valideer dat het een echte URL is
        if (!filter_var($link, FILTER_VALIDATE_URL)) {
            throw new \Exception('Dropbox gaf geen geldige URL terug.');
        }

        // 🛡️ Dwing HTTPS af
        $parsedUrl = parse_url($link);
        if (($parsedUrl['scheme'] ?? '') !== 'https') {
            throw new \Exception('Alleen HTTPS verbindingen zijn toegestaan.');
        }

        // 🛡️ Dwing Dropbox domein af (SSRF preventie)
        $host = $parsedUrl['host'] ?? '';
        $isDropboxDomain = str_ends_with($host, '.dropboxusercontent.com')
            || str_ends_with($host, '.dropbox.com');

        if (!$isDropboxDomain) {
            \Illuminate\Support\Facades\Log::alert('Mogelijke SSRF aanval geblokkeerd!', ['url' => $link]);
            throw new \Exception('Ongeldig domein gedetecteerd.');
        }

        // Bepaal MIME type op basis van extensie
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeType = match($extension) {
            'pdf'        => 'application/pdf',
            'jpg','jpeg' => 'image/jpeg',
            'png'        => 'image/png',
            'gif'        => 'image/gif',
            'webp'       => 'image/webp',
            'svg'        => 'image/svg+xml',
            'bmp'        => 'image/bmp',
            'txt'        => 'text/plain',
            default      => 'application/octet-stream',
        };

        // ✅ Stream via Guzzle — 8KB per keer, nooit het hele bestand in RAM
        return response()->stream(function() use ($link) {
            $client       = new \GuzzleHttp\Client();
            $guzzleResponse = $client->get($link, ['stream' => true]);
            $body         = $guzzleResponse->getBody();

            while (!$body->eof()) {
                echo $body->read(8192);
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type'        => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        ]);

    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Dropbox stream error: ' . $e->getMessage());
        abort(404, 'Bestand niet gevonden of kan niet worden geladen.');
    }
}
}