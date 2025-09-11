<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = ['street', 'number', 'zipcode', 'city'];

    public function tasks()
    {
        return $this->hasMany(Task::class, 'address_id');
    }
}
