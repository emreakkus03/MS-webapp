<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;

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

      public function index(Request $request)
    {
        $status = $request->query('status');
        $q      = $request->query('q');

        $tasks = Task::with(['address','team'])
            ->when($status, fn($query) => $query->where('status', $status))
            ->when($q, function($query) use ($q) {
                $query->whereHas('address', function ($sub) use ($q) {
                    $sub->where('street', 'like', "%{$q}%")
                        ->orWhere('number', 'like', "%{$q}%")
                        ->orWhere('zipcode', 'like', "%{$q}%")
                        ->orWhere('city', 'like', "%{$q}%");
                });
            })
            ->orderBy('time', 'desc')
            ->paginate(10);

        return view('tasks.index', compact('tasks', 'status', 'q'));
    }

    /**
     * ADMIN: REOPEN
     */
    public function reopen(Task $task)
    {
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Geen toegang');
        }

        if ($task->status !== 'finished') {
            return redirect()->back()->with('error', 'Alleen afgeronde taken kunnen heropend worden.');
        }

        $task->status = 'reopened';
        $task->save();

        return redirect()->route('tasks.index')->with('success', 'Taak is heropend.');
    }

    public function filter(Request $request)
{
    $status = $request->query('status');
    $q      = $request->query('q');

    $tasks = Task::with(['address','team'])
        ->when($status, fn($query) => $query->where('status', $status))
        ->when($q, function($query) use ($q) {
            $query->whereHas('address', function ($sub) use ($q) {
                $sub->where('street', 'like', "%{$q}%")
                    ->orWhere('number', 'like', "%{$q}%")
                    ->orWhere('zipcode', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%");
            });
        })
        ->orderBy('time', 'desc')
        ->paginate(10); // ✅ paginatie toegevoegd

    if ($request->ajax()) {
        return response()->json([
            'rows' => view('tasks._rows', compact('tasks'))->render(),
            'pagination' => view('tasks._pagination', compact('tasks'))->render(),
        ]);
    }

    return view('tasks.index', compact('tasks'));
}


}
