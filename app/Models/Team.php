<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Team extends Authenticatable
{
    use Notifiable, LogsActivity;

    protected $table = 'teams';

    protected $fillable = [
        'name',
        'password',
        'members',
        'employee_number',
        'subcontractor',
    ];

    protected $hidden = [
        'password',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            // We loggen alleen als deze velden veranderen
            ->logOnly(['name', 'role', 'members']) 
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // Wachtwoord automatisch hashen
    public function setPasswordAttribute($password)
    {
        $this->attributes['password'] = bcrypt($password);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'team_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
