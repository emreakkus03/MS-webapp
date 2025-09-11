<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DashboardController extends Controller
{
    // Admin dashboard
    public function admin()
    {
        // Admin ziet alle taken (of eventueel van vandaag, afhankelijk van je wens)
        $tasks = Task::with('address', 'team')
            ->orderBy('time')
            ->get();

        return view('dashboard.admin', compact('tasks'));
    }

    // User dashboard
    public function user()
    {
        $user = Auth::user();
        $today = Carbon::today();

        // Alleen de taken van vandaag voor deze user
        $tasksToday = Task::with('address')
            ->where('team_id', $user->id)
            ->whereDate('time', $today)
            ->orderBy('time')
            ->get();

        return view('dashboard.user', compact('tasksToday'));
    }
}
