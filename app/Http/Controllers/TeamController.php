<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Team;
use Illuminate\Support\Facades\Hash;


class TeamController extends Controller
{
    // Constructor om eventueel middleware toe te voegen
    public function __construct()
    {
        // Als je wilt dat alleen ingelogde admins toegang hebben
        // kun je hier middleware toevoegen (optioneel als je route al checkt)
        // $this->middleware('auth');
    }

    // Toon de ploegenlijst
    public function index()
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->guard()->user();

        if (!$user || $user->role !== 'admin') {
            abort(403); // of redirect('/');
        }

        $teams = Team::all();
        return view('teams.index', compact('teams'));
    }

    public function store(Request $request)
    {
        /** @var \App\Models\User|null $team */
        $team = auth()->guard()->user();
        abort_unless($team && $team->role === 'admin', 403);

        // Validatie
        $request->validate([
            'name' => 'required|string|unique:teams,name',
            'password' => 'required|string|min:6',
            'role' => 'required|string',
            'members' => 'nullable|string',
        ]);

        // Nieuwe team aanmaken
        Team::create([
            'name' => $request->name,
            'password' => $request->password,
            'role' => $request->role,
            'members' => $request->members,
        ]);

        return redirect()->back()->with('success', 'Nieuwe ploeg succesvol aangemaakt!');
    }

    public function edit($id)
    {
        $team = Team::findOrFail($id);

        // Alleen admin mag editen
        $user = auth()->guard()->user();
        abort_unless($user && $user->role === 'admin', 403);

        return view('teams.edit', compact('team'));
    }

    public function update(Request $request, $id)
    {
        $team = Team::findOrFail($id);

        $user = auth()->guard()->user();
        abort_unless($user && $user->role === 'admin', 403);

        $request->validate([
            'name' => 'required|string|unique:teams,name,' . $team->id,
            'password' => 'nullable|string|min:6',
            'role' => 'required|string',
            'members' => 'nullable|string',
        ]);

        $team->name = $request->name;
        $team->role = $request->role;
        $team->members = $request->members;

        // Alleen updaten als er een nieuw wachtwoord is ingevuld
        if (!empty($request->password)) {
            $team->password = $request->password; // automatisch gehashed door mutator
        }

        $team->save();

        return redirect()->route('teams.index')->with('success', 'Ploeg succesvol bijgewerkt!');
    }


    public function destroy(Team $team)
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->guard()->user();

        // Alleen admins mogen teams verwijderen
        if (!$user || $user->role !== 'admin') {
            abort(403);
        }

        $team->delete();

        return redirect()->back()->with('success', 'Team succesvol verwijderd!');
    }
}
