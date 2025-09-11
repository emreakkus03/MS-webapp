<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Task;

class TaskController extends Controller
{
    public function finish(Request $request, Task $task)
    {
        $request->validate([
            'note' => 'nullable|string|max:1000',
        ]);

        $task->update([
            'note'   => ucfirst($request->note),
            'status' => 'finished',
        ]);

        return redirect()->back()->with('success', 'Taak is afgerond!');
    }
}
