<x-layouts.dashboard>
    <div class="max-w-4xl mx-auto py-6">

        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Materiaal Bewerken</h1>
            <a href="{{ route('shop.index') }}" class="text-gray-600 hover:text-gray-900 flex items-center gap-1">
                &larr; Terug naar overzicht
            </a>
        </div>

        <div class="bg-white rounded-xl shadow p-6">
            <form action="{{ route('shop.update', $material->id) }}" method="POST">
                @csrf
                @method('PUT') <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <div class="col-span-2">
                        <label for="description" class="block text-sm font-medium text-gray-700">Omschrijving / Naam</label>
                        <input type="text" name="description" id="description" 
                               value="{{ old('description', $material->description) }}" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#B51D2D] focus:ring-[#B51D2D] sm:text-sm" required>
                        @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="sap_number" class="block text-sm font-medium text-gray-700">SAP Nummer</label>
                        <input type="text" name="sap_number" id="sap_number" 
                               value="{{ old('sap_number', $material->sap_number) }}" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#B51D2D] focus:ring-[#B51D2D] sm:text-sm" required>
                        @error('sap_number') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="packaging" class="block text-sm font-medium text-gray-700">Verpakking (Optioneel)</label>
                        <input type="text" name="packaging" id="packaging" 
                               value="{{ old('packaging', $material->packaging) }}" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#B51D2D] focus:ring-[#B51D2D] sm:text-sm">
                        @error('packaging') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="unit" class="block text-sm font-medium text-gray-700">Eenheid</label>
                        <input type="text" name="unit" id="unit" 
                               value="{{ old('unit', $material->unit) }}" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#B51D2D] focus:ring-[#B51D2D] sm:text-sm"
                               placeholder="bv. Stuks, Paar, Doos, kg, Meter, Liter">
                        @error('unit') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                </div>

                <div class="mt-8 flex justify-between items-center">
                    
                    </div>
                    
                <div class="flex justify-end gap-3 mt-6">
                     <a href="{{ route('shop.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm font-medium">
                        Annuleren
                    </a>
                    <button type="submit" class="px-4 py-2 bg-[#B51D2D] text-white rounded-lg hover:bg-red-800 text-sm font-medium shadow-sm">
                        Wijzigingen Opslaan
                    </button>
                </div>
            </form>
            
            <div class="border-t mt-8 pt-6">
                <h3 class="text-sm font-medium text-red-600 mb-2">Gevaarlijke zone</h3>
                <div class="flex justify-between items-center">
                    <p class="text-sm text-gray-500">Wil je dit materiaal definitief verwijderen?</p>
                    <form action="{{ route('shop.destroy', $material->id) }}" method="POST" onsubmit="return confirm('Weet je zeker dat je {{ $material->description }} wilt verwijderen?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium underline">
                            Materiaal Verwijderen
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-layouts.dashboard>