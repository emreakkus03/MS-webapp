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
        } else {
            $tasks = Task::with('address')->where('team_id', $user->id)->get();
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
            'street' => ucfirst(strtolower($request->address_name)),
            'number' => $request->address_number
        ],
        [
            'zipcode' => $request->address_zipcode,
            'city' => ucfirst(strtolower($request->address_city)),
        ]
    );

    $note = $request->note;
    if (!$note) {
        $note = Task::where('address_id', $address->id)->latest()->value('note');
    }

    // Kijk of er al een taak voor dit adres bestaat
    $lastTask = Task::where('address_id', $address->id)->latest()->first();
    $status = $lastTask ? $lastTask->status : 'open';

    Task::create([
        'team_id' => $request->team_id, // 👈 altijd vanuit formulier
        'address_id' => $address->id,
        'time' => $request->time,
        'status' => $status,
        'note' => $note,
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
        if (Auth::user()->role !== 'admin') abort(403);

        $tasks = Task::with('address')->where('team_id', $teamId)->get();

        $events = $tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'title' => $task->address->street . ' ' . ($task->address->number ?? ''),
                'start' => $task->time,
                'color' => 'blue',
                'extendedProps' => [
                    'time' => \Carbon\Carbon::parse($task->time)->format('H:i'),
                    'address_name' => $task->address->street,
                    'address_number' => $task->address->number ?? '',
                    'zipcode' => $task->address->zipcode ?? '',
                    'city' => $task->address->city ?? '',
                    'note' => $task->note ?? '',
                    'team_id' => $task->team_id
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
            'street' => ucfirst(strtolower($request->address_name)),
            'number' => $request->address_number
        ],
        [
            'zipcode' => $request->address_zipcode,
            'city' => ucfirst(strtolower($request->address_city)),
        ]
    );

    $task->update([
        'team_id' => $user->role === 'admin' && $request->filled('team_id')
            ? $request->team_id
            : $task->team_id,
        'address_id' => $address->id,
        'time' => $request->time,
        'note' => $request->note ?: $task->note,
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
}
