<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Services\DropboxService;
use App\Notifications\TaskCompletedNotification;
use App\Models\Team;
use App\Jobs\UploadToDropboxJob;

use App\Jobs\MoveToDropboxJob;
use App\Models\R2PendingUpload;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Bus;

class TaskController extends Controller
{
    public function finish(Request $request, Task $task)
    {
        $request->validate([
            'damage' => 'required|in:none,damage',
            'note'   => 'nullable|string|max:1000',
        ]);

        // 👉 sanitize note
        $cleanNote = $request->note ? ucfirst(e(strip_tags($request->note))) : null;

        if ($task->status === 'open') {
            $task->status = 'in behandeling';
            $task->note   = $request->damage === 'damage' ? $cleanNote : null;
        } elseif (in_array($task->status, ['in behandeling', 'reopened'])) {
            if ($request->damage === 'none') {
                $task->status = 'finished';
                $task->note   = null;
            } else {
                $task->status = 'in behandeling';
                $task->note   = $cleanNote;
            }
        }

        $task->save();

        Task::where('address_id', $task->address_id)
            ->where('id', '!=', $task->id)
            ->update(['status' => $task->status]);

        $team = Auth::user();
        $address = $task->address
            ? "{$task->address->street} {$task->address->number}, {$task->address->zipcode} {$task->address->city}"
            : "Onbekend adres";

        if ($request->damage === 'damage' && !empty($task->note)) {
            if ($team->role !== 'admin') {
                $admins = \App\Models\Team::where('role', 'admin')->get();
                foreach ($admins as $admin) {
                    $admin->notify(new \App\Notifications\TaskNoteAddedNotification(
                        $team->name,
                        $task->address
                    ));
                }
            }
        }

        if (
            $task->status === 'finished' &&
            in_array($team->name, ['Herstelploeg 1', 'Herstelploeg 2'])
        ) {
            $taskName = $address;
            if ($team->role !== 'admin') {
                $admins = \App\Models\Team::where('role', 'admin')->get();
                foreach ($admins as $admin) {
                    $admin->notify(new \App\Notifications\TaskCompletedNotification(
                        $team->name,
                        $taskName
                    ));
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Taak is afgerond!',
            'status'  => $task->status,
            'taskId'  => $task->id,
            'note'    => $task->note,
        ]);
    }





    public function index(Request $request)
    {
        return $this->filter($request);
    }

    public function reopen(Task $task)
    {
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Geen toegang');
        }

        if ($task->status !== 'finished') {
            return redirect()->back()->with('error', 'Alleen afgeronde taken kunnen heropend worden.');
        }

        $task->status = 'reopened';
        $task->save();

        return redirect()->route('tasks.index')->with('success', 'Taak is heropend.');
    }

    public function filter(Request $request)
    {
        $status = $request->query('status');
        $q      = $request->query('q');

        $tasks = Task::with(['address', 'team'])
            ->when($status, fn($query) => $query->where('status', $status))
            ->when($q, function ($query) use ($q) {
                $query->whereHas('address', function ($sub) use ($q) {
                    $sub->where('street', 'like', "%{$q}%")
                        ->orWhere('number', 'like', "%{$q}%")
                        ->orWhere('zipcode', 'like', "%{$q}%")
                        ->orWhere('city', 'like', "%{$q}%");
                });
            })
            ->orderBy('time', 'desc')
            ->paginate(10);

        if ($request->ajax()) {
            return response()->json([
                'rows_table' => view('tasks._rows_table', compact('tasks'))->render(),
                'rows_cards' => view('tasks._rows_cards', compact('tasks'))->render(),
                'pagination' => view('tasks._pagination', compact('tasks'))->render(),
            ]);
        }

        return view('tasks.index', compact('tasks'));
    }

    /**
     * ========================
     * Dropbox Endpoints
     * ========================
     */

