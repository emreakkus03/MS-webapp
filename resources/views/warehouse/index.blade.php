<x-layouts.dashboard>
<div class="container mx-auto px-4">
    <h1 class="text-3xl font-bold mb-6">Magazijn Dashboard 📦</h1>

    @if(session('success'))
        <div class="bg-green-100 text-green-800 p-3 mb-5 rounded">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-gray-50 p-4 rounded-lg mb-5 border border-gray-300">
        <form action="{{ route('warehouse.index') }}" method="GET" class="flex flex-wrap gap-5 items-center">
            
            <div>
                <strong class="block mb-2">Snelle selectie:</strong>
                <a href="{{ route('warehouse.index') }}" 
                   class="no-underline mr-1 {{ !request('date') && !request('show') ? 'text-black font-bold' : 'text-gray-400' }}">
                   Alles Openstaand
                </a>
                |
                <a href="{{ route('warehouse.index', ['date' => date('Y-m-d')]) }}" 
                   class="no-underline mx-1 {{ request('date') == date('Y-m-d') ? 'text-black font-bold' : 'text-gray-400' }}">
                   Vandaag
                </a>
                |
                <a href="{{ route('warehouse.index', ['date' => date('Y-m-d', strtotime('+1 day'))]) }}" 
                   class="no-underline ml-1 {{ request('date') == date('Y-m-d', strtotime('+1 day')) ? 'text-black font-bold' : 'text-gray-400' }}">
                   Morgen
                </a>
            </div>

            <div class="border-l border-gray-300 pl-5">
                <label class="block mb-2">Specifieke datum:</label>
                <input type="date" name="date" value="{{ request('date') }}" onchange="this.form.submit()" class="p-1 border border-gray-300 rounded">
                
                @if(request('show'))
                    <input type="hidden" name="show" value="{{ request('show') }}">
                @endif
            </div>

            <div class="ml-auto">
                @if($isHistory)
                    <a href="{{ route('warehouse.index') }}" class="bg-blue-500 text-white px-4 py-2 no-underline rounded">
                        ← Terug naar Werklijst
                    </a>
                @else
                    <button type="submit" name="show" value="history" class="bg-gray-500 text-white px-4 py-2 border-0 rounded cursor-pointer">
                        📜 Toon Archief / Historiek
                    </button>
                @endif
            </div>
        </form>
    </div>

    <h2 class="text-2xl font-bold mb-4">
        @if($isHistory)
            📂 Archief (Gereed gemeld)
        @elseif(request('date'))
            📅 Planning voor {{ \Carbon\Carbon::parse(request('date'))->format('d-m-Y') }}
        @else
            ⏳ Te Verzamelen (Alle openstaande)
        @endif
        <span class="text-xs text-gray-500">({{ $orders->total() }} resultaten)</span>
    </h2>
    
    <div class="overflow-x-auto mb-5">
        <table class="w-full border-collapse border border-gray-300">
            <thead>
                <tr class="bg-gray-200">
                    <th class="border border-gray-300 p-2 text-left">Order #</th>
                    <th class="border border-gray-300 p-2 text-left">Category</th>
                    <th class="border border-gray-300 p-2 text-left">Aanvraagdatum</th>
                    <th class="border border-gray-300 p-2 text-left">Ploeg </th>
                    <th class="border border-gray-300 p-2 text-left">Afhaaldatum</th>
                    <th class="border border-gray-300 p-2 text-left">Nr. Plaat</th>
                    <th class="border border-gray-300 p-2 text-left">Status</th>
                    <th class="border border-gray-300 p-2 text-left">Acties</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                <tr>
                    <td class="border border-gray-300 p-2">{{ $order->id }}</td>
                    <td class="border border-gray-300 p-2">
            @php
                
                $cat = $order->materials->first()->category ?? 'onbekend';
            @endphp

            @if(strtolower($cat) == 'fluvius')
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                    ⚡ Fluvius
                </span>
            @elseif(strtolower($cat) == 'handgereedschap')
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 border border-red-200">
                    🔨 Handgereedschap
                </span>
            @else
                <span class="text-gray-500 text-sm">{{ ucfirst($cat) }}</span>
            @endif
        </td>
                    <td class="border border-gray-300 p-2">{{ $order->created_at->format('d-m-Y') }}</td>
                    <td class="border border-gray-300 p-2">{{ $order->team->name ?? 'Ploeg ' . $order->team_id }}</td> 
                    
                    <td class="border border-gray-300 p-2 {{ $order->pickup_date->isToday() && !$isHistory ? 'text-red-600 font-bold' : '' }}">
                        {{ $order->pickup_date->format('d-m-Y') }}
                    </td>
                    
                    <td class="border border-gray-300 p-2 uppercase">{{ $order->license_plate }}</td>
                    
                    <td class="border border-gray-300 p-2">
                        @if($order->status == 'pending')
                            <span class="bg-orange-400 text-white px-1.5 py-0.5 rounded text-sm">Nieuw</span>
                        @elseif($order->status == 'printed')
                            <span class="bg-blue-200 text-blue-900 px-1.5 py-0.5 rounded text-sm">Wordt gepakt</span>
                        @else
                            <span class="bg-green-200 text-green-700 px-1.5 py-0.5 rounded text-sm">✅ Klaar</span>
                        @endif
                    </td>
                    
                    <td class="border border-gray-300 p-2">
                        <a href="{{ route('warehouse.print', $order->id) }}" target="_blank" 
                           class="bg-blue-500 text-white px-2.5 py-1.5 no-underline mr-1 text-sm rounded inline-block">
                           🖨️ Bon
                        </a>

                        @if(!$isHistory)
                        <form action="{{ route('warehouse.complete', $order->id) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="bg-green-500 text-white px-2.5 py-1.5 border-0 cursor-pointer text-sm rounded"
                                    onclick="return confirm('Is deze bestelling helemaal compleet en ingepakt?')">
                                ✅ Klaar
                            </button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="border border-gray-300 p-8 text-center text-gray-400">
                        Geen bestellingen gevonden voor deze filter.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mb-12">
        {{ $orders->links() }}
    </div>

</div>
</x-layouts.dashboard>