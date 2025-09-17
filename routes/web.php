<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TaskController;

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
    Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
});

Route::get('/teams/{id}/edit', [TeamController::class, 'edit'])->name('teams.edit');
Route::put('/teams/{id}', [TeamController::class, 'update'])->name('teams.update');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard/admin', [DashboardController::class, 'admin'])->name('dashboard.admin');
    Route::get('/dashboard/user', [DashboardController::class, 'user'])->name('dashboard.user');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule.index');
    Route::post('/schedule', [ScheduleController::class, 'store'])->name('schedule.store'); // ✅ alleen admin mag dit in controller
    Route::get('/schedule/tasks', [ScheduleController::class, 'tasks'])->name('schedule.tasks');
    Route::get('/schedule/tasks/{team}', [ScheduleController::class, 'getTasksByTeam'])->name('schedule.teamTasks');
    Route::get('/schedule/{task}/edit', [ScheduleController::class, 'edit'])->name('schedule.edit');
    Route::put('/schedule/{task}', [ScheduleController::class, 'update'])->name('schedule.update');
    Route::delete('/schedule/{task}', [ScheduleController::class, 'destroy'])->name('schedule.destroy');
    Route::get('/schedule/task-note', [ScheduleController::class, 'getTaskNoteByAddress']);
    Route::get('/schedule/check-time', [ScheduleController::class, 'checkTime']);
    Route::get('/schedule/address-details', [ScheduleController::class, 'getAddressDetails']);
});

Route::middleware(['auth'])->group(function () {
    // Taken overzicht
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks/{task}/finish', [TaskController::class, 'finish'])->name('tasks.finish');

    // Alleen admin mag een taak heropenen (PATCH)
    Route::patch('/tasks/{task}/reopen', [TaskController::class, 'reopen'])
        ->name('tasks.reopen');
    Route::get('/tasks/filter', [TaskController::class, 'filter'])->name('tasks.filter');

});
