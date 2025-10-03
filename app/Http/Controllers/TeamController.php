<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Team;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->guard()->user();

        if (!$user || $user->role !== 'admin') {
            abort(403);
        }

        $query = Team::query();

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $teams = $query->orderBy('name')->get();

        return view('teams.index', compact('teams'))
            ->with('filters', $request->only(['role', 'search']));
    }

    public function store(Request $request)
    {
        $team = auth()->guard()->user();
        abort_unless($team && $team->role === 'admin', 403);

        $request->validate([
            'name' => 'required|string|unique:teams,name',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,team', // ✅ whitelist
            'members' => 'nullable|string',
        ]);

        // 👉 input schoonmaken
        $teamName = ucfirst(strtolower(e(strip_tags($request->name))));
        $role = e(strip_tags($request->role));

        $members = null;
        if ($request->members) {
            $members = collect(explode(' ', $request->members))
                ->map(fn($name) => ucfirst(strtolower(e(strip_tags($name)))))
                ->implode(' ');
        }

        Team::create([
            'name' => $teamName,
            'password' => $request->password,
            'members' => $members,
        ])->role = $role; // ✅ role apart instellen

        return redirect()->back()->with('success', 'Nieuwe ploeg succesvol aangemaakt!');
    }

    public function edit($id)
    {
        $team = Team::findOrFail($id);

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
            'role' => 'required|in:admin,team', // ✅ whitelist
            'members' => 'nullable|string',
        ]);

        // 👉 input schoonmaken
        $team->name = ucfirst(strtolower(e(strip_tags($request->name))));
        $team->role = e(strip_tags($request->role));
        $team->members = $request->members
            ? collect(explode(' ', $request->members))
                ->map(fn($name) => ucfirst(strtolower(e(strip_tags($name)))))
                ->implode(' ')
            : null;

        if (!empty($request->password)) {
            $team->password = $request->password;
        }

        $team->save();

        return redirect()->route('teams.index')->with('success', 'Ploeg succesvol bijgewerkt!');
    }

    public function destroy(Team $team)
    {
        $user = auth()->guard()->user();

        if (!$user || $user->role !== 'admin') {
            abort(403);
        }

        $team->delete();

        return redirect()->back()->with('success', 'Team succesvol verwijderd!');
    }
}
