<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\Order;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Notifications\NewOrderReceived;
use Illuminate\Support\Facades\Notification;
use App\Models\User;

class ShopController extends Controller
{
    // 1. Toon de lijst met materialen (met zoekfunctie!)
    public function index(Request $request)
    {
        $query = Material::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('description', 'like', "%$search%")
                  ->orWhere('sap_number', 'like', "%$search%");
        }

        // We pagineren per 20 items, anders wordt de pagina te traag
        $materials = $query->paginate(20);

        return view('shop.index', compact('materials'));
    }

    // 2. Voeg item toe aan de sessie (niet database)
    public function addToCart(Request $request, $id)
    {
        $material = Material::findOrFail($id);
        $cart = session()->get('cart', []);

        // Als product al in mandje zit, tel aantal op. Anders nieuw toevoegen.
        if(isset($cart[$id])) {
            $cart[$id]['quantity'] += $request->quantity;
        } else {
            $cart[$id] = [
                'name' => $material->description,
                'sap' => $material->sap_number,
                'packaging' => $material->packaging,
                'unit' => $material->unit,
                'quantity' => $request->quantity
            ];
        }

        session()->put('cart', $cart);
        return redirect()->back()->with('success', 'Product toegevoegd aan winkelmand!');
    }

    // 3. Bekijk de winkelmand (Checkout pagina)
    public function viewCart()
    {
        $cart = session()->get('cart', []);
        return view('shop.cart', compact('cart'));
    }
    
    // 4. Verwijder item uit mandje
    public function removeFromCart($id)
    {
        $cart = session()->get('cart');
        if(isset($cart[$id])) {
            unset($cart[$id]);
            session()->put('cart', $cart);
        }
        return redirect()->back()->with('success', 'Item verwijderd.');
    }

    public function checkout(Request $request)
{
    $dateRule = now()->hour >= 13 ? 'tomorrow' : 'today';
    // 1. Validatie uitgebreid met 'quantities'
    $request->validate([
        'pickup_date'   => 'required|date|after_or_equal:' . $dateRule,
        'license_plate' => 'required|string',
        'quantities'    => 'required|array', // We verwachten een lijst met aantallen
        'quantities.*'  => 'required|integer|min:1', // Elk aantal moet min. 1 zijn
    ]);

    $cart = session()->get('cart');
    
    if(!$cart) {
        return redirect()->back()->with('error', 'Je winkelmand is leeg!');
    }

    // 2. UPDATE LOGICA: Werk de sessie bij met de nieuwe aantallen uit het formulier
    foreach ($request->quantities as $id => $quantity) {
        if (isset($cart[$id])) {
            $cart[$id]['quantity'] = $quantity;
        }
    }
    session()->put('cart', $cart); // Sla de nieuwe aantallen op

    $user = Auth::user();
    $teamId = $user->id; 

    $order = DB::transaction(function () use ($request, $cart, $teamId) {
        
        // A. Maak de Order aan
        $order = Order::create([
            'team_id'       => $teamId,
            'pickup_date'   => $request->pickup_date,
            'license_plate' => $request->license_plate,
            'status'        => 'pending'
        ]);

        // B. Koppel de materialen (Nu met de geüpdatete aantallen uit $cart)
        foreach($cart as $id => $details) {
            $order->materials()->attach($id, [
                'quantity' => $details['quantity'],
                'ready' => false 
            ]);
        }

        return $order;
    });

    $warehouseUsers = Team::whereIn('role', ['warehouseman', 'admin'])->get();

    // 2. Stuur de notificatie
    if ($warehouseUsers->count() > 0) {
        // We sturen de $order mee die net is aangemaakt
        Notification::send($warehouseUsers, new NewOrderReceived($order));
    }

    session()->forget('cart');

    return redirect()->route('shop.success', $order->id);
}
public function orderSuccess(Order $order)
{
    // Check voor de zekerheid of de order wel van dit team is (veiligheid)
    if (Auth::user()->id !== $order->team_id && Auth::user()->role !== 'admin') {
        abort(403);
    }

    return view('shop.success', compact('order'));
}
    // Voeg deze functie toe in je ShopController class
public function updateCart(Request $request, $id)
{
    $cart = session()->get('cart');

    if(isset($cart[$id])) {
        // Update het aantal met de nieuwe waarde uit de input
        $cart[$id]['quantity'] = $request->quantity;
        
        session()->put('cart', $cart);
        return redirect()->back()->with('success', 'Aantal bijgewerkt!');
    }

    return redirect()->back()->with('error', 'Product niet gevonden in mandje.');
}

public function history()
{
    $user = Auth::user();

    // Haal orders op waar team_id gelijk is aan de user id
    // We laden meteen de 'materials' mee om N+1 problemen te voorkomen (sneller)
    $orders = Order::where('team_id', $user->id)
                   ->with('materials')
                   ->orderBy('created_at', 'desc') // Nieuwste bovenaan
                   ->get();

    return view('shop.history', compact('orders'));
}

public function create()
    {
        // BEVEILIGING: Alleen admins mogen hierin
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Alleen beheerders mogen materialen toevoegen.');
        }

        return view('shop.create');
    }

    // 2. Sla het nieuwe materiaal op in de database (POST)
    public function store(Request $request)
    {
        if (Auth::user()->role !== 'admin') {
            abort(403);
        }

        // Validatie: zorg dat alles is ingevuld en het SAP nummer uniek is
        $request->validate([
            'description' => 'required|string|max:255',
            'sap_number'  => 'required|string|unique:materials,sap_number',
            'unit'        => 'required|string|max:10', // bijv. stuks, kg, doos
            'packaging'   => 'nullable|string|max:50', // bijv. per 10 verpakt
            'image'       => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Optioneel: foto upload
        ]);

        // Opslaan
        Material::create([
            'description' => $request->description,
            'sap_number'  => $request->sap_number,
            'unit'        => $request->unit,
            'packaging'   => $request->packaging,
            // Als je images hebt, zou je hier de path opslaan, anders null
        ]);

        return redirect()->route('shop.index')->with('success', 'Nieuw materiaal succesvol toegevoegd!');
    }

    // 3. Toon het formulier om te wijzigen
    public function edit($id)
    {
        if (Auth::user()->role !== 'admin') {
            abort(403);
        }

        $material = Material::findOrFail($id);
        return view('shop.edit', compact('material'));
    }

    // 4. Update de wijzigingen in de database (PUT/PATCH)
    public function update(Request $request, $id)
    {
        if (Auth::user()->role !== 'admin') {
            abort(403);
        }

        $material = Material::findOrFail($id);

        $request->validate([
            'description' => 'required|string|max:255',
            // Bij update: check uniek SAP nummer, maar negeer het huidige ID van dit materiaal
            'sap_number'  => 'required|string|unique:materials,sap_number,' . $material->id,
            'unit'        => 'required|string',
            'packaging'   => 'nullable|string',
        ]);

        $material->update([
            'description' => $request->description,
            'sap_number'  => $request->sap_number,
            'unit'        => $request->unit,
            'packaging'   => $request->packaging,
        ]);

        return redirect()->route('shop.index')->with('success', 'Materiaal is bijgewerkt.');
    }

    // 5. Verwijder materiaal uit de database (DELETE)
    public function destroy($id)
    {
        if (Auth::user()->role !== 'admin') {
            abort(403);
        }

        $material = Material::findOrFail($id);
        $material->delete();

        return redirect()->route('shop.index')->with('success', 'Materiaal is verwijderd.');
    }
}