<x-layouts.dashboard>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            🕵️‍♂️ Developer Logboek
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    <div class="overflow-x-auto">
                        <table class="min-w-full leading-normal">
                            <thead>
                                <tr>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Tijd</th>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Wie</th>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Actie</th>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Onderwerp</th>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($activities as $activity)
                                    <tr>
                                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                            {{ $activity->created_at->format('d-m-Y H:i') }}
                                            <div class="text-xs text-gray-400">
                                                {{ $activity->created_at->diffForHumans() }}</div>
                                        </td>

                                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm font-bold">
                                            @if ($activity->causer)
                                                {{ $activity->causer->name }}
                                            @else
                                                <span class="text-gray-400 italic">Systeem</span>
                                            @endif
                                        </td>

                                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                            @if ($activity->description == 'created')
                                                <span class="text-green-600 font-bold">Nieuw</span>
                                            @elseif($activity->description == 'updated')
                                                <span class="text-blue-600 font-bold">Aangepast</span>
                                            @elseif($activity->description == 'deleted')
                                                <span class="text-red-600 font-bold">Verwijderd</span>
                                            @else
                                                {{ $activity->description }}
                                            @endif
                                        </td>

                                       <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">

    @php
        // We bepalen eerst het type en halen de data op om de code leesbaar te houden
        $type = class_basename($activity->subject_type);
        $attributes = $activity->properties['attributes'] ?? [];
        $old = $activity->properties['old'] ?? [];
    @endphp

    {{-- =========================== --}}
    {{-- SCENARIO 1: HET IS EEN TAAK --}}
    {{-- =========================== --}}
    @if($type === 'Task')

        {{-- A. UPDATE: Het adres is gewijzigd --}}
        @if($activity->description == 'updated' && isset($old['address_id']))
            @php
                $oldAddr = \App\Models\Address::find($old['address_id']);
                $newAddr = $activity->subject ? $activity->subject->address : null;
            @endphp
            <div class="flex flex-col">
                <div class="text-xs text-red-400 line-through mb-1">
                    @if($oldAddr)
                        {{ $oldAddr->street }} {{ $oldAddr->number }}
                    @else
                        Onbekend (ID: {{ $old['address_id'] }})
                    @endif
                </div>
                <div class="font-bold text-green-600">
                    ⬇ {{ $newAddr ? $newAddr->street . ' ' . $newAddr->number : '(Geen adres)' }}
                </div>
            </div>

        
           {{-- B. DELETE: De taak is verwijderd --}}
        @elseif($activity->description == 'deleted')
            @php
                // Optie 1: Hebben we de tekst opgeslagen tijdens het verwijderen? (De nieuwe methode)
                $savedAddressText = $activity->properties['archived_address_text'] ?? null;

                // Optie 2: Zo niet, probeer het oude ID (De oude methode, voor oude logs)
                if (!$savedAddressText) {
                    $archivedId = $attributes['address_id'] ?? null;
                    $archivedAddr = $archivedId ? \App\Models\Address::find($archivedId) : null;
                    
                    if ($archivedAddr) {
                        $savedAddressText = $archivedAddr->street . ' ' . $archivedAddr->number;
                    }
                }
            @endphp

            <span class="text-red-600 font-bold block">🚫 Taak Verwijderd</span>
            <span class="text-gray-500 text-xs">
                @if($savedAddressText)
                    📍 {{ $savedAddressText }}
                @else
                    (Adresgegevens gewist)
                @endif
            </span>

        {{-- C. STANDAARD: Gewoon tonen (Create of Update zonder adreswijziging) --}}
        @else
            <div class="font-bold text-gray-700">
                @if($activity->subject && $activity->subject->address)
                    {{ $activity->subject->address->street }} {{ $activity->subject->address->number }}
                @elseif($activity->subject)
                    📅 Taak op {{ \Carbon\Carbon::parse($activity->subject->time)->format('d-m H:i') }}
                @else
                    Onbekende taak
                @endif
            </div>
           
        @endif


    {{-- =========================== --}}
    {{-- SCENARIO 2: HET IS EEN TEAM --}}
    {{-- =========================== --}}
    @elseif($type === 'Team')
        
        @php
            // Probeer de naam te pakken. Als het team weg is, pak de naam uit de log.
            $teamName = $activity->subject->name ?? $attributes['name'] ?? 'Onbekend Team';
        @endphp

        <div class="font-bold text-blue-900">
            Team: {{ $teamName }}
        </div>
        
        @if($activity->description == 'deleted')
            <span class="text-xs text-red-500 font-bold">(Verwijderd)</span>
        @endif


    {{-- =========================== --}}
    {{-- SCENARIO 3: IETS ANDERS    --}}
    {{-- =========================== --}}
    @else
        {{ $type }} #{{ $activity->subject_id }}
        @if($activity->description == 'deleted')
            <span class="text-red-500 italic text-xs block">🚫 Verwijderd</span>
        @endif
    @endif

</td>

                                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-gray-500">
                                            @if ($activity->description == 'updated')
                                                @foreach ($activity->properties['attributes'] ?? [] as $key => $val)
                                                    @if (isset($activity->properties['old'][$key]) && $activity->properties['old'][$key] != $val)
                                                        <div class="text-xs">
                                                            <strong>{{ ucfirst($key) }}:</strong>
                                                            <span
                                                                class="line-through text-red-400">{{ $activity->properties['old'][$key] }}</span>
                                                            ➝
                                                            <span
                                                                class="text-green-600 font-bold">{{ $val }}</span>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $activities->links() }}
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-layouts.dashboard>
