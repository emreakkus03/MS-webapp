<x-layouts.dashboard>
<div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <div class="mb-8">
            {{-- AANPASSING 1: Titel laat nu zien welke shop het is (eerste letter hoofdletter) --}}
            <h1 class="text-4xl font-bold text-slate-900 mb-2">{{ ucfirst($category) }} Shop 🛠️</h1>
            <p class="text-slate-600">Beheer en bestel materialen uit onze magazijn</p>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            
            {{-- AANPASSING 2: De categorie meegeven aan de route --}}
            <form action="{{ route('shop.index', $category) }}" method="GET" class="mb-6">
                <div class="flex flex-col sm:flex-row gap-3">
                    <input 
                        type="text" 
                        name="search" 
                        placeholder="Zoek op naam of SAP..." 
                        value="{{ request('search') }}"
                        class="flex-1 px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                    <button 
                        type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors duration-200"
                    >
                        Zoeken
                    </button>
                </div>
            </form>

            <div class="flex flex-col sm:flex-row gap-3">
                <a 
                    href="{{ route('shop.history', $category) }}"
                    class="flex-1 sm:flex-none bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-lg font-medium transition-colors duration-200 text-center"
                >
                    📋 Mijn Historiek
                </a>
                
                @php
    $sessionKey = 'cart_' . strtolower(trim($category));
@endphp

<a 
    href="{{ route('cart.view', $category) }}" 
    class="flex-1 sm:flex-none bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200 text-center"
>
    {{-- Nu kijken we zeker naar dezelfde sleutel als de controller --}}
    🛒 Winkelmand ({{ count(session($sessionKey, [])) }})
</a>
                
                @if(auth()->user()->role === 'admin')
                    <a href="{{ route('shop.create', ['category' => $category]) }}" class="flex-1 sm:flex-none bg-red-700 hover:bg-red-800 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200 text-center">
                        + Nieuw Materiaal
                    </a>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                @php
    
    $showPackagingColumn = $materials->pluck('packaging')->filter()->isNotEmpty();
@endphp
                <table class="w-full">
                    <thead class="bg-slate-800 text-white">
                        <tr>
                            <th class="px-6 py-4 text-left font-semibold">SAP</th>
                            <th class="px-6 py-4 text-left font-semibold">Omschrijving</th>
                           @if($showPackagingColumn)
            <th class="px-6 py-4 text-left font-semibold">Verpakking</th>
        @endif
                            <th class="px-6 py-4 text-left font-semibold">Actie</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($materials as $material)
                        <tr class="hover:bg-slate-50 transition-colors duration-150">
                            <td class="px-6 py-4 text-slate-900 font-mono font-semibold">{{ $material->sap_number }}</td>
                            <td class="px-6 py-4 text-slate-700">{{ $material->description }}</td>
                           @if($showPackagingColumn)
            <td class="px-6 py-4 text-slate-600">
                {{ $material->packaging ?? '-' }}
            </td>
        @endif
                            <td class="px-6 py-4">
                                <div class="flex flex-col gap-2">
                                    <form action="{{ route('cart.add', $material->id) }}" method="POST" class="flex items-center gap-2">
                                        @csrf
                                        <input 
                                            type="number" 
                                            name="quantity" 
                                            value="1" 
                                            min="1"
                                            class="w-16 px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-center"
                                        >
                                        <span class="text-slate-600 text-sm w-12">{{ $material->unit }}</span>
                                        <button 
                                            type="submit"
                                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200 whitespace-nowrap text-sm"
                                        >
                                            Toevoegen
                                        </button>
                                    </form>

                                    @if(auth()->user()->role === 'admin')
                                    <div class="flex items-center gap-2 pt-2 border-t border-gray-100">
                                        <a href="{{ route('shop.edit', $material->id) }}" class="text-xs font-bold text-blue-600 hover:text-blue-800">
                                            ✏️ Bewerken
                                        </a>
                                        <span class="text-gray-300">|</span>
                                        <form action="{{ route('shop.destroy', $material->id) }}" method="POST" class="inline" onsubmit="return confirm('Weet je zeker dat je {{ $material->description }} definitief wilt verwijderen?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs font-bold text-red-600 hover:text-red-800">
                                                🗑️ Verwijderen
                                            </button>
                                        </form>
                                    </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-slate-600">
                                Geen materialen gevonden in {{ $category }}
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-slate-200 bg-slate-50">
                {{-- Laravel onthoudt automatisch de query parameters bij links(), maar check even of dit werkt. --}}
                {{ $materials->appends(['search' => request('search')])->links() }}
            </div>
        </div>
    </div>
</div>
</x-layouts.dashboard>