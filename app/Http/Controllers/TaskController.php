<?php 

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use App\Services\DropboxService;
use App\Notifications\TaskCompletedNotification;
use App\Models\Team; 
use Illuminate\Support\Facades\Log;

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
        'namespace_id' => 'required|string',
        'path'         => 'required|string',
        'photos'       => 'required|array|max:10',
        'photos.*'     => 'image|mimes:jpeg,png,jpg|max:5120'
    ]);

    $task   = Task::findOrFail($taskId);
    $photos = $task->photo ? explode(',', $task->photo) : [];

    foreach ($request->file('photos', []) as $file) {
        if (count($photos) >= 10) break;

        $filename = uniqid() . '.' . $file->getClientOriginalExtension();

        // ✅ het gekozen adrespad waar de foto’s direct in moeten komen
        $basePath = rtrim($request->path, '/');

        // ✅ uploadpad voor Dropbox (zonder dubbele map)
        if ($request->namespace_id !== $dropbox->getFluviusNamespaceId()) {
            // Perceel 1 → relatief
            $uploadPath = '/' . ltrim(preg_replace('/^\/?Perceel 1/i', '', $basePath . '/' . $filename), '/');
            $dbPath     = '/PERCEEL 1' . $basePath . '/' . $filename;
        } else {
    // Perceel 2 → absoluut
    $uploadPath = $basePath . '/' . $filename;
    $dbPath     = $basePath . '/' . $filename; 
}

        // 🔹 upload naar Dropbox
        $dropbox->upload($request->namespace_id, $uploadPath, $file);

        // 🔹 altijd nette absolute path in database opslaan
        $photos[] = $dbPath;
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
