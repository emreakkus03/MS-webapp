<?php 

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use App\Services\DropboxService;


class TaskController extends Controller
{
    public function finish(Request $request, Task $task)
{
    $request->validate([
        'damage' => 'required|in:none,damage',
        'note'   => 'nullable|string|max:1000',
    ]);

    if ($task->status === 'open') {
        $task->status = 'in behandeling';
        $task->note   = $request->damage === 'damage' ? ucfirst($request->note) : null;
    } elseif (in_array($task->status, ['in behandeling', 'reopened'])) {
        if ($request->damage === 'none') {
            $task->status = 'finished';
            $task->note   = null;
        } else {
            $task->status = 'in behandeling';
            $task->note   = ucfirst($request->note);
        }
    }

    $task->save();

    Task::where('address_id', $task->address_id)
        ->where('id', '!=', $task->id)
        ->update(['status' => $task->status]);

    // 🔥 Nieuw: JSON response i.p.v. redirect
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
                'rows' => view('tasks._rows', compact('tasks'))->render(),
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

    // Stap 1: lijst percelen (PERCEEL 1 / PERCEEL 2)
  public function listPercelen(DropboxService $dropbox)
{
    // Namespaces (bv. PERCEEL 1)
    $namespaces = $dropbox->listNamespaces();

    $percelenFromNamespaces = collect($namespaces)
        ->filter(fn($ns) => stripos($ns['name'], 'perceel') !== false)
        ->map(fn($ns) => [
            'name' => $ns['name'],
            'id'   => $ns['namespace_id'],
            'type' => 'namespace',
        ]);

    // Folders binnen Fluvius (bv. PERCEEL 2)
    $fluviusNamespaceId = $dropbox->getFluviusNamespaceId();
    $folders = $dropbox->listFoldersInNamespace($fluviusNamespaceId, "");

    $percelenFromFolders = collect($folders['entries'] ?? [])
        ->filter(fn($entry) =>
            $entry['.tag'] === 'folder' &&
            stripos($entry['name'], 'perceel') !== false
        )
        ->map(fn($entry) => [
            'name' => $entry['name'],
            'id'   => $entry['path_display'],   // <== let op, dit is een pad
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

    if ($type === 'namespace') {
        // Perceel 1 → eigen namespace
        $namespaceUsed = $id;
        $result = $dropbox->listFoldersInNamespace($id, "");
    } else {
        // Perceel 2 → folder binnen Fluvius
        $namespaceUsed = $dropbox->getFluviusNamespaceId();
        $result = $dropbox->listFoldersInNamespace($namespaceUsed, $id);
    }

    $regios = collect($result['entries'] ?? [])
        ->filter(fn($e) => ($e['.tag'] ?? null) === 'folder')
        ->map(function ($folder) use ($dropbox, $namespaceUsed) {
            // Tel hoeveel adressen erin zitten
            $adresResult = $dropbox->listFoldersInNamespace($namespaceUsed, $folder['path_display']);
            $count = collect($adresResult['entries'] ?? [])
                ->filter(fn($e) => ($e['.tag'] ?? null) === 'folder')
                ->count();

            return [
                'name'      => $folder['name'] . " ({$count})", // ← aantal mappen erbij
                'path'      => $folder['path_display'],
                'id'        => $folder['id'],
                'namespace' => $namespaceUsed,
                'count'     => $count, // ook apart meesturen
            ];
        })
        ->values();

    return response()->json($regios);
}





public function listAdressen(DropboxService $dropbox, Request $request)
{
    $namespaceId = $request->query('namespace_id');
    $path        = $request->query('path');
    $cursor      = $request->query('cursor');
    $search      = $request->query('search'); 

    if (!$namespaceId || (!$path && !$cursor)) {
        return response()->json(['error' => 'namespace_id + path of cursor verplicht'], 400);
    }

    // 🔍 Als er een zoekterm is → direct Dropbox search
    if ($search) {
        $adressen = $dropbox->searchFoldersInNamespace($namespaceId, $path, $search);

        return response()->json([
            'entries'  => $adressen,
            'cursor'   => null,
            'has_more' => false,
        ]);
    }

    // Anders: normale paginatie
    if ($cursor) {
        $result = $dropbox->listFoldersContinue($namespaceId, $cursor);
    } else {
        $result = $dropbox->listFoldersInNamespace($namespaceId, $path);
    }

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

public function createAdresFolder(Request $request, DropboxService $dropbox)
{
    $data = $request->validate([
        'namespace_id' => 'required|string',
        'path'         => 'required|string',  // regio-pad
        'adres'        => 'required|string|min:1|max:200'
    ]);

    try {
        $meta = $dropbox->createChildFolder($data['namespace_id'], $data['path'], $data['adres']);

        return response()->json([
            'success' => true,
            'message' => 'Map succesvol aangemaakt!',
            'folder'  => [
                'name'      => $meta['name'] ?? $data['adres'],
                'path'      => $meta['path_display'] ?? (rtrim($data['path'], '/') . '/' . $data['adres']),
                'namespace' => $data['namespace_id'],
            ],
        ], 201);

    } catch (\Throwable $e) {
        // 🔥 DEBUG MODE AAN
        return response()->json([
            'success' => false,
            'message' => 'Dropbox fout bij map maken.',
            'error'   => $e->getMessage(),      // exacte foutmelding
            'trace'   => $e->getTraceAsString() // volledige trace → zie in frontend
        ], 500);
    }
}


public function uploadPhoto(Request $request, DropboxService $dropbox, $taskId)
{
    $request->validate([
        'namespace_id' => 'required|string',
        'path'         => 'required|string',
        'photos'       => 'required|array|max:3',
        'photos.*'     => 'image|max:5120' // max 5MB per stuk
    ]);

    $task   = Task::findOrFail($taskId);

    // huidige foto's ophalen → omzetten naar array
    $photos = $task->photo ? explode(',', $task->photo) : [];

    foreach ($request->file('photos', []) as $file) {
        if (count($photos) >= 3) break; // nooit meer dan 3 in totaal

        $filename = uniqid() . '.' . $file->getClientOriginalExtension();

        // ✅ gebruik het path RELATIEF binnen de namespace
        $cleanPath = rtrim($request->path, '/') . '/' . $filename;

        // upload naar dropbox
        $upload = $dropbox->upload($request->namespace_id, $cleanPath, $file);

        $photos[] = $upload['path_display'] ?? $cleanPath;
    }

    // terug opslaan als string (kommagescheiden)
    $task->photo = implode(',', $photos);
    $task->save();

    return response()->json([
        'message' => 'Foto(s) succesvol geüpload',
        'files'   => $photos
    ]);
}


    
     public function listTeamMembers(DropboxService $dropbox)
    {
        try {
            $members = $dropbox->listTeamMembers();

            // Geef enkel de nuttige info door
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
}
