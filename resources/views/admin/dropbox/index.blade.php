<x-layouts.dashboard>
    {{-- Cache headers (optioneel, maar consistent met je login) --}}
    @php
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");
        header("Expires: 0");
    @endphp

    <div class="min-h-screen  py-10 px-4">
        {{-- De "Kaart" Container --}}
        <div class="max-w-6xl mx-auto bg-white rounded-lg shadow-lg overflow-hidden">
            
            {{-- Header Sectie --}}
            <div class="p-8 border-b border-gray-200">
                <h1 class="text-3xl font-bold text-center text-gray-800">Dropbox Beheer (MS INFRA)</h1>
                <p class="text-center text-gray-500 mt-2">Beheer de zichtbaarheid van mappen voor de ploegen.</p>
            </div>

            <div class="p-8">
                {{-- Feedback meldingen --}}
                @if(session('success'))
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm" role="alert">
                        <p class="font-bold">Succes</p>
                        <p>{{ session('success') }}</p>
                    </div>
                @endif
                @if(session('error'))
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm" role="alert">
                        <p class="font-bold">Foutmelding</p>
                        <p>{{ session('error') }}</p>
                    </div>
                @endif

                {{-- Actie Knoppen --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    
                    {{-- 1. Scan Hoofdstructuur (Snel) --}}
                    <div class="bg-blue-50 rounded-lg p-6 border border-gray-200 flex flex-col items-center justify-center">
                        <p class="text-gray-700 mb-4 font-medium text-center">Stap 1: Haal nieuwe jaren/hoofdmappen op</p>
                        <form action="{{ route('admin.dropbox.scan') }}" method="POST" class="w-full max-w-xs">
                            @csrf
                            <button type="submit" class="w-full bg-[#283142] hover:bg-[#B51D2D] text-white font-bold py-3 px-6 rounded transition duration-300 shadow-md flex items-center justify-center gap-2">
                                🔄 Scan Hoofdstructuur
                            </button>
                        </form>
                    </div>

                    {{-- 2. Sync Submappen (Traag) --}}
                    <div class="bg-blue-50 rounded-lg p-6 border border-blue-200 flex flex-col items-center justify-center">
                        <p class="text-blue-900 mb-4 font-medium text-center">Stap 3: Haal inhoud van 'Zichtbare' mappen op</p>
                        <form action="{{ route('admin.dropbox.sync') }}" method="POST" class="w-full max-w-xs" onsubmit="return confirm('Dit kan enkele minuten duren. Sluit dit venster niet! Wil je doorgaan?');">
                            @csrf
                            <button type="submit" class="w-full bg-[#283142] hover:bg-[#B51D2D] text-white font-bold py-3 px-6 rounded transition duration-300 shadow-md flex items-center justify-center gap-2">
                                🚀 Start Diepe Sync
                            </button>
                        </form>
                        <p class="text-xs text-blue-600 mt-2">⚠️ Duurt enkele minuten. Blijf op de pagina.</p>
                    </div>

                </div>

                {{-- De Tabel --}}
                <div class="overflow-x-auto">
                      <p class="text-blue-900 mb-4 font-medium text-center">Stap 2: Activeer de zichtbaarheid van een map</p>
                    <table class="min-w-full leading-normal border border-gray-200 rounded-lg">
                        <thead>
                            <tr class="bg-[#283142] text-white uppercase text-sm leading-normal">
                                <th class="px-5 py-3 text-left">Mapnaam</th>
                                <th class="px-5 py-3 text-left">Dropbox Pad</th>
                                <th class="px-5 py-3 text-center">Zichtbaarheid</th>
                                <th class="px-5 py-3 text-center">Sync Status</th>
                                <th class="px-5 py-3 text-center">Actie</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 text-sm font-light">
                            @forelse($folders as $folder)
                                <tr class="border-b border-gray-200 hover:bg-gray-50 transition duration-150">
                                    <td class="px-5 py-4 whitespace-nowrap font-medium text-gray-800">
                                        {{ $folder->name }}
                                    </td>
                                    <td class="px-5 py-4 whitespace-nowrap text-gray-500">
                                        {{ $folder->path_display }}
                                    </td>
                                    <td class="px-5 py-4 whitespace-nowrap text-center">
                                        @if($folder->is_visible)
                                            <span class="bg-green-200 text-green-800 py-1 px-3 rounded-full text-xs font-bold uppercase tracking-wide">
                                                Zichtbaar
                                            </span>
                                        @else
                                            <span class="bg-gray-200 text-gray-600 py-1 px-3 rounded-full text-xs font-bold uppercase tracking-wide">
                                                Verborgen
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 whitespace-nowrap text-center">
                                        @if($folder->is_synced)
                                            <span class="flex items-center justify-center text-green-600 font-semibold gap-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                                Klaar
                                            </span>
                                        @elseif($folder->is_visible)
                                            <span class="flex items-center justify-center text-orange-500 font-semibold gap-1 animate-pulse">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                Wacht op sync
                                            </span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 whitespace-nowrap text-center">
                                        <form action="{{ route('admin.dropbox.toggle', $folder->id) }}" method="POST">
                                            @csrf
                                            @if($folder->is_visible)
                                                <button type="submit" class="text-red-600 hover:text-white hover:bg-red-600 border border-red-600 px-4 py-2 rounded transition duration-300 text-xs font-bold uppercase">
                                                    Verbergen
                                                </button>
                                            @else
                                                <button type="submit" class="text-green-600 hover:text-white hover:bg-green-600 border border-green-600 px-4 py-2 rounded transition duration-300 text-xs font-bold uppercase">
                                                    Activeren
                                                </button>
                                            @endif
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-10 text-center text-gray-500 bg-gray-50 italic">
                                        Nog geen mappen gevonden. Klik op de knop hierboven om te scannen.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-layouts.dashboard>