<x-layouts.dashboard>
    <div class="grid grid-cols-3 gap-6">

        <!-- Linkerzijde: Takenlijst -->
        <div class="col-span-2">
            <h1 class="text-2xl font-bold mb-4">Taken van vandaag</h1>

            @if (session('success'))
                <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">
                    {{ session('success') }}
                </div>
            @endif

            @if ($tasksToday->isEmpty())
                <p>Geen taken vandaag!</p>
            @else
                <table class="w-full border border-gray-300 rounded">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border px-4 py-2 text-left">Tijd</th>
                            <th class="border px-4 py-2 text-left">Adres</th>
                            <th class="border px-4 py-2 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tasksToday as $task)
                            <tr class="cursor-pointer hover:bg-gray-100"
                                onclick="openTaskForm('{{ $task->id }}', '{{ $task->address->street }} {{ $task->address->number }}', '{{ \Carbon\Carbon::parse($task->time)->format('Y-m-d H:i') }}', '{{ $task->status }}')">
                                <td class="border px-4 py-2">{{ \Carbon\Carbon::parse($task->time)->format('H:i') }}
                                </td>
                                <td class="border px-4 py-2">{{ $task->address->street }} {{ $task->address->number }}
                                </td>
                                <td class="border px-4 py-2 capitalize">{{ $task->status }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <!-- Rechterzijde: Formulier -->
        <div id="taskFormPanel" class="col-span-1 hidden bg-white p-4 rounded shadow">
            <h2 class="text-lg font-bold mb-3">Taak afronden</h2>

            <form id="finishForm" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="block text-sm font-medium">Adres</label>
                    <input type="text" id="taskAddress" readonly class="w-full border px-3 py-2 rounded bg-gray-100">
                </div>

                <div class="mb-3">
                    <label class="block text-sm font-medium">Tijd</label>
                    <input type="text" id="taskTime" readonly class="w-full border px-3 py-2 rounded bg-gray-100">
                </div>

                <div class="mb-3">
                    <label class="block text-sm font-medium">Notitie</label>
                    <textarea name="note" rows="4" class="w-full border px-3 py-2 rounded">{{ old('note') }}</textarea>
                </div>

                <button type="submit" id="finishButton"
                    class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                    Voltooid
                </button>
            </form>
        </div>
    </div>

    <script>
        function openTaskForm(taskId, address, time, status) {
            document.getElementById('taskFormPanel').classList.remove('hidden');
            document.getElementById('taskAddress').value = address;
            document.getElementById('taskTime').value = time;

            let form = document.getElementById('finishForm');
            form.action = `/tasks/${taskId}/finish`;

            // knop alleen tonen bij open status
            if (status === 'open') {
                document.getElementById('finishButton').classList.remove('hidden');
            } else {
                document.getElementById('finishButton').classList.add('hidden');
            }
        }
    </script>
</x-layouts.dashboard>
