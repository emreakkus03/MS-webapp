<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
     use HasFactory;

    protected $fillable = ['team_id', 'address_id', 'time', 'status', 'note', 'photo'];

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
