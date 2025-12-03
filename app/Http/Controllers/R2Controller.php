<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\R2PendingUpload;

class R2Controller extends Controller
{
    public function registerUpload(Request $request)
    {
        $data = $request->validate([
            'task_id' => 'required|integer',
            'r2_path' => 'required|string',
            'namespace_id' => 'required|string',
            'adres_path' => 'required|string',
        ]);

        R2PendingUpload::create([
            'task_id'        => $data['task_id'],
            'r2_path'        => $data['r2_path'],
            'namespace_id'   => $data['namespace_id'],
            'adres_path'     => $data['adres_path'],
            'status'         => 'pending',
        ]);

        return response()->json(['success' => true]);
    }
}
