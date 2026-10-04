@forelse ($tasks as $task)
    @php
        $colors = [
            'open' => 'bg-gray-200 text-gray-800',
            'in behandeling' => 'bg-yellow-200 text-yellow-800',
            'finished' => 'bg-green-200 text-green-800',
            'reopened' => 'bg-red-200 text-red-800',
        ];
        $statusColor = $colors[$task->status] ?? 'bg-gray-200 text-gray-800';

        $photos = $task->photo ? explode(',', $task->photo) : [];
    @endphp

    <!-- ✅ Desktop/tablet versie -->
    <tr class="hidden md:table-row hover:bg-gray-50">
        <td class="border px-3 py-2">{{ \Carbon\Carbon::parse($task->time)->format('Y-m-d H:i') }}</td>
        <td class="border px-3 py-2">
            {{ $task->address->street }} {{ $task->address->number }},
            {{ $task->address->zipcode }} {{ $task->address->city }}
        </td>
        <td class="border px-3 py-2">{{ optional($task->team)->name ?? 'Team ' . $task->team_id }}</td>
        <td class="border px-3 py-2">
            <span class="px-2 py-1 rounded text-xs font-semibold {{ $statusColor }}">{{ $task->status }}</span>
        </td>
        <td class="border px-3 py-2">{{ $task->note ?? '-' }}</td>
        <td class="border px-3 py-2">
            @if(count($photos) > 0)
                <div class="flex flex-wrap gap-2">
                    @foreach($photos as $photo)
                        <img src="/dropbox/preview?path={{ urlencode($photo) }}"
                             onclick="openPhotoModal(this.src)"
                             class="w-16 h-16 object-cover rounded cursor-pointer border">
                    @endforeach
                </div>
            @else
                <span class="text-gray-400">Geen foto’s</span>
            @endif
        </td>
        <td class="border px-3 py-2">
            @if (Auth::user()->role === 'admin')
                <a href="{{ route('schedule.edit', $task) }}?redirect={{ urlencode(request()->fullUrl()) }}"
                   class="px-3 py-1 rounded bg-[#2ea5d7] text-white hover:bg-[#2eb5d7]">
                    Bewerken
                </a>

                <form action="{{ route('schedule.destroy', $task) }}" method="POST" class="inline"
                      onsubmit="return confirm('Weet je zeker dat je deze taak wilt verwijderen?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-3 py-1 ml-1 rounded bg-[#B51D2D] text-white hover:bg-[#B53D2D]">
                        Verwijderen
                    </button>
                </form>
            @endif

            @if (Auth::user()->role === 'admin' && $task->status === 'finished')
                <form action="{{ route('tasks.reopen', $task) }}" method="POST" class="inline">
                    @csrf
                    @method('PATCH')
                    <button class="px-3 py-1 ml-1 rounded bg-[#005787] text-white hover:bg-[#006087]">
                        Heropenen
                    </button>
                </form>
            @endif
        </td>
    </tr>

    <!-- ✅ Mobiele versie (card layout) -->
    <tr class="md:hidden">
        <td colspan="7" class="border-b px-3 py-4">
            <div class="bg-white rounded-lg shadow p-4 space-y-3">

                <!-- Datum + Status -->
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600 font-medium">
                        {{ \Carbon\Carbon::parse($task->time)->format('d-m-Y H:i') }}
                    </span>
                    <span class="px-2 py-1 rounded text-xs font-semibold {{ $statusColor }}">
                        {{ ucfirst($task->status) }}
                    </span>
                </div>

                <!-- Adres -->
                <div class="text-sm text-gray-700">
                    <strong>Adres:</strong><br>
                    {{ $task->address->street }} {{ $task->address->number }},
                    {{ $task->address->zipcode }} {{ $task->address->city }}
                </div>

                <!-- Team -->
                <div class="text-sm text-gray-700">
                    <strong>Team:</strong> {{ optional($task->team)->name ?? 'Team ' . $task->team_id }}
                </div>

                <!-- Notitie -->
                <div class="text-sm text-gray-700">
                    <strong>Notitie:</strong> {{ $task->note ?? '-' }}
                </div>

                <!-- Foto’s -->
                <div>
                    <strong class="text-sm text-gray-700">Foto’s:</strong>
                    @if(count($photos) > 0)
                        <div class="grid grid-cols-3 gap-2 mt-2">
                            @foreach($photos as $photo)
                                <img src="/dropbox/preview?path={{ urlencode($photo) }}"
                                     onclick="openPhotoModal(this.src)"
                                     class="w-full h-20 object-cover rounded cursor-pointer border">
                            @endforeach
                        </div>
                    @else
                        <span class="text-gray-400 text-sm">Geen foto’s</span>
                    @endif
                </div>

                <!-- Acties -->
                <div class="flex flex-col gap-2 pt-2">
                    @if (Auth::user()->role === 'admin')
                        <a href="{{ route('schedule.edit', $task) }}?redirect={{ urlencode(request()->fullUrl()) }}"
                           class="block text-center px-3 py-2 rounded bg-[#2ea5d7] text-white hover:bg-[#2eb5d7]">
                            Bewerken
                        </a>

                        <form action="{{ route('schedule.destroy', $task) }}" method="POST"
                              onsubmit="return confirm('Weet je zeker dat je deze taak wilt verwijderen?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="w-full px-3 py-2 rounded bg-[#B51D2D] text-white hover:bg-[#B53D2D]">
                                Verwijderen
                            </button>
                        </form>
                    @endif

                    @if (Auth::user()->role === 'admin' && $task->status === 'finished')
                        <form action="{{ route('tasks.reopen', $task) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button class="w-full px-3 py-2 rounded bg-[#005787] text-white hover:bg-[#006087]">
                                Heropenen
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="7" class="text-center text-gray-500 py-4">Geen taken gevonden.</td>
    </tr>
@endforelse
