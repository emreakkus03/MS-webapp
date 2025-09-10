<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
// use App\Http\Controllers\Auth\TeamController;
use App\Http\Controllers\TeamController;

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::get('/', function () {
    return view('welcome');
});

Route::get('/signin', function () {
    return view('signin.singin');
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
    Route::get('/', function () {
        return view('welcome');
    })->name('welcome');
});