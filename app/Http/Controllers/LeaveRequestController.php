<?php

namespace App\Http\Controllers;

use App\Notifications\LeaveRequestCreatedNotification;
use Illuminate\Support\Facades\Notification;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Notifications\LeaveRequestStatusUpdatedNotification;
use Illuminate\Support\Facades\Mail; // 👈 Belangrijk
use App\Mail\NewLeaveRequestMail;    // 👈 De mail uit stap 3
use App\Mail\LeaveApprovedMail;  
use Illuminate\Support\Facades\Log;    // 👈 De mail uit stap 3

class LeaveRequestController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->role === 'admin') {
            $requests = LeaveRequest::with(['team', 'leaveType'])
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $requests = LeaveRequest::where('team_id', $user->id)
                ->with('leaveType')
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return view('leaves.index', compact('requests', 'user'));
    }

    public function create()
    {
        $user = Auth::user();

        if ($user->role === 'admin') {
            abort(403, 'Admins cannot create leave requests.');
        }

        // ✅ Haal ALLE teamleden op uit ALLE ploegen
        $allMembers = Team::pluck('members')->filter()->toArray();

        $members = collect($allMembers)
            ->flatMap(fn($str) => array_map('trim', explode(',', $str)))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        $leaveTypes = LeaveType::all();

        return view('leaves.create', compact('members', 'leaveTypes'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if ($user->role === 'admin') {
            abort(403, 'Admins cannot create leave requests.');
        }

        $validated = $request->validate([
            'member_name' => 'required|string|max:100',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => [
                'required',
                'date',
                function ($attribute, $value, $fail) {
                    $minDate = Carbon::now()->addDays(14)->startOfDay();
                    $selected = Carbon::parse($value);
                    if ($selected->lt($minDate)) {
                        $fail('De startdatum moet minstens 2 weken op voorhand liggen.');
                    }
                },
            ],
            'end_date' => [
                'required',
                'date',
                function ($attribute, $value, $fail) use ($request) {
                    $startDate = Carbon::parse($request->start_date);
                    $endDate = Carbon::parse($value);
                    $minDate = Carbon::now()->addDays(14)->startOfDay();

                    if ($endDate->lt($minDate)) {
                        $fail('De einddatum moet minstens 2 weken op voorhand liggen.');
                    }

                    if ($endDate->lt($startDate)) {
                        $fail('De einddatum mag niet vóór de startdatum liggen.');
                    }
                },
            ],
            'note' => 'nullable|string',
        ]);

        $leave = LeaveRequest::create([
            'team_id' => $user->id,
            'member_name' => $validated['member_name'],
            'leave_type_id' => $validated['leave_type_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'note' => $validated['note'] ?? null,
            'status' => 'pending'
        ]);

        // ---------------------------------------------------------
        // 📧 NIEUW: MAIL VERSTUREN NAAR ADMINS
        // ---------------------------------------------------------
        $adminEmails = [
            'emreakkus003@gmail.com',
        ];

        try {
            // We sturen de mail direct
            Mail::to($adminEmails)->send(new NewLeaveRequestMail($leave));
        } catch (\Exception $e) {
            // Log fout als mail niet werkt, zodat app niet crasht
            Log::error('Mail kon niet verzonden worden: ' . $e->getMessage());
        }
        // ---------------------------------------------------------

        // ✅ Stuur notificatie naar alle admins
        $admins = Team::where('role', 'admin')->get();
        $leaveType = LeaveType::find($validated['leave_type_id'])->name;

        Notification::send(
            $admins,
            new LeaveRequestCreatedNotification(
                $user->name ?? $user->team_name ?? 'Onbekend team',
                $validated['member_name'],
                $leaveType
            )
        );

        return redirect()->route('leaves.index')
            ->with('success', 'Verlofaanvraag succesvol ingediend.');
    }

    public function updateStatus(Request $request, $id)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            abort(403, 'Only admins can update request status.');
        }

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        $leave = LeaveRequest::findOrFail($id);
        $leave->update(['status' => $validated['status']]);

        // ---------------------------------------------------------
        // 📧 NIEUW: MAIL NAAR HR BIJ GOEDKEURING
        // ---------------------------------------------------------
        if ($validated['status'] === 'approved') {
            $hrEmail = 'emreakkus003@gmail.com'; // 👈 Pas dit aan naar het echte HR adres!
            
            try {
                Mail::to($hrEmail)
                    
                    ->send(new LeaveApprovedMail($leave));
            } catch (\Exception $e) {
                Log::error('HR Mail kon niet verzonden worden: ' . $e->getMessage());
            }
        }

        // ✅ Stuur notificatie naar het team/lid dat deze aanvraag deed
        $team = Team::find($leave->team_id);
        $leaveType = $leave->leaveType->name ?? 'verlof';
        $status = $validated['status'] === 'approved' ? 'goedgekeurd' : 'afgewezen';

        if ($team) {
            $team->notify(new LeaveRequestStatusUpdatedNotification(
                $leave->member_name,
                $leaveType,
                $status,
                $team->id // ✅ meegeven aan de notification
            ));
        }
        $msg = $validated['status'] === 'approved' 
            ? 'Verlof goedgekeurd en mail verstuurd naar HR.' 
            : 'Verlofaanvraag afgewezen.';

        return back()->with('success', 'Verlofaanvraag status geupdate ', $msg);
    }

    public function update(Request $request, $id)
    {
        $leave = LeaveRequest::findOrFail($id);
        $user = Auth::user();

        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => [
                'required',
                'date',
                function ($attribute, $value, $fail) {
                    $minDate = Carbon::now()->addDays(14)->startOfDay();
                    if (Carbon::parse($value)->lt($minDate)) {
                        $fail('De startdatum moet minstens 2 weken op voorhand liggen.');
                    }
                },
            ],
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => $user->role === 'admin'
                ? 'required|in:pending,approved,rejected'
                : 'nullable',
        ]);

        // ✅ Alleen admin mag status wijzigen
        if ($user->role !== 'admin') {
            // User ziet geen status veld → vul automatisch bestaande status in
            $validated['status'] = $leave->status;
        }
        $leave->update($validated);

        return redirect()->route('leaves.index')
            ->with('success', 'Verlofaanvraag succesvol bijgewerkt.');
    }


    public function edit($id)
    {
        $leave = LeaveRequest::findOrFail($id);
        $leaveTypes = LeaveType::all();
        $user = Auth::user();
        return view('leaves.edit', compact('leave', 'leaveTypes', 'user'));
    }

    public function destroy($id)
    {
        LeaveRequest::findOrFail($id)->delete();
        return redirect()->route('leaves.index')->with('success', 'Verlofaanvraag verwijderd.');
    }
}
