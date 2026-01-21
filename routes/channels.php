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

Broadcast::channel('warehouseman-orders', function ($user) {
    return $user->role === 'warehouseman';
});

Broadcast::channel('teams-order-ready.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// 🔐 Persoonlijk kanaal per team/user
Broadcast::channel('App.Models.Team.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

