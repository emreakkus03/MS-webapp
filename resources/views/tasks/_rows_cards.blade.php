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

    <div class="bg-white rounded-lg shadow p-4 space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-sm text-gray-600 font-medium">
                {{ \Carbon\Carbon::parse($task->time)->format('d-m-Y H:i') }}
            </span>
            <span class="px-2 py-1 rounded text-xs font-semibold {{ $statusColor }}">
                {{ ucfirst($task->status) }}
            </span>
        </div>

        <div class="text-sm text-gray-700">
            <strong>Adres:</strong><br>
            {{ $task->address->street }} {{ $task->address->number }},
            {{ $task->address->zipcode }} {{ $task->address->city }}
        </div>

        <div class="text-sm text-gray-700">
            <strong>Team:</strong> {{ optional($task->team)->name ?? 'Team ' . $task->team_id }}
        </div>

        <div class="text-sm text-gray-700">
            <strong>Notitie:</strong> {{ $task->note ?? '-' }}
        </div>

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

        <div class="flex flex-col gap-2 pt-2">
            @if (Auth::user()->role === 'admin')
                <a href="{{ route('schedule.edit', $task) }}?redirect={{ urlencode(request()->fullUrl()) }}"
                   class="block text-center px-3 py-2 rounded bg-[#2ea5d7] text-white hover:bg-[#2eb5d7]">
                    Bewerken
                </a>

                <button type="button"
                onclick="openDeleteModal({{ $task->id }})"
                class="w-full px-3 py-2 rounded bg-[#B51D2D] text-white hover:bg-[#B53D2D]">
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
@empty
    <div class="text-center text-gray-500 py-4">Geen taken gevonden.</div>
@endforelse
