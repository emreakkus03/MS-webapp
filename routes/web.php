<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\LeaveRequestController;
use Illuminate\Support\Facades\Auth;
use App\Events\TestEvent;
use Illuminate\Notifications\DatabaseNotification;

Route::middleware('auth')->get('/fire-test', function () {
    broadcast(new TestEvent('Hallo vanuit Laravel Reverb!'));
    return "Event fired!";
});


Route::post('/notifications/clear', function () {
    Auth::user()->unreadNotifications->markAsRead();
    return back();
})->name('notifications.clear');

Route::post('/notifications/delete', function () {
    $user = Auth::user();

    if ($user->role !== 'admin') {
        abort(403, 'Geen toegang om notificaties te verwijderen.');
    }

    $user->notifications()->delete();

    return back()->with('success', 'Alle notificaties verwijderd.');
})->name('notifications.delete');

Route::delete('/notifications/{id}', function ($id) {
    $user = Auth::user();

    // Alleen admin mag notificaties verwijderen
    if ($user->role !== 'admin') {
        abort(403, 'Geen toegang om notificaties te verwijderen.');
    }

    $notification = DatabaseNotification::find($id);
    if ($notification && $notification->notifiable_id === $user->id) {
        $notification->delete();
        return back()->with('success', 'Notificatie verwijderd.');
    }

    return back()->with('error', 'Notificatie niet gevonden of niet van jou.');
})->name('notifications.destroy'); 

Route::get('/', function () {
    // Als de gebruiker al ingelogd is, stuur hem naar de juiste dashboard
    if (Auth::check()) {
        return Auth::user()->role === 'admin'
            ? redirect()->route('dashboard.admin')
            : redirect()->route('dashboard.user');
    }

    // Anders naar de loginpagina
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/signin', fn() => view('signin.signin'));
});
Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    });

Route::middleware(['auth'])->group(function () {
    Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::post('/teams', [TeamController::class, 'store']);
    Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
    Route::get('/teams/{id}/edit', [TeamController::class, 'edit'])->name('teams.edit');
    Route::put('/teams/{id}', [TeamController::class, 'update'])->name('teams.update');
});


//
Route::middleware('auth')->group(function () {
    Route::get('/dashboard/admin', [DashboardController::class, 'admin'])->name('dashboard.admin');
    Route::get('/dashboard/user', [DashboardController::class, 'user'])->name('dashboard.user');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule.index');
    Route::post('/schedule', [ScheduleController::class, 'store'])->name('schedule.store');
    //Route::get('/schedule/tasks', [ScheduleController::class, 'tasks'])->name('schedule.tasks');
    Route::get('/schedule/tasks/{team}', [ScheduleController::class, 'getTasksByTeam'])->name('schedule.teamTasks');
    Route::get('/schedule/{task}/edit', [ScheduleController::class, 'edit'])->name('schedule.edit');
    Route::put('/schedule/{task}', [ScheduleController::class, 'update'])->name('schedule.update');
    Route::delete('/schedule/{task}', [ScheduleController::class, 'destroy'])->name('schedule.destroy');
    Route::get('/schedule/task-note', [ScheduleController::class, 'getTaskNoteByAddress']);
    Route::get('/schedule/check-time', [ScheduleController::class, 'checkTime']);
    Route::get('/schedule/address-details', [ScheduleController::class, 'getAddressDetails']);
    Route::get('/schedule/address-suggest', [ScheduleController::class, 'addressSuggest']);
});

Route::middleware(['auth'])->group(function () {
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks/{task}/finish', [TaskController::class, 'finish'])->name('tasks.finish');
    Route::patch('/tasks/{task}/reopen', [TaskController::class, 'reopen'])->name('tasks.reopen');
    Route::get('/tasks/filter', [TaskController::class, 'filter'])->name('tasks.filter');

    // 🔹 Cascade API voor Dropbox
    Route::get('/dropbox/percelen', [TaskController::class, 'listPercelen']);
    Route::get('/dropbox/regios', [TaskController::class, 'listRegios']);
    Route::get('/dropbox/adressen', [TaskController::class, 'listAdressen']);

    Route::get('/dropbox/members', [TaskController::class, 'listTeamMembers']);
    Route::get('/dropbox/preview', [TaskController::class, 'previewPhoto']);

Route::get('/dropbox/create-adres', fn() => abort(404));
    Route::post('/dropbox/create-adres', [TaskController::class, 'createAdresFolder'])->name('dropbox.create_adres');
    Route::post('/dropbox/upload-adres-photos', [TaskController::class, 'uploadAdresPhotos']);
    Route::post('/tasks/{id}/upload-photo', [TaskController::class, 'uploadPhoto']);
    Route::post('/dropbox/start-session', [TaskController::class, 'startDropboxSession'])
    ->name('dropbox.start_session');
   Route::post('/tasks/{task}/upload-temp', [TaskController::class, 'uploadTemp'])->name('tasks.uploadTemp');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/leaves', [LeaveRequestController::class, 'index'])->name('leaves.index');
    Route::get('/leaves/create', [LeaveRequestController::class, 'create'])->name('leaves.create');
    Route::post('/leaves', [LeaveRequestController::class, 'store'])->name('leaves.store');
    Route::post('/leaves/{id}/status', [LeaveRequestController::class, 'updateStatus'])->name('leaves.status');
     Route::get('/leaves/{id}/edit', [LeaveRequestController::class, 'edit'])->name('leaves.edit');
     Route::put('/leaves/{id}', [LeaveRequestController::class, 'update'])->name('leaves.update');
    Route::delete('/leaves/{id}', [LeaveRequestController::class, 'destroy'])->name('leaves.destroy');
});