<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Contracts\Activity;

class Task extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['team_id', 'address_id', 'time', 'status', 'note', 'photo'];
    protected $casts = [
    'time' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
    public function tapActivity(Activity $activity, string $eventName)
    {
        // Alleen als we aan het verwijderen zijn ('deleted')
        if ($eventName === 'deleted') {

            // Probeer het adres op te halen
            $addr = $this->address;

            if ($addr) {
                $activity->properties = $activity->properties->merge([
                    'archived_address_text' => $addr->street . ' ' . $addr->number . ', ' . $addr->city
                ]);
            }
        }
    }
    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function address()
    {
        return $this->belongsTo(Address::class, 'address_id');
    }
    public function getPhotoArrayAttribute()
    {
        return $this->photo ? explode(',', $this->photo) : [];
    }
}
