<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Task;

class TaskController extends Controller
{
    public function finish(Request $request, Task $task)
    {
        $request->validate([
            'damage' => 'required|in:none,damage',
            'note'   => 'nullable|string|max:1000',
        ]);

        if ($task->status === 'open') {
            // Eerste keer → altijd "in behandeling"
            $task->status = 'in behandeling';
            $task->note   = $request->damage === 'damage' ? ucfirst($request->note) : null;

        } elseif ($task->status === 'in behandeling') {
            if ($request->damage === 'none') {
                // Geen schade meer → klaar
                $task->status = 'finished';
                $task->note   = null;
            } else {
                // Nog steeds schade → blijft in behandeling
                $task->status = 'in behandeling';
                $task->note   = ucfirst($request->note);
            }
        }

        $task->save();

        return redirect()->back()->with('success', 'Taak is afgerond!');
    }
}
