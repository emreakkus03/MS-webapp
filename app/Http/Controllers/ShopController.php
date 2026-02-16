<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\Order;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\NewOrderMail;
use Illuminate\Support\Facades\Log;
use App\Notifications\NewOrderReceived;
use Illuminate\Support\Facades\Notification;
use App\Models\User;

class ShopController extends Controller
{
    // 1. Toon de lijst (Gefilterd op categorie: fluvius of handgereedschap)
    public function index(Request $request, $category)
    {
        // Check voor de zekerheid of de categorie geldig is (optioneel, maar netjes)
        if (!in_array($category, ['fluvius', 'handgereedschap'])) {
            // Als iemand een onzin categorie typt, stuur ze naar fluvius (of geef 404)
            return redirect()->route('shop.index', 'fluvius');
        }

        $query = Material::where('category', $category);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('description', 'like', "%$search%")
                  ->orWhere('sap_number', 'like', "%$search%");
            });
        }
session(['last_shop_category' => $category]);
        $materials = $query->paginate(20);

        // We geven $category mee zodat de view weet waar we zijn
        return view('shop.index', compact('materials', 'category'));
    }

  // 2. Voeg item toe aan de JUISTE sessie
    public function addToCart(Request $request, $id)
    {
        $material = Material::findOrFail($id);
        
        // BEVEILIGING: Check of het materiaal wel een categorie heeft
        if (empty($material->category)) {
            return redirect()->back()->with('error', 'Fout: Dit materiaal heeft geen categorie in de database!');
        }

        // We maken alles kleine letters en halen spaties weg voor de zekerheid
        $cleanCategory = strtolower(trim($material->category));
        
        // De sessie sleutel wordt bv: 'cart_fluvius'
        $cartKey = 'cart_' . $cleanCategory;

        $cart = session()->get($cartKey, []);

        if (isset($cart[$id])) {
            $cart[$id]['quantity'] += $request->quantity;
        } else {
            $cart[$id] = [
                'name' => $material->description,
                'sap' => $material->sap_number,
                'packaging' => $material->packaging,
                'unit' => $material->unit,
                'quantity' => $request->quantity,
                'category' => $cleanCategory // Sla ook de schone versie op
            ];
        }

        session()->put($cartKey, $cart);
        
        // Debug regel: haal deze weg als het werkt!
        // dd(session()->all()); 
        
        return redirect()->back()->with('success', 'Product toegevoegd aan ' . ucfirst($cleanCategory) . ' winkelmand!');
    }

    // 3. Bekijk de winkelmand (Specifiek voor de shop waar je bent)
    public function viewCart($category)
    {
        $cartKey = 'cart_' . $category;
        $cart = session()->get($cartKey, []);
        
        return view('shop.cart', compact('cart', 'category'));
    }

    // 4. Verwijder item uit de juiste mand
    public function removeFromCart($id)
    {
        $material = Material::findOrFail($id);
        $cartKey = 'cart_' . $material->category;

        $cart = session()->get($cartKey);
        if (isset($cart[$id])) {
            unset($cart[$id]);
            session()->put($cartKey, $cart);
        }
        return redirect()->back()->with('success', 'Item verwijderd.');
    }

    // 5. Update aantallen in de mand
    public function updateCart(Request $request, $id)
    {
        // We moeten even weten in welke mand dit item zit
        // Omdat we hier geen category in de URL hebben (POST request),
        // zoeken we het materiaal even op.
        $material = Material::findOrFail($id);
        $cartKey = 'cart_' . $material->category;

        $cart = session()->get($cartKey);

        if (isset($cart[$id])) {
            $cart[$id]['quantity'] = $request->quantity;
            session()->put($cartKey, $cart);
            return redirect()->back()->with('success', 'Aantal bijgewerkt!');
        }

        return redirect()->back()->with('error', 'Product niet gevonden.');
    }

    // 6. CHECKOUT (Specifiek per shop)
    public function checkout(Request $request, $category)
    {
        $user = Auth::user();
        $nowInBelgium = now()->timezone('Europe/Brussels');

        if (in_array($user->role, ['admin', 'warehouseman'])) {
            $dateRule = 'today';
        } else {
            $dateRule = $nowInBelgium->hour >= 13 ? 'tomorrow' : 'today';
        }
        
        $request->validate([
            'pickup_date'   => 'required|date|after_or_equal:' . $dateRule,
            'license_plate' => 'required|string',
            'quantities'    => 'required|array',
            'quantities.*'  => 'required|integer|min:1',
        ]);

        // Haal de juiste sessie op
        $cartKey = 'cart_' . $category;
        $cart = session()->get($cartKey);

        if (!$cart) {
            return redirect()->back()->with('error', 'Je winkelmand is leeg!');
        }

        // Update sessie met laatste aantallen uit formulier
        foreach ($request->quantities as $id => $quantity) {
            if (isset($cart[$id])) {
                $cart[$id]['quantity'] = $quantity;
            }
        }
        session()->put($cartKey, $cart);

        $teamId = $user->id;

        // DB Transactie
        $order = DB::transaction(function () use ($request, $cart, $teamId) {
            $order = Order::create([
                'team_id'       => $teamId,
                'pickup_date'   => $request->pickup_date,
                'license_plate' => $request->license_plate,
                'status'        => 'pending'
            ]);

            foreach ($cart as $id => $details) {
                $order->materials()->attach($id, [
                    'quantity' => $details['quantity'],
                    'ready' => false
                ]);
            }
            return $order;
        });

        // Notificaties
        $warehouseUsers = Team::whereIn('role', ['warehouseman', 'admin'])->get();
        if ($warehouseUsers->count() > 0) {
            Notification::send($warehouseUsers, new NewOrderReceived($order));
        }

        // Mail sturen
        // Wil je dat ALLES naar hetzelfde adres gaat? Of apart?
        // Nu gaat alles naar magazijn2. Als je apart wil, kan ik hier een if-je zetten.
        $magazijnEmail = 'magazijn2@msinfra.be'; 

        try {
            $order->load(['materials', 'team']); 
            Mail::to($magazijnEmail)->send(new NewOrderMail($order));
        } catch (\Exception $e) {
            Log::error('Bestelmail fout: ' . $e->getMessage());
        }

        // Alleen DEZE mand legen
        session()->forget($cartKey);

        return redirect()->route('shop.success', $order->id);
    }

    // Success pagina
    public function orderSuccess(Order $order)
    {
        if (Auth::user()->id !== $order->team_id && Auth::user()->role !== 'admin') {
            abort(403);
        }
        return view('shop.success', compact('order'));
    }

    // History
    public function history($category)
    {
        $user = Auth::user();

        // Check op geldige categorie (veiligheid)
        if (!in_array($category, ['fluvius', 'handgereedschap'])) {
            return redirect()->route('shop.index', 'fluvius');
        }

        $orders = Order::where('team_id', $user->id)
            // HIER IS DE MAGIE: Filter orders op basis van de materialen die erin zitten
            ->whereHas('materials', function ($query) use ($category) {
                $query->where('category', $category);
            })
            ->with('materials')
            ->orderBy('created_at', 'desc')
            ->get();

        // We sturen de category mee naar de view
        return view('shop.history', compact('orders', 'category'));
    }

    // --- ADMIN CRUD ---

    public function create()
    {
        if (Auth::user()->role !== 'admin') abort(403);
        return view('shop.create');
    }

    public function store(Request $request)
    {
        if (Auth::user()->role !== 'admin') abort(403);

        $request->validate([
            'description' => 'required|string|max:255',
            'sap_number'  => 'required|string|unique:materials,sap_number',
            'unit'        => 'required|string|max:10',
            // Zorg dat je in je formulier een select menu hebt voor category: fluvius of handgereedschap
            'category'    => 'required|string', 
        ]);

        Material::create($request->all());

        // Redirect naar de juiste shop index
        return redirect()->route('shop.index', $request->category)->with('success', 'Materiaal toegevoegd!');
    }

    public function edit($id)
    {
        if (Auth::user()->role !== 'admin') abort(403);
        $material = Material::findOrFail($id);
        return view('shop.edit', compact('material'));
    }

    public function update(Request $request, $id)
    {
        if (Auth::user()->role !== 'admin') abort(403);
        $material = Material::findOrFail($id);

        $request->validate([
            'description' => 'required|string|max:255',
            'sap_number'  => 'required|string|unique:materials,sap_number,' . $material->id,
            'unit'        => 'required|string',
            'category'    => 'required|string',
        ]);

        $material->update($request->all());

        return redirect()->route('shop.index', $request->category)->with('success', 'Materiaal bijgewerkt.');
    }

    public function destroy($id)
    {
        if (Auth::user()->role !== 'admin') abort(403);
        $material = Material::findOrFail($id);
        $cat = $material->category; // Onthouden voor redirect
        $material->delete();

        return redirect()->route('shop.index', $cat)->with('success', 'Materiaal verwijderd.');
    }
}