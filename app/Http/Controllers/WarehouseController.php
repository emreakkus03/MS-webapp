<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Notifications\OrderReady;
use App\Models\User;
use App\Models\Team;

class WarehouseController extends Controller
{
    private function checkAdminAndWarehousemanAccess()
    {
        $user = Auth::user();
        
        // Als er geen user is OF de rol is geen admin of warehouseman -> STOP.
        if (!$user || !in_array($user->role, ['admin', 'warehouseman'])) {
            abort(403, 'Geen toegang. Alleen voor beheerders.');
        }
    }
   public function index(Request $request)
    {
        $this->checkAdminAndWarehousemanAccess();

        // We starten een query op de Order tabel
        $query = Order::with(['team', 'materials']);

        // FILTER 1: MODUS (Openstaand vs Historiek)
        // Als we in de URL ?show=history hebben staan:
        if ($request->get('show') === 'history') {
            $isHistory = true;
            $query->where('status', 'ready'); // Alleen wat klaar is
            $query->orderBy('pickup_date', 'desc'); // Nieuwste bovenaan
        } else {
            // Standaard modus: Werklijst
            $isHistory = false;
            $query->whereIn('status', ['pending', 'printed']); // Nog te doen
            $query->orderBy('pickup_date', 'asc'); // Dringendste eerst (oudste datum)
        }

        // FILTER 2: DATUM
        // Als er een datum gekozen is, filteren we daarop
        if ($request->filled('date')) {
            $query->whereDate('pickup_date', $request->date);
        }

        // PAGINERING
        // We gebruiken paginate(20) zodat je bij 4 jaar historiek niet 10.000 rijen laadt
        $orders = $query->paginate(20)->withQueryString();

        return view('warehouse.index', compact('orders', 'isHistory'));
    }

    public function printOrder(Order $order)
    {
        $this->checkAdminAndWarehousemanAccess();
        // Update status naar 'printed' als hij nog op 'pending' stond.
        // Zo weet een collega: "Hé, deze wordt al gepakt".
        if ($order->status === 'pending') {
            $order->update(['status' => 'printed']);
        }

        return view('warehouse.print', compact('order'));
    }

    public function markAsReady(Request $request, $id)
{
    $this->checkAdminAndWarehousemanAccess();
    $order = Order::findOrFail($id);
    $order->update(['status' => 'ready']);

    // 👇 Haal het Team op
    $team = Team::find($order->team_id);

    // 👇 Stuur de notificatie naar het TEAM
    if ($team) {
        $team->notify(new OrderReady($order));
    }

    return back()->with('success', 'Klaar gemeld!');
}
}