<x-layouts.dashboard>
<div class="container mx-auto p-4">
    <div class="flex justify-between items-center mb-5">
        <h1 class="text-3xl font-bold">Winkelmandje 🛒</h1>
        <a href="{{ route('shop.index') }}" class="px-4 py-2 bg-gray-500 text-white no-underline rounded hover:bg-gray-600 transition">
            ← Terug naar bestellen
        </a>
    </div>

    @if(session('cart'))
        <div class="overflow-x-auto">
            <table class="w-full border-collapse border border-gray-300">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="border border-gray-300 p-3 text-left">SAP nr</th>
                        <th class="border border-gray-300 p-3 text-left">Product</th>
                        <th class="border border-gray-300 p-3 text-left">Verpakking</th>
                        <th class="border border-gray-300 p-3 text-left">Aantal</th> 
                        <th class="border border-gray-300 p-3 text-left">Actie</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(session('cart') as $id => $details)
                    <tr class="hover:bg-gray-50">
                        <td class="border border-gray-300 p-3">({{ $details['sap'] }})</td>
                        <td class="border border-gray-300 p-3">{{ $details['name'] }}</td>
                        <td class="border border-gray-300 p-3">{{ $details['packaging'] }}</td>
                        
                        <td class="border border-gray-300 p-3">
                            <div class="flex items-center gap-2">
                                <input type="number" 
                                       name="quantities[{{ $id }}]" 
                                       value="{{ $details['quantity'] }}" 
                                       min="1" 
                                       form="checkout-form"
                                       class="w-16 px-2 py-1 border border-gray-300 rounded">
                                <span>{{ $details['unit'] }}</span>
                            </div>
                        </td>

                        <td class="border border-gray-300 p-3">
                            <form action="{{ route('cart.remove', $id) }}" method="POST">
                                @csrf
                                <button type="submit" class="text-red-600 cursor-pointer border-none bg-none hover:text-red-800">❌ Verwijder</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <hr class="my-6">

        <h3 class="text-2xl font-semibold mb-4">Gegevens voor afhaling</h3>
        
      <form id="checkout-form" action="{{ route('shop.checkout') }}" method="POST" class="space-y-4">
    @csrf
    
   @php
        $user = auth()->user();
        
        // 🕒 FIX: Tijdzone forceren naar Brussel
        $nowInBelgium = now()->timezone('Europe/Brussels');
        
        // Check of de gebruiker de regels mag omzeilen
        $canBypassTimeRule = in_array($user->role, ['admin', 'warehouseman']);

        if ($canBypassTimeRule) {
            // Admin & Magazijnier mogen ALTIJD vandaag kiezen
            $minDate = $nowInBelgium->format('Y-m-d');
        } else {
            
            $minDate = $nowInBelgium->hour >= 13 ? $nowInBelgium->addDay()->format('Y-m-d') : $nowInBelgium->format('Y-m-d');
        }
    @endphp

    <div>
        <label class="block font-medium mb-2">Gewenste afhaaldatum:</label>
        
        <input type="date" 
               name="pickup_date" 
               required 
               min="{{ $minDate }}" 
               class="w-full px-3 py-2 border border-gray-300 rounded">
        
       
        @if(now()->hour >= 13 && !$canBypassTimeRule)
            <p class="text-xs text-orange-600 mt-1">
                ⚠️ Omdat het na 13:00u is, is afhalen vandaag niet meer mogelijk.
            </p>
        @endif

        {{-- INFO VOOR ADMINS (Optioneel, handig voor duidelijkheid) --}}
        @if(now()->hour >= 13 && $canBypassTimeRule)
            <p class="text-xs text-blue-600 mt-1">
                ℹ️ <strong>Admin/Magazijnier:</strong> Je kunt ondanks het tijdstip toch voor vandaag bestellen.
            </p>
        @endif
    </div>

    <div>
        <label class="block font-medium mb-2" for="license_plate">Nummerplaat Voertuig:</label>
        <input type="text" 
               name="license_plate" 
               id="license_plate"
               placeholder="Bv. 1-ABC-123" 
               required
               class="w-full px-3 py-2 border border-gray-300 rounded"
               oninput="this.value = this.value.toUpperCase()" 
               onblur="formatBelgianPlate(this)"
        >
    </div>

    <button type="submit" class="px-5 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition">✅ Bestelling Plaatsen</button>
</form>

    @else
        <p class="text-gray-600">Je winkelmandje is nog leeg.</p>
        <a href="{{ route('shop.index') }}" class="text-blue-600 hover:text-blue-800">Terug naar de shop</a>
    @endif
</div>

<script>
    function formatBelgianPlate(input) {
        let value = input.value.replace(/[^A-Z0-9]/g, '');
        
        if (value.length === 7 && !isNaN(value[0])) {
            input.value = value.substring(0, 1) + '-' + value.substring(1, 4) + '-' + value.substring(4, 7);
        }
        else if (value.length === 6 && isNaN(value[0])) {
            input.value = value.substring(0, 3) + '-' + value.substring(3, 6);
        }
    }
</script>
</x-layouts.dashboard>
