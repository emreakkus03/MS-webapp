<x-layouts.dashboard>
    <div class="max-w-4xl mx-auto py-6">
        
        {{-- We halen de categorie op uit de URL (bv. ?category=fluvius). 
             Is die er niet? Dan is 'fluvius' de fallback. --}}
        @php
            $sourceCategory = request('category', 'fluvius');
        @endphp

        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Nieuw Materiaal Toevoegen</h1>
            
            {{-- AANPASSING: De terugknop gaat nu naar de $sourceCategory --}}
            <a href="{{ route('shop.index', $sourceCategory) }}" class="text-gray-600 hover:text-gray-900 flex items-center gap-1">
                &larr; Terug naar overzicht
            </a>
        </div>

        <div class="bg-white rounded-xl shadow p-6">
            <form action="{{ route('shop.store') }}" method="POST">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    {{-- 1. BESCHRIJVING --}}
                    <div class="col-span-2">
                        <label for="description" class="block text-sm font-medium text-gray-700">Omschrijving / Naam</label>
                        <input type="text" name="description" id="description" 
                               value="{{ old('description') }}" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#B51D2D] focus:ring-[#B51D2D] sm:text-sm"
                               placeholder="bv. Hamer, Veiligheidsvest, ..." required>
                        @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- 2. CATEGORIE --}}
                    <div class="col-span-2 md:col-span-1">
                        <label for="category" class="block text-sm font-medium text-gray-700">Categorie / Shop</label>
                        <select name="category" id="category" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#B51D2D] focus:ring-[#B51D2D] sm:text-sm" required>
                            <option value="" disabled>Selecteer een shop...</option>
                            
                            {{-- AANPASSING: We selecteren automatisch de optie die overeenkomt met de URL parameter --}}
                            <option value="fluvius" {{ old('category', $sourceCategory) == 'fluvius' ? 'selected' : '' }}>Fluvius</option>
                            <option value="handgereedschap" {{ old('category', $sourceCategory) == 'handgereedschap' ? 'selected' : '' }}>Handgereedschap</option>
                        </select>
                        @error('category') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- 3. SAP NUMMER --}}
                    <div class="col-span-2 md:col-span-1">
                        <label for="sap_number" class="block text-sm font-medium text-gray-700">SAP Nummer</label>
                        <input type="text" name="sap_number" id="sap_number" 
                               value="{{ old('sap_number') }}" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#B51D2D] focus:ring-[#B51D2D] sm:text-sm"
                               placeholder="bv. 1045992" required>
                        @error('sap_number') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- 4. VERPAKKING --}}
                    <div>
                        <label for="packaging" class="block text-sm font-medium text-gray-700">Verpakking (Optioneel)</label>
                        <input type="text" name="packaging" id="packaging" 
                               value="{{ old('packaging') }}" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#B51D2D] focus:ring-[#B51D2D] sm:text-sm"
                               placeholder="bv. Doos van 10 stuks">
                        @error('packaging') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- 5. EENHEID --}}
                    <div>
                        <label for="unit" class="block text-sm font-medium text-gray-700">Eenheid</label>
                         <input type="text" name="unit" id="unit" 
                               value="{{ old('unit') }}" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#B51D2D] focus:ring-[#B51D2D] sm:text-sm"
                               placeholder="bv. Stuks, Paar, Doos, kg">
                        @error('unit') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                </div>

                <div class="mt-8 flex justify-end gap-3">
                    {{-- AANPASSING: Ook de Annuleren knop onderaan gaat nu naar de juiste plek --}}
                    <a href="{{ route('shop.index', $sourceCategory) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm font-medium">
                        Annuleren
                    </a>
                    <button type="submit" class="px-4 py-2 bg-[#B51D2D] text-white rounded-lg hover:bg-red-800 text-sm font-medium shadow-sm">
                        Opslaan
                    </button>
                </div>

            </form>
        </div>
    </div>
</x-layouts.dashboard>