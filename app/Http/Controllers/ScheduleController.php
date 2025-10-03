<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Task;
use App\Models\Team;
use App\Models\Address;
use Illuminate\Support\Facades\Auth;

class ScheduleController extends Controller
{
    public function index(Request $request)
{
    $user = Auth::user();

    $teams = Team::orderBy('name')->get();
    $addresses = Address::orderBy('street')->get();

    $defaultTeamId = $request->query('team_id');

    if ($user->role === 'admin') {
    if (!$defaultTeamId && $teams->count() > 0) {
        $defaultTeamId = $teams->first()->id;
    }

    $tasks = Task::with('address')
        ->when($defaultTeamId, fn($query, $teamId) => $query->where('team_id', $teamId))
        ->get();

    $tasks->transform(function ($task) {
        $task->current_photos = $task->photo ? explode(',', $task->photo) : [];
        $task->previous_photos = [];
        $task->current_note = $task->note ?? '';
        $task->previous_notes = [];
        return $task;
    });
} else {
    $tasks = Task::with('address')->where('team_id', $user->id)->get();
    $team = Team::find($user->id);

    foreach ($tasks as $task) {
        $task->current_photos = $task->photo ? explode(',', $task->photo) : [];
        $task->current_note = $task->note ?? '';

        if ($team && in_array($team->name, ['Herstelploeg 1', 'Herstelploeg 2'])) {
            $previousPhotos = Task::where('address_id', $task->address_id)
                ->where('id', '<', $task->id)
                ->whereNotNull('photo')
                ->pluck('photo')
                ->toArray();

            $previousPhotos = collect($previousPhotos)
                ->flatMap(fn($p) => explode(',', $p))
                ->toArray();

            $task->previous_photos = $previousPhotos;

            // 👉 oude notities ophalen
            $previousNotes = Task::where('address_id', $task->address_id)
                ->where('id', '<', $task->id)
                ->whereNotNull('note')
                ->pluck('note')
                ->toArray();

            $task->previous_notes = $previousNotes;
        } else {
            $task->previous_photos = [];
            $task->previous_notes = [];
        }
    }

}


    return view('schedule.index', compact('tasks', 'teams', 'addresses', 'defaultTeamId'));
}

    

 public function store(Request $request)
{
    $user = Auth::user();

    // ✅ Alleen admins mogen taken inplannen
    if ($user->role !== 'admin') {
        abort(403, 'Alleen admins kunnen taken inplannen.');
    }

    $request->validate([
        'time' => 'required|date',
        'team_id' => 'required|exists:teams,id', // verplicht bij admin
        'address_name' => 'required|string|max:255',
        'address_number' => 'nullable|string|max:10',
        'address_zipcode' => 'nullable|string|max:20',
        'address_city' => 'nullable|string|max:100',
        'note' => 'nullable|string|max:500',
    ]);

    // ✅ Check dubbele taken per team
    $existingTask = Task::where('time', $request->time)
        ->where('team_id', $request->team_id)
        ->first();

    if ($existingTask) {
        return redirect()->back()
            ->withErrors(['time' => 'Er is al een taak ingepland op dit tijdstip voor dit team!'])
            ->withInput();
    }

    $address = Address::firstOrCreate(
        [
            'street' => strip_tags(ucfirst(strtolower(trim($request->address_name)))),
            'number' => strip_tags(trim($request->address_number)),
        ],
        [
            'zipcode' => strip_tags(trim($request->address_zipcode)),
            'city'    => strip_tags(ucfirst(strtolower(trim($request->address_city)))),
        ]
    );

    $note = $request->note ? strip_tags(trim($request->note)) : null;
    if (!$note) {
        $note = Task::where('address_id', $address->id)->latest()->value('note');
    }

    // Kijk of er al een taak voor dit adres bestaat
    $lastTask = Task::where('address_id', $address->id)->latest()->first();
    $status = $lastTask ? $lastTask->status : 'open';

    Task::create([
        'team_id'    => $request->team_id, // 👈 altijd vanuit formulier
        'address_id' => $address->id,
        'time'       => $request->time,
        'status'     => $status,
        'note'       => $note,
    ]);

    // ✅ Redirect naar dezelfde ploeg
    return redirect()
        ->route('schedule.index', ['team_id' => $request->team_id])
        ->with('success', 'Taak toegevoegd!');
}



