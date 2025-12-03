<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class R2PendingUpload extends Model
{
    protected $fillable = [
        'task_id',
        'r2_path',
        'namespace_id',
        'perceel',
        'regio_path',
        'adres_path',
        'target_dropbox_path',
        'status',
    ];
}

