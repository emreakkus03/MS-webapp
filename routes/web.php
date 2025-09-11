<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
// use App\Http\Controllers\Auth\TeamController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\DashboardController;

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/signin', function () {
    return view('signin.signin');
});

Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
Route::post('/teams', [TeamController::class, 'store']);
Route::middleware(['auth'])->group(function () {
    Route::delete('/teams/{team}', [TeamController::class, 'destroy'])
        ->name('teams.destroy');
});

Route::get('/teams/{id}/edit', [TeamController::class, 'edit'])->name('teams.edit');
Route::put('/teams/{id}', [TeamController::class, 'update'])->name('teams.update');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard/admin', [DashboardController::class, 'admin'])->name('dashboard.admin');
    Route::get('/dashboard/user', [DashboardController::class, 'user'])->name('dashboard.user');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule.index');
    Route::post('/schedule', [ScheduleController::class, 'store'])->name('schedule.store');
    Route::get('/schedule/tasks', [ScheduleController::class, 'tasks'])->name('schedule.tasks');
    Route::get('/schedule/tasks/{team}', [ScheduleController::class, 'getTasksByTeam'])->name('schedule.teamTasks');
});