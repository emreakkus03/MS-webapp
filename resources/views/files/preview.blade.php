<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Afbeelding weergave</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 h-screen w-screen flex flex-col overflow-hidden">

    {{-- Bovenbalk met knoppen --}}
    <div class="bg-gray-800 text-white p-4 flex justify-between items-center shadow-md z-10">
        <h2 class="text-sm font-mono truncate max-w-lg text-gray-300">
            {{ basename($path) }}
        </h2>
        <div class="flex gap-4">
            {{-- Download knop (Forceert download) --}}
            <a href="{{ route('dossiers.stream', ['path' => $path, 'ns_id' => $nsId]) }}" download 
               class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded text-sm transition">
               ⬇ Downloaden
            </a>
            {{-- Sluit knop (sluit tabblad) --}}
            <button onclick="window.close()" class="bg-red-600 hover:bg-red-500 text-white px-4 py-2 rounded text-sm transition">
                ✕ Sluiten
            </button>
        </div>
    </div>

    {{-- De Afbeelding Container --}}
    <div class="flex-1 flex items-center justify-center p-4">
        {{-- 
            max-h-full = nooit hoger dan het scherm
            max-w-full = nooit breder dan het scherm
            object-contain = behoudt verhoudingen
        --}}
        <img src="{{ route('dossiers.stream', ['path' => $path, 'ns_id' => $nsId]) }}" 
             alt="Voorbeeld" 
             class="max-h-[85vh] max-w-full object-contain shadow-2xl rounded"
        >
    </div>

</body>
</html>