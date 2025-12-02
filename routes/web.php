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
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use App\Mail\RepairTasksMail;
use Illuminate\Support\Facades\Response;

Route::get('/3f73e6bc076b1cb056e072cc30dc485b.txt', function () {
    return Response::make('', 200, ['Content-Type' => 'text/plain']);
});
Route::get('/ping', function () {
    return response('pong', 200);
});

Route::middleware(['auth'])->group(function () {
    Route::post('/r2/presigned-url', function (Request $request) {
        $request->validate([
            'filename' => 'required|string|max:255',
            'folder'   => 'nullable|string|max:255',
        ]);

        // Optioneel: voeg een folder toe zoals "uploads/team_1"
        $folder = $request->input('folder', 'uploads');
        $path = trim($folder, '/') . '/' . uniqid() . '_' . $request->filename;

        // Maak een tijdelijke upload URL (5 minuten geldig)
        try {
            $url = Storage::disk('r2')->temporaryUploadUrl(
                $path,
                now()->addMinutes(5)
            );

            return response()->json([
                'success' => true,
                'upload_url' => $url,
                'path' => $path,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    })->name('r2.presigned');

 Route::post('/r2/upload-urls', function (Request $request) {
    $files = $request->input('files', []);

    $urls = collect($files)->map(function ($name) {
        $path = 'uploads/' . uniqid() . '_' . $name;

        try {
            $s3 = new S3Client([
                'region' => 'auto',
                'version' => 'latest',
                'endpoint' => env('R2_ENDPOINT'),
                'credentials' => [
                    'key' => env('R2_ACCESS_KEY_ID'),
                    'secret' => env('R2_SECRET_ACCESS_KEY'),
                ],
            ]);

            $cmd = $s3->getCommand('PutObject', [
                'Bucket' => env('R2_BUCKET'),
                'Key'    => $path,
                // ✅ Belangrijk: verwijder deze regel of vervang met 'public-read'
                //'ACL'    => 'public-read',
            ]);

            $request = $s3->createPresignedRequest($cmd, '+5 minutes');
            $tempUrl = (string) $request->getUri();

            Log::info("✅ Presigned R2 upload URL gegenereerd", [
                'file' => $name,
                'url'  => $tempUrl,
            ]);

            return [
                'name' => $name,
                'url'  => $tempUrl,
                'path' => $path,
            ];
        } catch (\Throwable $e) {
            Log::error("❌ R2 upload URL fout: " . $e->getMessage(), ['file' => $name]);
            return [
                'name'  => $name,
                'error' => $e->getMessage(),
            ];
        }
    });

    return response()->json(['urls' => $urls]);
})->name('r2.upload_urls');

});

Route::get('/r2/test', function () {
    $files = Storage::disk('r2')->files('uploads');
    return response()->json($files);
});

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

Route::get('/test-mail', function () {
    Mail::raw('Dit is een testmail vanuit Laravel via Outlook SMTP.', function ($message) {
        $message->to('jouwadres@outlook.com')
                ->subject('Testmail via Outlook SMTP');
    });

    return '✅ Testmail verzonden! Check je inbox.';
});

Route::get('/test-repair-mail', function () {
    $fakeTasks = collect([
        (object)['name' => 'Check fuse box at site 12', 'status' => 'needs_repair', 'deadline' => now()->addDays(2)],
        (object)['name' => 'Replace broken cable', 'status' => 'needs_repair', 'deadline' => now()->addDays(5)],
    ]);

    Mail::to('emreakkus003@gmail.com')
        ->send(new RepairTasksMail($fakeTasks));

    return '✅ RepairTasksMail sent successfully. Check your inbox.';
});

/*Route::get('/download-r2-backup', function () {
    $path = storage_path('app/r2_backup.zip');

    if (!file_exists($path)) {
        abort(404, "ZIP file not found");
    }

    return response()->download($path, 'r2_backup.zip');
}); */
