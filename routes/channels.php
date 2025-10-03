<?php

use Illuminate\Support\Facades\Broadcast;

// 🔐 Kanaal voor alle admins
Broadcast::channel('App.Models.Team.admins', function ($user) {
    return $user->role === 'admin';
});

// 🔐 Kanaal voor admin taken
Broadcast::channel('admin-tasks', function ($user) {
    return $user->role === 'admin';
});

// 🔐 Persoonlijk kanaal per team/user
Broadcast::channel('App.Models.Team.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});
