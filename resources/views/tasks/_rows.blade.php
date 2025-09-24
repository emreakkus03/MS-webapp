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
    <tr>
        <td class="border px-3 py-2">
            {{ \Carbon\Carbon::parse($task->time)->format('Y-m-d H:i') }}
        </td>
        <td class="border px-3 py-2">
            {{ $task->address->street }} {{ $task->address->number }},
            {{ $task->address->zipcode }} {{ $task->address->city }}
        </td>
        <td class="border px-3 py-2">
            {{ optional($task->team)->name ?? 'Team ' . $task->team_id }}
        </td>
        <td class="border px-3 py-2">
            <span class="px-2 py-1 rounded text-xs font-semibold {{ $statusColor }}">
                {{ $task->status }}
            </span>
        </td>

        {{-- ✅ Notitie --}}
        <td class="border px-3 py-2">
            {{ $task->note ?? '-' }}
        </td>

        {{-- ✅ Foto’s --}}
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
                {{-- Bewerken knop --}}
                <a href="{{ route('schedule.edit', $task) }}?redirect={{ urlencode(request()->fullUrl()) }}"
                   class="px-3 py-1 rounded bg-[#2ea5d7] text-white hover:bg-[#2eb5d7]">
                    Bewerken
                </a>

                {{-- Delete knop --}}
                <form action="{{ route('schedule.destroy', $task) }}" method="POST" class="inline"
                      onsubmit="return confirm('Weet je zeker dat je deze taak wilt verwijderen?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-3 py-1 rounded bg-[#B51D2D] text-white hover:bg-[#B53D2D]">
                        Verwijder
                    </button>
                </form>
            @endif

            @if (Auth::user()->role === 'admin' && $task->status === 'finished')
                <form action="{{ route('tasks.reopen', $task) }}" method="POST" class="inline">
                    @csrf
                    @method('PATCH')
                    <button class="px-3 py-1 rounded bg-[#005787] text-white hover:bg-[#006087]">
                        Reopen
                    </button>
                </form>
            @else
                <span class="text-gray-400"></span>
            @endif
        </td>
    </tr>
@empty
    <tr>
        <td colspan="7" class="text-center text-gray-500 py-4">Geen taken gevonden.</td>
    </tr>
@endforelse
