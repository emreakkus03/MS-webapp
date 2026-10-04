<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class R2PendingUpload extends Model
{
    protected $casts = ['confirmed_at' => 'datetime', 'completed_at' => 'datetime', 'cleaned_at' => 'datetime'];

    protected $fillable = [
        'task_id',
        'r2_path',
        'namespace_id',
        'perceel',
        'regio_path',
        'adres_path',
        'target_dropbox_path',
        'status', 'upload_id', 'content_sha256', 'byte_size', 'confirmed_at',
        'completed_at', 'cleaned_at', 'attempts', 'error_message',
    ];
}
