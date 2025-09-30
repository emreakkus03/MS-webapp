<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Task;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function admin()
    {
        // Aantal actieve ploegen
        $activeTeams = Team::count();

        // Totaal aantal taken vandaag (alle statussen)
        $tasksToday = Task::whereDate('time', Carbon::today())->count();

        // ---- Periodes (volledige ranges) ----
        $now = Carbon::now();

        $dayStart   = $now->copy()->startOfDay();
        $dayEnd     = $now->copy()->endOfDay();

        $weekStart  = $now->copy()->startOfWeek();   // Maandag t/m zondag
        $weekEnd    = $now->copy()->endOfWeek();

        $monthStart = $now->copy()->startOfMonth();
        $monthEnd   = $now->copy()->endOfMonth();

        $yearStart  = $now->copy()->startOfYear();
        $yearEnd    = $now->copy()->endOfYear();

        // Dagelijks
        $tasksDailyFinished = Task::whereBetween('time', [$dayStart, $dayEnd])
            ->where('status', 'finished')->count();
        $tasksDailyOpen = Task::whereBetween('time', [$dayStart, $dayEnd])
            ->where('status', '!=', 'finished')->count();

        // Wekelijks (volledige week)
        $tasksWeeklyFinished = Task::whereBetween('time', [$weekStart, $weekEnd])
            ->where('status', 'finished')->count();
        $tasksWeeklyOpen = Task::whereBetween('time', [$weekStart, $weekEnd])
            ->where('status', '!=', 'finished')->count();

        // Maandelijks (volledige maand)
        $tasksMonthlyFinished = Task::whereBetween('time', [$monthStart, $monthEnd])
            ->where('status', 'finished')->count();
        $tasksMonthlyOpen = Task::whereBetween('time', [$monthStart, $monthEnd])
            ->where('status', '!=', 'finished')->count();

        // Jaarlijks (volledige jaar)
        $tasksYearlyFinished = Task::whereBetween('time', [$yearStart, $yearEnd])
            ->where('status', 'finished')->count();
        $tasksYearlyOpen = Task::whereBetween('time', [$yearStart, $yearEnd])
            ->where('status', '!=', 'finished')->count();

        return view('dashboard.admin', compact(
            'activeTeams',
            'tasksToday',
            'tasksDailyFinished', 'tasksDailyOpen',
            'tasksWeeklyFinished', 'tasksWeeklyOpen',
            'tasksMonthlyFinished', 'tasksMonthlyOpen',
            'tasksYearlyFinished', 'tasksYearlyOpen'
        ));
    }

    public function user()
    {
        $user = Auth::user();
        $today = Carbon::today();

        $tasksToday = Task::with('address')
            ->where('team_id', $user->id)
            ->whereDate('time', $today)
            ->orderBy('time')
            ->get();

        $tasksFinished = $tasksToday->where('status', 'finished')->count();
        $tasksTotal = $tasksToday->count();

        return view('dashboard.user', compact('tasksToday', 'tasksFinished', 'tasksTotal'));
    }
}
