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

        $request->validate([
            'time' => 'required|date',
            'team_id' => 'nullable|exists:teams,id',
            'address_name' => 'required|string|max:255',
            'address_number' => 'nullable|string|max:10',
            'address_zipcode' => 'nullable|string|max:20',
            'address_city' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:500',
        ]);

        // Adres ophalen of aanmaken
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

        // Note bepalen: als ingevuld gebruik dat, anders laatste note voor dat adres
        $note = $request->note;
        if (!$note) {
            $note = Task::where('address_id', $address->id)->latest()->value('note');
        }

        // Taak opslaan
        Task::create([
            'team_id' => $user->role === 'admin' && $request->filled('team_id')
                ? $request->team_id
                : $user->id,
            'address_id' => $address->id,
            'time' => $request->time,
            'status' => 'open',
            'note' => $note,
        ]);

        return redirect()->route('schedule.index')->with('success', 'Taak toegevoegd!');
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
        if ($user->role !== 'admin' && $task->team_id !== $user->id) abort(403);

        $teams = Team::orderBy('name')->get();
        $addresses = Address::orderBy('street')->get();

        return view('schedule.edit', compact('task', 'teams', 'addresses'));
    }

    public function update(Request $request, Task $task)
    {
        $user = Auth::user();
        if ($user->role !== 'admin' && $task->team_id !== $user->id) abort(403);

        $request->validate([
            'time' => 'required|date',
            'team_id' => 'nullable|exists:teams,id',
            'address_name' => 'required|string|max:255',
            'address_number' => 'nullable|string|max:10',
            'address_zipcode' => 'nullable|string|max:20',
            'address_city' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:500',
        ]);

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

        // Note bijwerken van de taak
        $task->update([
            'team_id' => $user->role === 'admin' && $request->filled('team_id')
                ? $request->team_id
                : $task->team_id,
            'address_id' => $address->id,
            'time' => $request->time,
            'note' => $request->note ?: $task->note,
        ]);

        return redirect()->route('schedule.index')->with('success', 'Taak bijgewerkt!');
    }

    public function destroy(Task $task)
    {
        $user = Auth::user();
        if ($user->role !== 'admin' && $task->team_id !== $user->id) abort(403);

        $task->delete();
        return redirect()->route('schedule.index')->with('success', 'Taak verwijderd!');
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
}
