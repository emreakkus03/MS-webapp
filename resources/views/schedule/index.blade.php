<x-layouts.dashboard>
    <div class="md:p-6">

        <h1 class="text-2xl font-bold mb-4">Planning</h1>

        <!-- Flash message -->
        @if (session('success'))
            <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">
                {{ session('success') }}
            </div>
        @endif

        <!-- Admin team dropdown -->
        @if (Auth::user()->role === 'admin')
            <div class="mb-4">
                <label for="teamSelect" class="block font-medium mb-1">Kies een team:</label>
                <select id="teamSelect" class="border px-3 py-2 rounded">
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}" {{ $team->id == $defaultTeamId ? 'selected' : '' }}>
                            {{ $team->name }}
                        </option>
                    @endforeach
                </select>

            </div>
        @endif

        <button type="button" onclick="openCreateModal()"
            class="mb-6 bg-[#283142] text-white px-4 py-2 rounded hover:bg-[#B51D2D]">
            Nieuwe Taak
        </button>

        <!-- Kalender -->
        <div id="calendar" class="mb-6"></div>

        <!-- Modal voor nieuwe taak -->
        <div id="createModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-lg p-6">
                <h2 class="text-lg font-semibold mb-4">Nieuwe Taak Toevoegen</h2>

                <form action="{{ route('schedule.store') }}" method="POST" class="space-y-4">
                    @csrf

                    @if (Auth::user()->role === 'admin')
                        <div>
                            <label class="block text-sm font-medium">Ploeg</label>
                            <select name="team_id" class="w-full border px-3 py-2 rounded">
                                @foreach ($teams as $team)
                                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium">Tijdstip (*)</label>
                        <input type="datetime-local" name="time" required class="w-full border px-3 py-2 rounded">
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Adres (*)</label>
                        <input list="addresses" name="address_name" class="w-full border px-3 py-2 rounded"
                            placeholder="Typ hier een straat..." required>
                        <datalist id="addresses">
                            @foreach ($addresses as $address)
                                <option value="{{ $address->street }}">{{ $address->number }}, {{ $address->zipcode }},
                                    {{ $address->city }}</option>
                            @endforeach
                        </datalist>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Nummer (*)</label>
                        <input type="text" name="address_number" class="w-full border px-3 py-2 rounded" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Postcode (*)</label>
                        <input type="text" name="address_zipcode" class="w-full border px-3 py-2 rounded" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Stad (*)</label>
                        <input type="text" name="address_city" class="w-full border px-3 py-2 rounded" required>
                    </div>

                    <div class="flex justify-end gap-3 mt-4">
                        <button type="button" onclick="closeCreateModal()"
                            class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">
                            Annuleren
                        </button>
                        <button type="submit" class="bg-[#283142] text-white px-4 py-2 rounded hover:bg-[#B51D2D]">
                            Toevoegen
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal om taak te bekijken -->
        <div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-lg p-6">
                <h2 class="text-lg font-semibold mb-4">Taak Details</h2>

                <div class="space-y-2">
                    <p><strong>Tijdstip:</strong> <span id="viewTime"></span></p>
                    <p><strong>Adres:</strong> <span id="viewAddress"></span></p>
                    <p><strong>Nummer:</strong> <span id="viewNumber"></span></p>
                    <p><strong>Postcode:</strong> <span id="viewZip"></span></p>
                    <p><strong>Stad:</strong> <span id="viewCity"></span></p>
                </div>

                <div class="flex justify-end mt-4">
                    <button type="button" onclick="closeViewModal()"
                        class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">
                        Sluiten
                    </button>
                </div>
            </div>
        </div>

    </div>
</x-layouts.dashboard>

<!-- FullCalendar CSS & JS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth', // standaard maandweergave
            selectable: true,

            // Header met buttons om view te switchen
            headerToolbar: {
                left: 'prev,next',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },

            // Klik op dag → nieuwe taak modal
            dateClick: function(info) {
                openCreateModal(info.dateStr);
            },

            // Klik op event → view modal
            eventClick: function(info) {
                openViewModal(info.event);
            },

            events: [
                @foreach ($tasks as $task)
                    {
                        title: "{{ $task->address->street }} {{ $task->address->number ?? '' }}",
                        start: "{{ $task->time }}",
                        color: "blue",
                        extendedProps: {
                            time: "{{ \Carbon\Carbon::parse($task->time)->format('H:i') }}",
                            address_name: "{{ $task->address->street }}",
                            address_number: "{{ $task->address->number ?? '' }}",
                            zipcode: "{{ $task->address->zipcode ?? '' }}",
                            city: "{{ $task->address->city ?? '' }}"
                        }
                    },
                @endforeach
            ]
        });

        calendar.render();

        // Admin: update kalender bij team select
        @if (Auth::user()->role === 'admin')
            document.getElementById('teamSelect').addEventListener('change', function() {
                var teamId = this.value;
                fetch('/schedule/tasks/' + teamId)
                    .then(res => res.json())
                    .then(events => {
                        calendar.removeAllEvents();
                        calendar.addEventSource(events);
                    })
                    .catch(err => console.error(err));
            });
        @endif
    });

    // Modals
    function openCreateModal(dateStr = null) {
        document.getElementById('createModal').classList.remove('hidden');
        document.getElementById('createModal').classList.add('flex');

        if (dateStr) {
            let input = document.querySelector('input[name="time"]');
            input.value = dateStr + "T09:00";
        }
    }

    function closeCreateModal() {
        document.getElementById('createModal').classList.add('hidden');
        document.getElementById('createModal').classList.remove('flex');
    }

    function openViewModal(event) {
        document.getElementById('viewModal').classList.remove('hidden');
        document.getElementById('viewModal').classList.add('flex');

        document.getElementById('viewTime').textContent = event.extendedProps.time || '';
        document.getElementById('viewAddress').textContent = event.extendedProps.address_name || '';
        document.getElementById('viewNumber').textContent = event.extendedProps.address_number || '';
        document.getElementById('viewZip').textContent = event.extendedProps.zipcode || '';
        document.getElementById('viewCity').textContent = event.extendedProps.city || '';
    }

    function closeViewModal() {
        document.getElementById('viewModal').classList.add('hidden');
        document.getElementById('viewModal').classList.remove('flex');
    }
</script>
