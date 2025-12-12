<x-layouts.dashboard>
    <div class="min-h-screen py-10 px-4">
        <div class="max-w-7xl mx-auto">
            
            <div class="flex flex-col md:flex-row justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-4 md:mb-0">📂 Dossiers</h1>
                
                {{-- Zoekbalk --}}
                <form action="{{ route('dossiers.index') }}" method="GET" class="w-full md:w-1/3 flex">
                    <input 
                        type="text" 
                        name="search" 
                        value="{{ request('search') }}" 
                        placeholder="Zoek op adres of nummer..." 
                        class="w-full px-4 py-2 rounded-l-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                    <button type="submit" class="bg-[#283142] text-white px-4 py-2 rounded-r-lg hover:bg-[#B51D2D]">
                        Zoek
                    </button>
                </form>
            </div>

            {{-- Het Grid --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                @forelse($folders as $folder)
                    <a href="{{ route('dossiers.show', $folder->id) }}" class="block bg-white rounded-lg shadow hover:shadow-lg transition duration-300 transform hover:-translate-y-1 overflow-hidden group">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-4">
                                {{-- Map Icoon --}}
                                <div class="bg-blue-100 p-3 rounded-full text-blue-600 group-hover:bg-[#B51D2D] group-hover:text-white transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                    </svg>
                                </div>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800 truncate" title="{{ $folder->name }}">
                                {{ $folder->name }}
                            </h3>
                            <p class="text-sm text-gray-500 mt-2">
                                {{ basename($folder->parent_path) }}
                            </p>
                        </div>
                    </a>
                @empty
                    <div class="col-span-full text-center py-10">
                        <p class="text-gray-500 text-lg">Geen dossiers gevonden.</p>
                        @if(request('search'))
                            <a href="{{ route('dossiers.index') }}" class="text-blue-500 hover:underline mt-2 inline-block">Reset zoekopdracht</a>
                        @endif
                    </div>
                @endforelse
            </div>

            {{-- Paginering --}}
            <div class="mt-8">
                {{ $folders->links() }}
            </div>
        </div>
    </div>
</x-layouts.dashboard>