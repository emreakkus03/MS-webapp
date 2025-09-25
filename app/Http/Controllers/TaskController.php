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
            ->filter(fn($entry) =>
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

    // 1) Bepaal namespace + basispad voor de zoekopdracht
    if ($type === 'namespace') {
        // Perceel 1 → eigen namespace; zoek vanaf root ("")
        $namespaceUsed = $id;
        $basePath = "";
    } else {
        // Perceel 2 → folder binnen Fluvius namespace; zoek vanaf die folder
        $namespaceUsed = $dropbox->getFluviusNamespaceId();
        $basePath = $id; // dit is bv. "/Fluvius/.../Perceel 2"
    }

    // 2) Zoek specifiek naar de map "Webapp uploads" (case-insensitive)
    $matches = $dropbox->searchFoldersInNamespace($namespaceUsed, $basePath, 'Webapp uploads');

    // filter op exacte naam (case-insensitive), voor het geval search meerdere dingen teruggeeft
    $webappFolders = collect($matches)->filter(function ($m) {
        return strtolower(trim($m['name'])) === 'webapp uploads';
    });

    // Fallback: als search niks teruggeeft, probeer de directe kinderen (voor het geval de map wél in de root zit).
    if ($webappFolders->isEmpty()) {
        $list = $dropbox->listFoldersInNamespace($namespaceUsed, $basePath);
        $webappFolders = collect($list['entries'] ?? [])->filter(function ($e) {
            return ($e['.tag'] ?? null) === 'folder' && strtolower(trim($e['name'])) === 'webapp uploads';
        })->map(function ($e) use ($namespaceUsed) {
            return [
                'name'      => $e['name'],
                'path'      => $e['path_display'] ?? null,
                'id'        => $e['id'] ?? null,
                'namespace' => $namespaceUsed,
                'tag'       => $e['.tag'] ?? 'folder',
            ];
        });
    }

    // 3) Toon alleen de (eerste) Webapp uploads map als "regio"
    $regios = $webappFolders->take(1)->map(function ($folder) use ($dropbox, $namespaceUsed) {
        // Tel hoeveel adres-mappen erin zitten
        $adresResult = $dropbox->listFoldersInNamespace($namespaceUsed, $folder['path']);
        $count = collect($adresResult['entries'] ?? [])
            ->filter(fn($e) => ($e['.tag'] ?? null) === 'folder')
            ->count();

        return [
            'name'      => $folder['name'] . " ({$count})",
            'path'      => $folder['path'],
            'id'        => $folder['id'],
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
        $data = $request->validate([
            'namespace_id' => 'required|string',
            'path'         => 'required|string',  
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
            return response()->json([
                'success' => false,
                'message' => 'Dropbox fout bij map maken.',
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ], 500);
        }
    }

  public function uploadPhoto(Request $request, DropboxService $dropbox, $taskId)
{
    $request->validate([
        'namespace_id' => 'required|string',
        'path'         => 'required|string',
        'photos'       => 'required|array|max:3',
        'photos.*'     => 'image|max:5120'
    ]);

    $task   = Task::findOrFail($taskId);
    $photos = $task->photo ? explode(',', $task->photo) : [];

    foreach ($request->file('photos', []) as $file) {
        if (count($photos) >= 3) break;

        $filename = uniqid() . '.' . $file->getClientOriginalExtension();
        $fullPath = $request->path;

        // ✅ Voeg perceel toe als die ontbreekt
        if (!preg_match('/^\/Perceel\s?[0-9]/i', $fullPath)) {
            $perceelName = $request->input('perceel_name');
            if ($perceelName) {
                $fullPath = '/' . $perceelName . $fullPath;
            }
        }

        $cleanPath = rtrim($fullPath, '/') . '/' . $filename;

        // ✅ Correct pad bepalen voor Dropbox upload
        $uploadPath = $cleanPath;

        // Als het een namespace is (dus Perceel 1), pad relativeren
        if ($request->namespace_id !== $dropbox->getFluviusNamespaceId()) {
            $uploadPath = preg_replace('/^\/Perceel 1/i', '', $cleanPath);
        }

        // Upload naar Dropbox
        $upload = $dropbox->upload($request->namespace_id, $uploadPath, $file);

        // ✅ In DB wel altijd het absolute pad opslaan
        $photos[] = $cleanPath;
    }

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
            'namespace_id'=> 'nullable|string', 
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
}
