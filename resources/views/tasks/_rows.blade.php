@forelse ($tasks as $task)
    @php
        $colors = [
            'open' => 'bg-gray-200 text-gray-800',
            'in behandeling' => 'bg-yellow-200 text-yellow-800',
            'finished' => 'bg-green-200 text-green-800',
            'reopened' => 'bg-red-200 text-red-800',
        ];
        $statusColor = $colors[$task->status] ?? 'bg-gray-200 text-gray-800';
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
        <td class="border px-3 py-2">
            @if (Auth::user()->role === 'admin' && $task->status === 'finished')
                <form action="{{ route('tasks.reopen', $task) }}" method="POST" class="inline">
                    @csrf
                    @method('PATCH')
                    <button class="px-3 py-1 rounded bg-blue-500 text-white hover:bg-yellow-600">
                        Reopen
                    </button>
                </form>
            @else
                <span class="text-gray-400">—</span>
            @endif
        </td>
    </tr>
@empty
    <tr>
        <td colspan="5" class="text-center text-gray-500 py-4">Geen taken gevonden.</td>
    </tr>
@endforelse