    // 🔹 Toon enkel de perceel mappen (maar straks filteren we op "Webapp uploads")
    public function listPercelen(DropboxService $dropbox)
    {
        $namespaces = $dropbox->listNamespaces();

        $percelenFromNamespaces = collect($namespaces)
            ->filter(fn($ns) => stripos($ns['name'], 'perceel') !== false)
            ->map(fn($ns) => [
                'name' => $ns['name'],
                'id'   => $ns['namespace_id'],
                'type' => 'namespace',
            ]);

        $fluviusNamespaceId = $dropbox->getFluviusNamespaceId();
        $folders = $dropbox->listFoldersInNamespace($fluviusNamespaceId, "");

        $percelenFromFolders = collect($folders['entries'] ?? [])
            ->filter(
                fn($entry) =>
                $entry['.tag'] === 'folder' &&
                    stripos($entry['name'], 'perceel') !== false
            )
            ->map(fn($entry) => [
                'name' => $entry['name'],
                'id'   => $entry['path_display'],
                'type' => 'folder',
            ]);

        return response()->json(
            $percelenFromNamespaces->merge($percelenFromFolders)->values()
        );
    }
    public function listRegios(DropboxService $dropbox, Request $request)
    {
        $id   = $request->query('id');   // namespace_id of pad
        $type = $request->query('type'); // 'namespace' of 'folder'

        if (!$id || !$type) {
            return response()->json(['error' => 'id + type verplicht']);
        }

        // 🔹 Perceel 1 (namespace)
        if ($type === 'namespace') {
            $namespaceUsed = $id;
            $folderPath = "/Webapp uploads"; // altijd relatief binnen namespace

            $adresResult = $dropbox->listFoldersInNamespace($namespaceUsed, $folderPath);

            $count = collect($adresResult['entries'] ?? [])
                ->filter(fn($e) => ($e['.tag'] ?? null) === 'folder')
                ->count();

            return response()->json([[
                'name'      => "Webapp uploads ({$count})",
                'path'      => $folderPath,   // relatieve path voor Perceel 1
                'id'        => null,
                'namespace' => $namespaceUsed,
                'count'     => $count,
            ]]);
        }

        // 🔹 Perceel 2 (Fluvius)
        $namespaceUsed = $dropbox->getFluviusNamespaceId();
        $basePath = $id;

        $list = $dropbox->listFoldersInNamespace($namespaceUsed, $basePath);

        $webappFolders = collect($list['entries'] ?? [])->filter(function ($entry) {
            return ($entry['.tag'] ?? null) === 'folder'
                && strcasecmp(trim($entry['name']), 'Webapp uploads') === 0;
        });

        $regios = $webappFolders->take(1)->map(function ($folder) use ($dropbox, $namespaceUsed) {
            $folderPath = $folder['path_display'] ?? "";

            $adresResult = $dropbox->listFoldersInNamespace($namespaceUsed, $folderPath);

            $count = collect($adresResult['entries'] ?? [])
                ->filter(fn($e) => ($e['.tag'] ?? null) === 'folder')
                ->count();

            return [
                'name'      => $folder['name'] . " ({$count})",
                'path'      => $folderPath,
                'id'        => $folder['id'] ?? null,
                'namespace' => $namespaceUsed,
                'count'     => $count,
            ];
        })->values();

        return response()->json($regios);
    }





    // 🔹 Enkel adressen binnen "Webapp uploads"
    public function listAdressen(DropboxService $dropbox, Request $request)
    {
        $namespaceId = $request->query('namespace_id');
        $path        = $request->query('path');
        $cursor      = $request->query('cursor');
        $search      = $request->query('search');

        if (!$namespaceId || (!$path && !$cursor)) {
            return response()->json(['error' => 'namespace_id + path of cursor verplicht'], 400);
        }

        if ($search) {
            $adressen = $dropbox->searchFoldersInNamespace($namespaceId, $path, $search);
            return response()->json([
                'entries'  => $adressen,
                'cursor'   => null,
                'has_more' => false,
            ]);
        }

        $result = $cursor
            ? $dropbox->listFoldersContinue($namespaceId, $cursor)
            : $dropbox->listFoldersInNamespace($namespaceId, $path);

        $adressen = collect($result['entries'] ?? [])
            ->filter(fn($entry) => $entry['.tag'] === 'folder')
            ->map(fn($entry) => [
                'name'      => $entry['name'],
                'path'      => $entry['path_display'] ?? null,
                'id'        => $entry['id'] ?? null,
                'namespace' => $namespaceId,
                'tag'       => $entry['.tag'] ?? 'unknown',
            ])
            ->values();

        return response()->json([
            'entries'  => $adressen,
            'cursor'   => $result['cursor'] ?? null,
            'has_more' => $result['has_more'] ?? false,
        ]);
    }

