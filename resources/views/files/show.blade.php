<x-layouts.dashboard>
    <div class="min-h-screen bg-gray-100 py-10 px-4">
        <div class="max-w-5xl mx-auto bg-white rounded-lg shadow-lg overflow-hidden">

            {{-- Header met Terug knop --}}
            <div class="p-6 border-b border-gray-200 flex justify-between items-center bg-[#283142] text-white">
                <h1 class="text-xl font-bold truncate flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                    </svg>
                    {{ $folder->name }}
                </h1>
                <a href="{{ route('dossiers.index') }}"
                    class="text-sm bg-gray-600 hover:bg-gray-500 px-3 py-1 rounded transition">
                    &larr; Terug naar overzicht
                </a>
            </div>

            <div class="p-6">
                @if ($files->isEmpty())
                    <div class="text-center py-10 text-gray-500">
                        <p>📂 Deze map is leeg.</p>
                    </div>
                @else
                    <ul class="divide-y divide-gray-200">
                        @foreach ($files as $file)
                            <li class="py-4 flex items-center justify-between hover:bg-gray-50 px-2 rounded transition">
                                <div class="flex items-center gap-3 overflow-hidden">

                                    {{-- Icoon --}}
                                    <div class="flex-shrink-0">
                                        @if (Str::endsWith(strtolower($file['name']), ['.jpg', '.jpeg', '.png', '.webp']))
                                            <span class="text-2xl">📷</span>
                                        @elseif(Str::endsWith(strtolower($file['name']), ['.pdf']))
                                            <span class="text-2xl">📄</span>
                                        @else
                                            <span class="text-2xl text-gray-400">📎</span>
                                        @endif
                                    </div>

                                    <div class="min-w-0">
                                        {{-- 👇 NIEUW: Submap weergave --}}
                                        @php
                                            // Trucje om te kijken of het bestand in een submap zit
                                            // We pakken het volledige pad, halen de dossiernaam eraf en de bestandsnaam eraf.
                                            $relativePath = str_replace(
                                                $folder->path_display,
                                                '',
                                                $file['path_display'],
                                            );
                                            $subfolderName = dirname($relativePath);
                                        @endphp

                                        @if ($subfolderName !== '/' && $subfolderName !== '.')
                                            {{-- Toon de submap naam in een grijs labeltje --}}
                                            <div
                                                class="text-xs text-blue-600 bg-blue-50 inline-block px-2 py-0.5 rounded mb-1 font-mono">
                                                📂 {{ ltrim($subfolderName, '/\\') }}
                                            </div>
                                        @endif

                                        <p class="text-sm font-medium text-gray-900 truncate"
                                            title="{{ $file['name'] }}">
                                            {{ $file['name'] }}
                                        </p>
                                        <p class="text-xs text-gray-500">{{ number_format($file['size'] / 1024, 1) }} KB
                                        </p>
                                    </div>
                                </div>

                                {{-- Knoppen --}}
                                <div class="flex-shrink-0 ml-4">
                                    <a href="{{ route('dossiers.view', ['ns_id' => $nsId, 'path' => $file['path_display']]) }}"
                                        target="_blank"
                                        class="text-blue-600 hover:text-blue-900 text-sm font-semibold border border-blue-200 px-3 py-1 rounded hover:bg-blue-50">
                                        Bekijken
                                    </a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-layouts.dashboard>
