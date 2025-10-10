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

    <tr class="hover:bg-gray-50 transition">
        <!-- Datum & Tijd -->
        <td class="border px-3 py-2 text-sm whitespace-nowrap">
            {{ \Carbon\Carbon::parse($task->time)->format('d-m-Y H:i') }}
        </td>

        <!-- Adres -->
        <td class="border px-3 py-2 text-sm">
            <div class="max-w-[160px] md:max-w-[220px] lg:max-w-[300px] truncate">
                {{ $task->address->street }} {{ $task->address->number }},
                {{ $task->address->zipcode }} {{ $task->address->city }}
            </div>
        </td>

        <!-- Team -->
        <td class="border px-3 py-2 text-sm hidden sm:table-cell">
            {{ optional($task->team)->name ?? 'Team ' . $task->team_id }}
        </td>

        <!-- Status -->
        <td class="border px-3 py-2 text-sm">
            <span class="px-2 py-1 rounded text-xs font-semibold {{ $statusColor }}">
                {{ ucfirst($task->status) }}
            </span>
        </td>

        <!-- Notitie (zichtbaar vanaf md) -->
        <td class="border px-3 py-2 text-sm hidden md:table-cell">
            <div class="max-w-[180px] lg:max-w-[250px] truncate">
                {{ $task->note ?? '-' }}
            </div>
        </td>

        <!-- Foto’s (nu zichtbaar vanaf md, dus tablet en groter) -->
        <td class="border px-3 py-2 hidden md:table-cell">
            @if(count($photos) > 0)
                <div class="flex flex-wrap gap-1">
                    @foreach($photos as $photo)
                        <img src="/dropbox/preview?path={{ urlencode($photo) }}"
                             onclick="openPhotoModal(this.src)"
                             class="w-12 h-12 md:w-14 md:h-14 lg:w-16 lg:h-16 object-cover rounded cursor-pointer border">
                    @endforeach
                </div>
            @else
                <span class="text-gray-400 text-xs">Geen foto’s</span>
            @endif
        </td>

        <!-- Acties -->
        <td class="border px-3 py-2 text-sm">
            <div class="flex flex-wrap gap-1">
                @if (Auth::user()->role === 'admin')
                    <a href="{{ route('schedule.edit', $task) }}?redirect={{ urlencode(request()->fullUrl()) }}"
                       class="px-2 py-1 md:px-3 md:py-1 rounded bg-[#2ea5d7] text-white hover:bg-[#2eb5d7] text-xs md:text-sm">
                        Bewerken
                    </a>

                    <button type="button"
                    onclick="openDeleteModal({{ $task->id }})"
                    class="px-2 py-1 md:px-3 md:py-1 rounded bg-[#B51D2D] text-white hover:bg-[#B53D2D] text-xs md:text-sm">
                Verwijderen
            </button>

            <form id="delete-form-{{ $task->id }}"
                  action="{{ route('schedule.destroy', $task) }}"
                  method="POST" class="hidden">
                @csrf
                @method('DELETE')
            </form>
                @endif

                @if (Auth::user()->role === 'admin' && $task->status === 'finished')
                    <form action="{{ route('tasks.reopen', $task) }}" method="POST" class="inline">
                        @csrf
                        @method('PATCH')
                        <button class="px-2 py-1 md:px-3 md:py-1 rounded bg-[#005787] text-white hover:bg-[#006087] text-xs md:text-sm">
                            Heropenen
                        </button>
                    </form>
                @endif
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="7" class="text-center text-gray-500 py-4">Geen taken gevonden.</td>
    </tr>
@endforelse
