<?php

use Illuminate\Support\Facades\Broadcast;


Broadcast::channel('App.Models.Team.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
