<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Team extends Authenticatable
{
    use Notifiable;

    protected $table = 'teams';

    protected $fillable = [
        'name',
        'password',
        'role',
        'members',
    ];

    protected $hidden = [
        'password',
    ];

    // Wachtwoord automatisch hashen
    public function setPasswordAttribute($password)
    {
        $this->attributes['password'] = bcrypt($password);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'team_id');
    }
}