    public function checkTime(Request $request)
    {
        $time = $request->query('time');
        $teamId = $request->query('team_id');

        if (!$time || !$teamId) {
            return response()->json(['exists' => false]);
        }

        $date = \Carbon\Carbon::parse($time)->toDateString();

        $exists = Task::where('team_id', $teamId)
            ->whereDate('time', $date)
            ->whereTime('time', \Carbon\Carbon::parse($time)->format('H:i:s'))
            ->exists();

        return response()->json(['exists' => $exists]);
    }

public function getTasksByTeam($teamId)
{
    $user = Auth::user();

    $calendarColors = [
        'open' => '#9CA3AF', // grijs
        'in behandeling' => '#FACC15', // geel
        'finished' => '#22C55E', // groen
        'reopened' => '#EF4444', // rood
    ];

    $statusClasses = [
        'open' => 'bg-gray-200 text-gray-800',
        'in behandeling' => 'bg-yellow-200 text-yellow-800',
        'finished' => 'bg-green-200 text-green-800',
        'reopened' => 'bg-red-200 text-red-800',
    ];

    if ($user->role === 'admin') {
        $tasks = Task::with('address', 'team')->where('team_id', $teamId)->get();
    } else {
        if ($teamId != $user->id) {
            abort(403);
        }
        $tasks = Task::with('address', 'team')->where('team_id', $user->id)->get();
    }

    $events = $tasks->map(function ($task) use ($calendarColors, $statusClasses) {
        // Huidige foto's & notitie
        $currentPhotos = $task->photo ? explode(',', $task->photo) : [];
        $currentNote   = $task->note ?? '';

        // Default leeg
        $previousPhotos = [];
        $previousNotes  = [];

        // 🔥 Als team een herstelploeg is → haal oude data op
        if ($task->team && in_array($task->team->name, ['Herstelploeg 1', 'Herstelploeg 2'])) {
            $previousPhotos = Task::where('address_id', $task->address_id)
                ->where('id', '<', $task->id)
                ->whereNotNull('photo')
                ->pluck('photo')
                ->flatMap(fn($p) => explode(',', $p))
                ->toArray();

            $previousNotes = Task::where('address_id', $task->address_id)
                ->where('id', '<', $task->id)
                ->whereNotNull('note')
                ->pluck('note')
                ->toArray();
        }

        return [
            'id' => $task->id,
            'title' => $task->address->street . ' ' . ($task->address->number ?? ''),
            'start' => $task->time,
            'color' => $calendarColors[$task->status] ?? '#9CA3AF',
            'extendedProps' => [
                'time'            => \Carbon\Carbon::parse($task->time)->format('H:i'),
                'address_name'    => $task->address->street,
                'address_number'  => $task->address->number ?? '',
                'zipcode'         => $task->address->zipcode ?? '',
                'city'            => $task->address->city ?? '',
                'status'          => $task->status,
                'statusColor'     => $statusClasses[$task->status] ?? 'bg-gray-200 text-gray-800',
                'note'            => $task->note ?? '',
                'current_note'    => $currentNote,
                'previous_notes'  => $previousNotes,
                'photos'          => $currentPhotos,
                'current_photos'  => $currentPhotos,
                'previous_photos' => $previousPhotos,
                'team_id'         => $task->team_id,
                'team_name'       => $task->team->name ?? '',
            ]
        ];
    });

    return response()->json($events);
}





    public function edit(Task $task)
{
    $user = Auth::user();
    if ($user->role !== 'admin') abort(403);

    $teams = Team::orderBy('name')->get();
    $addresses = Address::orderBy('street')->get();

       $redirect = request('redirect', url()->previous());

    return view('schedule.edit', compact('task', 'teams', 'addresses', 'redirect'));

}

  public function update(Request $request, Task $task)
{
    $user = Auth::user();
    if ($user->role !== 'admin') abort(403);

    $request->validate([
        'time' => 'required|date',
        'team_id' => 'nullable|exists:teams,id',
        'address_name' => 'required|string|max:255',
        'address_number' => 'nullable|string|max:10',
        'address_zipcode' => 'nullable|string|max:20',
        'address_city' => 'nullable|string|max:100',
        'note' => 'nullable|string|max:500',
    ]);

    $existingTask = Task::where('time', $request->time)
        ->where('id', '!=', $task->id)
        ->first();

    if ($existingTask) {
        return redirect()->back()
            ->withErrors(['time' => 'Er is al een andere taak ingepland op dit tijdstip!'])
            ->withInput();
    }

    $address = Address::firstOrCreate(
        [
            'street' => strip_tags(ucfirst(strtolower(trim($request->address_name)))),
            'number' => strip_tags(trim($request->address_number)),
        ],
        [
            'zipcode' => strip_tags(trim($request->address_zipcode)),
            'city'    => strip_tags(ucfirst(strtolower(trim($request->address_city)))),
        ]
    );

    $task->update([
        'team_id'    => $user->role === 'admin' && $request->filled('team_id')
            ? $request->team_id
            : $task->team_id,
        'address_id' => $address->id,
        'time'       => $request->time,
        'note'       => $request->note ? strip_tags(trim($request->note)) : $task->note,
    ]);

    // 👇 check of redirect_to bestaat (tasks), anders ga je terug naar schedule
    $redirect = $request->input('redirect_to', route('schedule.index'));

    return redirect($redirect)->with('success', 'Taak bijgewerkt!');
}


    public function destroy(Task $task)
    {
        $user = Auth::user();
        if ($user->role !== 'admin') abort(403);

        $task->delete();
        return redirect()->back()->with('success', 'Taak verwijderd!');
    }

    public function getTaskNoteByAddress(Request $request)
    {
        $street = $request->query('street');
        $number = $request->query('number');

        $address = Address::where('street', ucfirst(strtolower($street)))
            ->where('number', $number)
            ->first();

        if (!$address) return response()->json(['note' => '']);

        $note = Task::where('address_id', $address->id)->latest()->value('note');
        return response()->json(['note' => $note ?? '']);
    }

    public function getAddressDetails(Request $request)
    {
        $street = $request->query('street');

        $address = Address::where('street', ucfirst(strtolower($street)))->first();
        if (!$address) return response()->json(['address' => null, 'note' => null]);

        $note = Task::where('address_id', $address->id)->latest()->value('note');

        return response()->json([
            'address' => [
                'number' => $address->number,
                'zipcode' => $address->zipcode,
                'city' => ucfirst(strtolower($address->city)),
            ],
            'note' => $note,
        ]);
    }

    public function addressSuggest(Request $request)
{
    $query = $request->query('query', '');
    if (strlen($query) < 2) {
        return response()->json([]);
    }

    $addresses = Address::where('street', 'like', "%{$query}%")
        ->limit(10)
        ->get();

    $addresses->transform(function ($a) {
        $a->note = Task::where('address_id', $a->id)->latest()->value('note');
        return $a;
    });

    return response()->json($addresses);
}

}