    // 🔹 Nieuwe adresmap altijd in Webapp uploads
    public function createAdresFolder(Request $request, DropboxService $dropbox)
    {
        Log::info('createAdresFolder aangeroepen', $request->all());
        $data = $request->validate([
            'namespace_id' => 'required|string',
            'path'         => 'required|string',
            'adres'        => 'required|string|min:1|max:200'
        ]);

        try {
            $namespaceId = $data['namespace_id'];
            $parentPath  = $data['path'];

            // 🔹 Bij Perceel 1 → pad relativeren
            if ($namespaceId !== $dropbox->getFluviusNamespaceId()) {
                $parentPath = ltrim(preg_replace('/^\/?Perceel 1/i', '', $parentPath), '/');
            }

            $meta = $dropbox->createChildFolder($namespaceId, $parentPath, $data['adres']);

            return response()->json([
                'success' => true,
                'message' => 'Map succesvol aangemaakt!',
                'folder'  => [
                    'name'      => $meta['name'] ?? $data['adres'],
                    'path'      => $meta['path_display'] ?? (rtrim($parentPath, '/') . '/' . $data['adres']),
                    'namespace' => $namespaceId,
                ],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dropbox fout bij map maken.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }


    public function uploadPhoto(Request $request, DropboxService $dropbox, $taskId)
    {
        $request->validate([
            'namespace_id' => 'nullable|string',
            // 'path' => 'required|string', // ❌ VERWIJDERD: Dit wordt nu automatisch gegenereerd
            'photos'       => 'required',
        ]);

        // Zorg dat namespace_id optioneel is of een default waarde krijgt
$namespaceId = $request->input('namespace_id') ?: $dropbox->getFluviusNamespaceId();
        
        // 🔹 1. Haal taak op met relaties
        $task = Task::with(['address', 'team'])->findOrFail($taskId);

        // 🔹 2. Genereer de mapnaam: Straat Nr Postcode Stad Ploegnaam - Datum (Taak ID)
        // Zorg dat de datum geformatteerd is (bijv. d-m-Y)
        $dateString = $task->time instanceof \Carbon\Carbon 
            ? $task->time->format('d-m-Y') 
            : date('d-m-Y', strtotime($task->time));

        $folderName = sprintf(
            '%s %s %s %s %s - %s (%s)',
            $task->address->street,
            $task->address->number,
            $task->address->zipcode,
            $task->address->city,
            $task->team->name,
            $dateString,
            $task->id
        );

        // 🔹 3. Sanitize: Verwijder illegale tekens voor mapnamen (zoals / \ : * ? " < > |)
        $adresPath = preg_replace('/[\\/\\\\:*?"<>|]/', '', $folderName);

        // 🔍 Detecteer perceeltype via namespace_id
        $isPerceel1 = $namespaceId !== $dropbox->getFluviusNamespaceId();

        // 📁 Bouw basis Dropbox-pad
        if ($isPerceel1) {
            $fullDropboxPath = "Fluvius Aansluitingen/PERCEEL 1/Webapp uploads/{$adresPath}";
        } else {
            $fullDropboxPath = "Fluvius Aansluitingen/PERCEEL 2/Webapp uploads/{$adresPath}";
        }

        // 💾 Sla tijdelijk R2-paden op
        $photos = $task->photo ? explode(',', $task->photo) : [];
        foreach ((array)$request->input('photos') as $photoPath) {
            $photos[] = $photoPath;
        }

        Log::info("📸 Tijdelijke R2 upload ontvangen (Auto Path)", [
            'task_id'      => $taskId,
            'adresPath'    => $adresPath, // De nieuwe gegenereerde naam
            'namespace_id' => $namespaceId,
        ]);

        foreach ($request->input('photos') as $path) {
            $fullTargetPath = "{$fullDropboxPath}/" . basename($path);

            R2PendingUpload::create([
                'task_id'             => $taskId,
                'r2_path'             => $path,
                'namespace_id'        => $namespaceId,
                'perceel'             => $isPerceel1 ? '1' : '2',
                'regio_path'          => null, // ⚠️ Niet meer relevant aangezien we direct de mapnaam genereren
                'adres_path'          => $adresPath,
                'target_dropbox_path' => $fullTargetPath,
            ]);
        }

        dispatch(new MoveToDropboxJob(
            $request->input('photos'),
            $adresPath,
            $namespaceId,
            $taskId
        ))->onQueue('uploads');

        return response()->json([
            'success' => true,
            'queued'  => true,
            'message' => '📦 Foto’s via R2 geüpload en worden op achtergrond verplaatst naar Dropbox.',
            'generated_path' => $adresPath // Handig voor debugging in frontend
        ]);
    }

    public function listTeamMembers(DropboxService $dropbox)
    {
        try {
            $members = $dropbox->listTeamMembers();

            $clean = collect($members['members'] ?? [])->map(fn($m) => [
                'team_member_id' => $m['profile']['team_member_id'] ?? null,
                'email'          => $m['profile']['email'] ?? null,
                'display_name'   => $m['profile']['name']['display_name'] ?? null,
            ]);

            return response()->json($clean->values());
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function previewPhoto(Request $request, DropboxService $dropbox)
    {
        $request->validate([
            'path'        => 'required|string',
            'namespace_id' => 'nullable|string',
        ]);

        try {
            $namespaceId = $request->get('namespace_id') ?: $dropbox->getFluviusNamespaceId();
            $link = $dropbox->getTemporaryLink($namespaceId, $request->path);

            return redirect()->away($link);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Preview ophalen mislukt',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function startDropboxSession(DropboxService $dropbox)
    {
        try {
            $session = $dropbox->startUploadSession();

            return response()->json([
                'success'        => true,
                'session_id'     => $session['session_id'] ?? null,
                'access_token'   => $dropbox->getAccessToken(),
                // 🔹 Voeg deze toe:
                'team_member_id' => config('services.dropbox.team_member_id'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Dropbox upload session start failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Kon geen upload-sessie starten bij Dropbox.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
 // In App\Http\Controllers\TaskController.php

public function uploadTemp(Request $request, $taskId, DropboxService $dropbox)
    {
        $task = Task::with(['address', 'team'])->findOrFail($taskId);
        
        // Laravel pakt 'photos[]' automatisch op als 'photos'
        $files = $request->file('photos'); 
        if (!is_array($files)) $files = [$files];

        // 1. Data uit request halen
        $namespaceId = $request->input('namespace_id');
        $rootPath = $request->input('root_path');

        // 2. Bepaal het BASISPAD & NAMESPACE
        // ---------------------------------------------------------
        // FIX: We gebruiken env() in plaats van API calls om cURL errors te voorkomen!
        
        // Scenario A: P1 (Namespace modus) -> rootPath is leeg
        if (empty($rootPath)) {
            $rootPath = "/Webapp uploads";
            // Fallback: als namespace leeg is, pak Fluvius uit ENV
            if (empty($namespaceId)) {
                $namespaceId = env('DROPBOX_FLUVIUS_NAMESPACE_ID');
            }
        } 
        // Scenario B: P2 (Folder modus) -> rootPath is gevuld
        else {
            // Forceer Fluvius ID uit ENV (geen internet nodig)
            if (empty($namespaceId)) {
                $namespaceId = env('DROPBOX_FLUVIUS_NAMESPACE_ID');
            }
        }

        // NOODOPLOSSING: Als .env leeg is, probeer toch API (maar dit mag eigenlijk niet gebeuren)
        if (empty($namespaceId)) {
            try {
                $namespaceId = $dropbox->getFluviusNamespaceId();
            } catch (\Throwable $e) {
                // Als we hier zijn, is er én geen .env én geen internet.
                // We zetten een placeholder zodat R2 upload TOCH doorgaat. De Job fixt het later wel.
                \Log::error("Namespace ID onbekend tijdens upload: " . $e->getMessage());
                $namespaceId = "OFFLINE_PENDING";
            }
        }
        // ---------------------------------------------------------

        // 3. Genereer mapnaam
        $dateString = $task->time instanceof \Carbon\Carbon 
            ? $task->time->format('d-m-Y') 
            : date('d-m-Y', strtotime($task->time));

        $folderName = sprintf(
            '%s %s %s %s %s - %s (%s)',
            $task->address->street, $task->address->number, $task->address->zipcode,
            $task->address->city, $task->team->name, $dateString, $task->id
        );
        $adresPath = preg_replace('/[\\/\\\\:*?"<>|]/', '', $folderName);

        // 4. Volledig pad
        $fullDropboxPath = rtrim($rootPath, '/') . '/' . $adresPath;

        $uploadedPaths = [];

        // 5. Uploaden naar R2
        foreach ($files as $file) {
            $r2Path = $file->store('uploads', 'r2'); 
            $uploadedPaths[] = $r2Path;
            
            $fullTargetPath = "{$fullDropboxPath}/" . basename($r2Path);

            // Bepaal P1/P2 voor database log
            $isPerceel1 = $namespaceId !== env('DROPBOX_FLUVIUS_NAMESPACE_ID') && $namespaceId !== "OFFLINE_PENDING";

            R2PendingUpload::create([
                'task_id'             => $taskId,
                'r2_path'             => $r2Path,
                'namespace_id'        => $namespaceId,
                'perceel'             => $isPerceel1 ? '1' : '2',
                'regio_path'          => $rootPath,
                'adres_path'          => $adresPath,
                'target_dropbox_path' => $fullTargetPath,
            ]);
        }

        // 6. Job starten
        if (count($uploadedPaths) > 0) {
            dispatch(new MoveToDropboxJob(
                $uploadedPaths,
                $adresPath,   
                $namespaceId, 
                $taskId,
                $rootPath     
            ))->onQueue('uploads');
        }

        return response()->json([
            'status' => 'queued',
            'count'  => count($files),
            'path'   => $fullDropboxPath,
            'message' => 'Upload gestart'
        ]);
    }
}
