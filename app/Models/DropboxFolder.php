<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DropboxFolder extends Model
{
    protected $guarded = [];

    // Relatie: Haal de submappen op van deze map
    public function children()
    {
        return $this->hasMany(DropboxFolder::class, 'parent_path', 'path_display');
    }
}