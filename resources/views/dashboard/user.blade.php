<x-layouts.dashboard>
    <div class="grid grid-cols-3 gap-6 w-50">

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
                <table class="w-full border border-gray-300 rounded shadow">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border px-4 py-2 text-left">Tijd</th>
                            <th class="border px-4 py-2 text-left">Adres</th>
                            <th class="border px-4 py-2 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tasksToday as $task)
                            <tr class="cursor-pointer hover:bg-gray-100" data-task-id="{{ $task->id }}"
                                data-address="{{ $task->address->street }} {{ $task->address->number }}"
                                data-zipcode="{{ $task->address->zipcode }}" data-city="{{ $task->address->city }}"
                                data-time="{{ \Carbon\Carbon::parse($task->time)->format('Y-m-d H:i') }}"
                                data-status="{{ $task->status }}" data-note="{{ $task->note ?? '' }}"
                                data-damage="{{ $task->note ? 'damage' : 'none' }}">
                                <td class="border px-4 py-2">
                                    {{ \Carbon\Carbon::parse($task->time)->format('H:i') }}
                                </td>
                                <td class="border px-4 py-2">
                                    {{ $task->address->street }} {{ $task->address->number }},
                                    {{ $task->address->zipcode }} {{ $task->address->city }}
                                </td>
                                <td class="border px-4 py-2 capitalize">
                                    @php
                                        $colors = [
                                            'open' => 'bg-gray-200 text-gray-800',
                                            'in behandeling' => 'bg-yellow-200 text-yellow-800',
                                            'finished' => 'bg-green-200 text-green-800',
                                        ];
                                        $statusColor = $colors[$task->status] ?? 'bg-gray-200 text-gray-800';
                                    @endphp

                                    <span class="px-2 py-1 rounded text-sm font-semibold {{ $statusColor }}">
                                        {{ $task->status }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <!-- Rechterzijde: Formulier -->
        <div id="taskFormPanel" class="col-span-1 hidden">
            <div class="border rounded-lg shadow bg-white p-5">
                <!-- Adres + tijd -->
                <div class="mb-4">
                    <h2 id="taskAddressTitle" class="text-lg font-semibold"></h2>
                    <p id="taskZipCity" class="text-sm text-gray-600"></p>
                    <p id="taskTimeTitle" class="text-sm text-gray-600"></p>
                    <hr class="my-3">
                </div>

                <form id="finishForm" method="POST" class="space-y-4">
                    @csrf

                    <!-- Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <div class="flex items-center gap-2 text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z" />
                            </svg>
                            <span id="taskStatus">Open</span>
                        </div>
                    </div>

                    <!-- Schade / Geen schade -->
                    <div class="mb-3">
                        <label class="block text-sm font-medium">Beoordeling</label>
                        <div class="flex gap-6 mt-2">
                            <label class="flex items-center gap-2">
                                <input type="radio" name="damage" value="none" id="damageNone">
                                <span>Geen schade</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="damage" value="damage" id="damageYes">
                                <span>Schade</span>
                            </label>
                        </div>
                    </div>

                    <!-- Notitie veld -->
                    <div id="noteWrapper" class="mb-3 hidden">
                        <label class="block text-sm font-medium">Notitie</label>
                        <textarea name="note" rows="4" class="w-full border px-3 py-2 rounded"></textarea>
                    </div>

                    <!-- Knop -->
                    <div>
                        <button type="submit" id="finishButton"
                            class="w-full bg-[#283142] text-white py-2 rounded hover:bg-[#B51D2D] transition">
                            Voltooien
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openTaskForm(taskId, address, time, status, note) {
            const panel = document.getElementById('taskFormPanel');
            panel.classList.remove('hidden');

            const row = document.querySelector(`tr[data-task-id="${taskId}"]`);

            document.getElementById('taskAddressTitle').textContent = row.dataset.address;
            document.getElementById('taskZipCity').textContent = row.dataset.zipcode + " " + row.dataset.city;
            document.getElementById('taskTimeTitle').textContent = time;
            document.getElementById('taskStatus').textContent = status;

            let form = document.getElementById('finishForm');
            let finishButton = document.getElementById('finishButton');
            let noteWrapper = document.getElementById('noteWrapper');
            let noteField = document.querySelector('#finishForm textarea[name="note"]');
            let damageNone = document.getElementById('damageNone');
            let damageYes = document.getElementById('damageYes');

            // Reset
            damageNone.checked = false;
            damageYes.checked = false;
            noteWrapper.classList.add('hidden');
            noteField.value = '';

            if (status === 'finished') {
                // readonly
                form.action = "";
                finishButton.classList.add('hidden');
                damageNone.disabled = true;
                damageYes.disabled = true;
                noteField.setAttribute('readonly', true);
            } else {
                // open en in behandeling → invulbaar
                form.action = `/tasks/${taskId}/finish`;
                finishButton.classList.remove('hidden');
                damageNone.disabled = false;
                damageYes.disabled = false;
                noteField.removeAttribute('readonly');
            }

            // Bestaande data invullen
            if (row.dataset.damage === 'damage') {
                damageYes.checked = true;
                noteWrapper.classList.remove('hidden');
                noteField.value = note || '';
            } else {
                damageNone.checked = true;
                noteWrapper.classList.add('hidden');
            }

            // Eventlisteners
            damageNone.addEventListener('change', () => {
                if (damageNone.checked) {
                    noteWrapper.classList.add('hidden');
                    noteField.value = '';
                }
            });

            damageYes.addEventListener('change', () => {
                if (damageYes.checked) {
                    noteWrapper.classList.remove('hidden');
                }
            });
        }

        document.querySelectorAll("tbody tr").forEach(row => {
            row.addEventListener("click", function() {
                openTaskForm(
                    this.dataset.taskId,
                    this.dataset.address,
                    this.dataset.time,
                    this.dataset.status,
                    this.dataset.note
                );
            });
        });
    </script>
</x-layouts.dashboard>
