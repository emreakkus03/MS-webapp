<x-layouts.dashboard>
    <div class="max-w-7xl mx-auto py-6 px-4">
        
        {{-- Header --}}
        <div class="flex flex-col md:flex-row justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">☁️ R2 Beheer</h1>
            
            <div class="flex gap-3 mt-4 md:mt-0">
                <form action="{{ route('r2.retry') }}" method="POST">
                    @csrf
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded shadow flex items-center gap-2">
                        <span>🚀 Forceer Retry</span>
                    </button>
                </form>

                <form action="{{ route('r2.clear') }}" method="POST" onsubmit="return confirm('⚠️ WEET JE HET ZEKER?\n\nDit wist ALLE bestanden in R2 en je wachtrij database!');">
                    @csrf
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded shadow flex items-center gap-2">
                        <span>🗑️ Alles Verwijderen</span>
                    </button>
                </form>
            </div>
        </div>

        {{-- Meldingen --}}
        @if (session('success'))
            <div class="bg-green-100 text-green-800 p-4 rounded mb-4 border-l-4 border-green-500">
                {{ session('success') }}
            </div>
        @endif
        @if (session('warning'))
            <div class="bg-yellow-100 text-yellow-800 p-4 rounded mb-4 border-l-4 border-yellow-500">
                {{ session('warning') }}
            </div>
        @endif
        @if (session('error'))
            <div class="bg-red-100 text-red-800 p-4 rounded mb-4 border-l-4 border-red-500">
                {{ session('error') }}
            </div>
        @endif

        {{-- De Lijst --}}
        <div class="bg-white rounded-lg shadow overflow-hidden border border-gray-200">
            @if($uploads->isEmpty())
                <div class="p-12 text-center text-gray-500">
                    <p class="text-xl font-semibold">🎉 Alles is schoon!</p>
                    <p class="text-sm">Geen vastgelopen uploads gevonden.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-gray-600 uppercase font-semibold text-xs">
                            <tr>
                                <th class="p-4 border-b">Preview</th>
                                <th class="p-4 border-b">Taak</th>
                                <th class="p-4 border-b">Doelpad (Dropbox)</th>
                                <th class="p-4 border-b">Status</th>
                                <th class="p-4 border-b">Tijd</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($uploads as $row)
                                <tr class="hover:bg-gray-50 transition">
                                    {{-- 1. Preview --}}
                                    <td class="p-4">
                                        @if($row->preview_url)
                                            <a href="{{ $row->preview_url }}" target="_blank">
                                                <img src="{{ $row->preview_url }}" class="h-16 w-16 object-cover rounded border border-gray-300 shadow-sm hover:scale-105 transition-transform">
                                            </a>
                                        @else
                                            <div class="h-16 w-16 bg-gray-100 rounded flex items-center justify-center text-gray-400 text-xs text-center border border-gray-200">
                                                Geen<br>Preview
                                            </div>
                                        @endif
                                    </td>
                                    
                                    {{-- 2. Taak ID --}}
                                    <td class="p-4 font-mono text-blue-600 font-bold">
                                        #{{ $row->task_id }}
                                    </td>

                                    {{-- 3. Pad --}}
                                    <td class="p-4 text-gray-700 break-all max-w-xs text-xs font-mono">
                                        {{ $row->adres_path }}
                                    </td>

                                    {{-- 4. Status --}}
                                    <td class="p-4">
                                        <span class="px-2 py-1 rounded text-xs font-bold uppercase
                                            {{ $row->status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' }}">
                                            {{ $row->status }}
                                        </span>
                                    </td>

                                    {{-- 5. Tijd --}}
                                    <td class="p-4 text-gray-500 whitespace-nowrap text-xs">
                                        {{ $row->created_at->diffForHumans() }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-layouts.dashboard>