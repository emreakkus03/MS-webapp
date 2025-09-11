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

        // Teams (alleen voor admin)
        $teams = Team::orderBy('name')->get();

        // Adressen voor autocomplete
        $addresses = Address::orderBy('street')->get();

        // Bepaal standaard team voor admin
        $defaultTeamId = $request->query('team_id'); // uit URL parameter
        if ($user->role === 'admin') {
            if (!$defaultTeamId && $teams->count() > 0) {
                $defaultTeamId = $teams->first()->id; // eerste team als default
            }

            // Taken ophalen van het geselecteerde team
            $tasks = Task::with('address')
                ->when($defaultTeamId, function ($query, $teamId) {
                    $query->where('team_id', $teamId);
                })
                ->get();
        } else {
            // Normale user: eigen taken
            $tasks = Task::with('address')->where('team_id', $user->id)->get();
        }

        return view('schedule.index', compact('tasks', 'teams', 'addresses', 'defaultTeamId'));
    }


    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'time' => 'required|date',
            'team_id' => 'nullable|exists:teams,id', // alleen admin
            'address_name' => 'required|string|max:255',
            'address_number' => 'nullable|string|max:10',
            'address_zipcode' => 'nullable|string|max:20',
            'address_city' => 'nullable|string|max:100',
        ]);

        // Adres ophalen of aanmaken
        $address = Address::where('street', ucfirst(strtolower($request->address_name)))
            ->where('number', $request->address_number)
            ->first();

        if (!$address) {
            $address = Address::create([
                'street' => ucfirst(strtolower($request->address_name)),
                'number' => $request->address_number,
                'zipcode' => $request->address_zipcode,
                'city' => ucfirst(strtolower($request->address_city)),
            ]);
        }


        // Taak opslaan
        Task::create([
            'team_id' => $user->role === 'admin' && $request->filled('team_id')
                ? $request->team_id
                : $user->id,
            'address_id' => $address->id,
            'time' => $request->time,
            'status' => 'open',
        ]);

        return redirect()->route('schedule.index')->with('success', 'Taak toegevoegd!');
    }
    public function getTasksByTeam($teamId)
    {
        // Alleen admin mag dit
        if (Auth::user()->role !== 'admin') {
            abort(403);
        }

        $tasks = Task::with('address')
            ->where('team_id', $teamId)
            ->get();

        // Zet taken om naar JSON in FullCalendar formaat
        $events = $tasks->map(function ($task) {
            return [
                'title' => $task->address->street . ' ' . ($task->address->number ?? ''),
                'start' => $task->time,
                'color' => 'blue',
                'extendedProps' => [
                    'time' => \Carbon\Carbon::parse($task->time)->format('H:i'),
                    'address_name' => $task->address->street,
                    'address_number' => $task->address->number ?? '',
                    'zipcode' => $task->address->zipcode ?? '',
                    'city' => $task->address->city ?? '',
                ]
            ];
        });

        return response()->json($events);
    }
}
