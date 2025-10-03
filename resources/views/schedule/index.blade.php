<x-layouts.dashboard>
    <div class="md:p-6">

        <h1 class="text-2xl font-bold mb-8 text-center md:text-left md:mb-4">Planning</h1>

        <!-- Flash message -->
        @if (session('success'))
            <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">
                {{ session('success') }}
            </div>
        @endif

        <div class="flex items-center justify-between mb-6">

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

            @if (Auth::user()->role === 'admin')
                <button type="button" onclick="openCreateModal()"
                    class="bg-[#283142] text-white px-2 py-2 mt-2  rounded hover:bg-[#B51D2D]">
                    Nieuwe Taak
                </button>
            @endif
        </div>

        <!-- Kalender -->
        <div id="calendar" class="mb-6 w-full min-h-[700px]"></div>

        <!-- Modal voor nieuwe taak -->
        @if (Auth::user()->role === 'admin')
            <div id="createModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
                <div class="bg-white rounded-lg shadow-lg w-full max-w-lg p-6">
                    <h2 class="text-lg font-semibold mb-4">Nieuwe Taak Toevoegen</h2>

                    <form id="taskForm" action="{{ route('schedule.store') }}" method="POST" class="space-y-4">
                        @csrf
                        @if (Auth::user()->role === 'admin')
                            <div>
                                <label class="block text-sm font-medium">Ploeg</label>
                                <select id="taskTeamSelect" name="team_id" required
                                    class="w-full border px-3 py-2 rounded">
                                    @foreach ($teams as $team)
                                        <option value="{{ $team->id }}">{{ $team->name }}</option>
                                    @endforeach
                                </select>

                            </div>
                        @endif

                        <div>
                            <label class="block text-sm font-medium">Tijdstip (*)</label>
                            <input type="datetime-local" name="time" required
                                class="w-full border px-3 py-2 rounded">
                        </div>

                        <!-- Waarschuwing voor dubbele tijd -->
                        <div id="timeWarning" class="text-red-600 mb-2 hidden">
                            Er is al een taak ingepland op dit tijdstip!
                        </div>

                        <div>
                            <label class="block text-sm font-medium">Adres (*)</label>
                            <input list="addresses" name="address_name" id="createAddress"
                                class="w-full border px-3 py-2 rounded" placeholder="Typ hier een straat..."
                                autocomplete="new-password" required>
                            <!-- Suggesties dropdown -->
                            <div id="addressSuggestions"
                                class="absolute z-50 bg-white border rounded shadow mt-1 hidden max-h-40 overflow-y-auto">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium">Nummer (*)</label>
                            <input type="text" name="address_number" id="createNumber"
                                class="w-full border px-3 py-2 rounded" autocomplete="new-password" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium">Postcode (*)</label>
                            <input type="text" name="address_zipcode" id="createZip"
                                class="w-full border px-3 py-2 rounded" autocomplete="new-password" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium">Stad (*)</label>
                            <input type="text" name="address_city" id="createCity"
                                class="w-full border px-3 py-2 rounded" autocomplete="new-password" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium">Opmerking</label>
                            <div id="createNoteDisplay"
                                class="w-full border px-3 py-2 rounded bg-gray-100 min-h-[60px]"></div>
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
        @endif

        <!-- Modal om taak te bekijken -->
        <!-- Modal om taak te bekijken -->
        <div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-lg p-6">
                <!-- Titel + 3 puntjes rechts -->
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold">Taak Details</h2>

                    @if (Auth::user()->role === 'admin')
                        <div class="relative">
                            <!-- 3 puntjes knop -->
                            <button id="actionsMenuBtn" type="button"
                                class="px-2 py-1 text-black font-bold text-2xl hover:bg-gray-200 rounded">
                                ⋮
                            </button>


                            <!-- Dropdown menu -->
                            <div id="actionsMenu"
                                class="absolute right-0 mt-2 w-32 bg-white border rounded shadow-lg hidden z-50">
                                <button id="editTaskBtn"
                                    class="block w-full text-left px-4 py-2 hover:bg-gray-100 text-sm text-gray-700">
                                    Bewerken
                                </button>
                                <form id="deleteTaskForm" method="POST" action="" class="block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="w-full text-left px-4 py-2 hover:bg-red-100 text-sm text-red-600">
                                        Verwijderen
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="space-y-2">
                    <p><strong>Tijdstip:</strong> <span id="viewTime"></span></p>
                    <p><strong>Adres:</strong> <span id="viewAddress"></span></p>
                    <p><strong>Nummer:</strong> <span id="viewNumber"></span></p>
                    <p><strong>Postcode:</strong> <span id="viewZip"></span></p>
                    <p><strong>Stad:</strong> <span id="viewCity"></span></p>
                    <p><strong>Opmerking:</strong> <span id="viewNote"></span></p>
                    <div id="photosSection">
                        <strong id="photosLabel">Fotos:</strong>
                        <div id="viewPhotos" class="flex flex-wrap gap-2 mt-2"></div>
                    </div>
                </div>

                <div class="flex justify-end mt-4 gap-2">
                    <button type="button" onclick="closeViewModal()"
                        class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">
                        Sluiten
                    </button>
                </div>
            </div>
        </div>


    </div>
    <!-- Foto Lightbox -->
    <div id="photoModal" class="fixed inset-0 bg-black bg-opacity-75 hidden items-center justify-center z-50">
        <!-- Sluitknop -->
        <span onclick="closePhotoModal()"
            class="absolute top-5 right-8 text-white text-3xl cursor-pointer">&times;</span>

        <!-- Grote foto -->
        <img id="photoModalImg" src=""
            class="max-h-[90%] max-w-[90%] rounded shadow-lg border-4 border-white" />
    </div>

</x-layouts.dashboard>


<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>


<script>
    // Toggle 3 puntjes menu in taakdetails
    document.addEventListener('click', (e) => {
        const menuBtn = document.getElementById('actionsMenuBtn');
        const menu = document.getElementById('actionsMenu');

        if (menuBtn && menu) {
            if (menuBtn.contains(e.target)) {
                menu.classList.toggle('hidden'); // open/dicht bij klik
            } else if (!menu.contains(e.target)) {
                menu.classList.add('hidden'); // klik buiten -> sluit
            }
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');

        // 👉 Detecteer mobiel
        var isMobile = window.innerWidth < 768;

        var calendar = new FullCalendar.Calendar(calendarEl, {
            // 👇 Gebruik dag-weergave op mobiel, anders maand
            initialView: isMobile ? 'timeGridDay' : 'dayGridMonth',
            selectable: true,
            headerToolbar: {
                left: 'prev,next',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            aspectRatio: isMobile ? 0.7 : 1.35,
            contentHeight: isMobile ? 'auto' : '700px',
            expandRows: true,

            dateClick: function(info) {
                openCreateModal(info.dateStr);
            },
            eventClick: function(info) {
                openViewModal(info.event);
            },

            events: '/schedule/tasks/{{ Auth::user()->role === 'admin' ? $defaultTeamId ?? Auth::id() : Auth::id() }}'
        });




        calendar.render();
        // === Tailwind op FullCalendar-toolbar toveren (responsive) ===
        function applyTailwindToToolbar() {
            const root = document.getElementById('calendar');
            const toolbar = root?.querySelector('.fc-header-toolbar');
            if (!toolbar) return;

            // Layout (mobiel = stacked, desktop = row)
            toolbar.classList.add(
                'flex', 'flex-col', 'items-center', 'gap-4',
                'p-4', 'rounded-lg', 'mb-6',
                'md:flex-row', 'md:justify-between'
            );

            // Titel (extra groot)
            const title = toolbar.querySelector('.fc-toolbar-title');
            if (title) {
                title.classList.add(
                    'text-xl', 'font-bold', 'text-center',
                    'md:text-5xl', 'md:font-extrabold', 'md:flex-1', 'md:text-center'
                );
            }

            // Chunks (prev/next, title, view-switcher)
            const chunks = toolbar.querySelectorAll('.fc-toolbar-chunk');
            if (chunks.length === 3) {
                // prev/next
                chunks[0].classList.add('flex', 'flex-row', 'gap-4', 'order-2', 'md:order-1');

                // title midden (desktop)
                chunks[1].classList.add('md:flex', 'order-1', 'md:order-2', 'justify-center', 'flex-1');

                // view-switcher rechts
                chunks[2].classList.add('flex', 'flex-row', 'gap-4', 'order-3');
            }

            // Buttons stylen (helemaal groot)
            toolbar.querySelectorAll('.fc-button').forEach(btn => {
                btn.classList.add(
                    'text-white', '!border', '!border-gray-200',
                    'px-4', 'py-3', 'rounded-lg', 'text-base', 'font-semibold',
                    'transition', 'duration-200',
                    'bg-[#283142]', 'hover:!bg-[#B51D2D]',
                    'focus:!outline-none', 'focus:!ring-2', 'focus:!ring-[#B51D2D]/40',
                    // desktop/laptop extreem groot
                    'md:px-20', 'md:py-10', 'md:text-3xl'
                );
            });

            // Button-groepen spacing
            toolbar.querySelectorAll('.fc-button-group').forEach(g => {
                g.classList.add('flex', 'flex-row', 'space-x-4');
            });
        }

        calendar.render();
        applyTailwindToToolbar();

       @if (Auth::user()->role === 'admin')
    document.getElementById('teamSelect').addEventListener('change', function() {
        var teamId = this.value;

        // Eerst alle oude events verwijderen
        calendar.removeAllEventSources();

        // 👉 FullCalendar zelf laten fetchen via de URL
        calendar.addEventSource('/schedule/tasks/' + teamId);
    });
@endif

    });

    function openCreateModal(dateStr = null) {
        document.getElementById('createModal').classList.remove('hidden');
        document.getElementById('createModal').classList.add('flex');

        // Reset velden
        document.getElementById('createAddress').value = '';
        document.getElementById('createNumber').value = '';
        document.getElementById('createZip').value = '';
        document.getElementById('createCity').value = '';
        document.getElementById('createNoteDisplay').textContent = '';
        document.getElementById('timeWarning').classList.add('hidden');

        const teamSelect = document.getElementById('teamSelect');
        const taskTeamSelect = document.getElementById('taskTeamSelect');
        if (teamSelect && taskTeamSelect) {
            taskTeamSelect.value = teamSelect.value;
        }


        if (dateStr) {
            let input = document.querySelector('input[name="time"]');
            input.value = dateStr + "T09:00";
        }

        // Functie om adres-velden en note in te vullen
        function fillAddressFields() {
            let street = document.getElementById('createAddress').value;

            if (street) {
                fetch(`/schedule/address-details?street=${encodeURIComponent(street)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.address) {
                            document.getElementById('createNumber').value = data.address.number ?? '';
                            document.getElementById('createZip').value = data.address.zipcode ?? '';
                            document.getElementById('createCity').value = data.address.city ?? '';
                            document.getElementById('createNoteDisplay').textContent = data.note ?? '';
                        } else {
                            document.getElementById('createNumber').value = '';
                            document.getElementById('createZip').value = '';
                            document.getElementById('createCity').value = '';
                            document.getElementById('createNoteDisplay').textContent = '';
                        }
                    });
            }
        }

        // Event listener voor adresselectie
        document.getElementById('createAddress').addEventListener('change', fillAddressFields);

        // Live check bij submit
        document.querySelector('#createModal form').addEventListener('submit', function(e) {
            e.preventDefault();
            let timeInput = this.querySelector('input[name="time"]');
            let selectedTime = timeInput.value;

            // Ploeg-id ophalen (admin)
            let teamSelect = this.querySelector('select[name="team_id"]');
            let teamId = teamSelect ? teamSelect.value : {{ Auth::id() }};

            fetch(`/schedule/check-time?time=${encodeURIComponent(selectedTime)}&team_id=${teamId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.exists) {
                        document.getElementById('timeWarning').classList.remove('hidden');
                    } else {
                        document.getElementById('timeWarning').classList.add('hidden');
                        this.submit();
                    }
                })
                .catch(err => console.error(err));
        });
        setupAddressAutocomplete();

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
        document.getElementById('viewNote').textContent = event.extendedProps.note || '';
        // 🔥 Status tonen
        const detailsContainer = document.querySelector("#viewModal .space-y-2");

        // eerst oude status-elementen verwijderen
        detailsContainer.querySelectorAll(".status-badge").forEach(el => el.remove());

        let statusEl = document.createElement('p');
        statusEl.classList.add("status-badge");
        statusEl.innerHTML = `<strong>Status:</strong> 
    <span class="${event.extendedProps.statusColor} px-2 py-1 rounded text-xs font-semibold">
        ${event.extendedProps.status}
    </span>`;
        detailsContainer.prepend(statusEl);

        // 👇 Foto’s dynamisch vullen (oude vs nieuwe)
        const photosContainer = document.getElementById('viewPhotos');
        const photosLabel = document.getElementById('photosLabel');
        photosContainer.innerHTML = ""; // reset

        // 👉 Als Herstelploeg 1 of 2 → verberg standaard "Fotos:" label
        if (event.extendedProps.team_name === "Herstelploeg 1" || event.extendedProps.team_name === "Herstelploeg 2") {
            photosLabel.style.display = "none";

            // Oude foto's
            if (event.extendedProps.previous_photos && event.extendedProps.previous_photos.length > 0) {
                let oldTitle = document.createElement('p');
                oldTitle.textContent = "Oude foto's:";
                oldTitle.classList.add("font-semibold", "mt-2");
                photosContainer.appendChild(oldTitle);

                event.extendedProps.previous_photos.forEach(path => {
                    let img = document.createElement('img');
                    img.src = `/dropbox/preview?path=${encodeURIComponent(path)}`;
                    img.classList.add("w-24", "h-24", "object-cover", "rounded", "border", "cursor-pointer",
                        "mr-2", "mt-1");
                    img.onclick = () => openPhotoModal(img.src);
                    photosContainer.appendChild(img);
                });
            }

            // Nieuwe foto's
            if (event.extendedProps.current_photos && event.extendedProps.current_photos.length > 0) {
                let newTitle = document.createElement('p');
                newTitle.textContent = "Nieuwe foto's:";
                newTitle.classList.add("font-semibold", "mt-4");
                photosContainer.appendChild(newTitle);

                event.extendedProps.current_photos.forEach(path => {
                    let img = document.createElement('img');
                    img.src = `/dropbox/preview?path=${encodeURIComponent(path)}`;
                    img.classList.add("w-24", "h-24", "object-cover", "rounded", "border", "cursor-pointer",
                        "mr-2", "mt-1");
                    img.onclick = () => openPhotoModal(img.src);
                    photosContainer.appendChild(img);
                });
            }

            if (
                (!event.extendedProps.previous_photos || event.extendedProps.previous_photos.length === 0) &&
                (!event.extendedProps.current_photos || event.extendedProps.current_photos.length === 0)
            ) {
                photosContainer.innerHTML = "<p class='text-gray-500'>Geen foto's</p>";
            }

        } else {
            // 👉 Voor admin en andere teams → toon standaard "Fotos:"
            photosLabel.style.display = "inline";

            if (event.extendedProps.current_photos && event.extendedProps.current_photos.length > 0) {
                event.extendedProps.current_photos.forEach(path => {
                    let img = document.createElement('img');
                    img.src = `/dropbox/preview?path=${encodeURIComponent(path)}`;
                    img.classList.add("w-24", "h-24", "object-cover", "rounded", "border", "cursor-pointer",
                        "mr-2", "mt-1");
                    img.onclick = () => openPhotoModal(img.src);
                    photosContainer.appendChild(img);
                });
            } else {
                photosContainer.innerHTML = "<p class='text-gray-500'>Geen foto's</p>";
            }
        }

        // 👇 Notities dynamisch vullen (oude vs nieuwe)
        const noteContainer = document.getElementById('viewNote');
        noteContainer.innerHTML = ""; // reset

        // 👉 Oude notities (alleen voor herstelploegen of admin)
        if (event.extendedProps.previous_notes && event.extendedProps.previous_notes.length > 0) {
            let oldNoteTitle = document.createElement('p');
            oldNoteTitle.textContent = "Oude notities:";
            oldNoteTitle.classList.add("font-semibold", "mt-2");
            noteContainer.appendChild(oldNoteTitle);

            event.extendedProps.previous_notes.forEach(note => {
                let p = document.createElement('p');
                p.textContent = note;
                p.classList.add("text-sm", "text-gray-700", "ml-2");
                noteContainer.appendChild(p);
            });
        }

        // 👉 Huidige notitie
        if (event.extendedProps.current_note) {
            let newNoteTitle = document.createElement('p');
            newNoteTitle.textContent = "Huidige notitie:";
            newNoteTitle.classList.add("font-semibold", "mt-4");
            noteContainer.appendChild(newNoteTitle);

            let p = document.createElement('p');
            p.textContent = event.extendedProps.current_note;
            p.classList.add("text-sm", "text-gray-800", "ml-2");
            noteContainer.appendChild(p);
        }

        // 👉 Geen notities
        if (
            (!event.extendedProps.previous_notes || event.extendedProps.previous_notes.length === 0) &&
            !event.extendedProps.current_note
        ) {
            noteContainer.innerHTML = "<p class='text-gray-500'>Geen notities</p>";
        }




        const editBtn = document.getElementById('editTaskBtn');
        const deleteForm = document.getElementById('deleteTaskForm');

        editBtn.onclick = function() {
            window.location.href = `/schedule/${event.id}/edit?redirect=` + encodeURIComponent(window.location
                .href);
        };

        deleteForm.action = `/schedule/${event.id}`;

        @if (Auth::user()->role === 'admin')
            editBtn.classList.remove('hidden');
            deleteForm.classList.remove('hidden');
        @else
            editBtn.classList.add('hidden');
            deleteForm.classList.add('hidden');
        @endif


        deleteForm.action = `/schedule/${event.id}`;

        @if (Auth::user()->role === 'admin')
            editBtn.classList.remove('hidden');
            deleteForm.classList.remove('hidden');
        @else
            editBtn.classList.add('hidden');
            deleteForm.classList.add('hidden');
        @endif

    }

    function closeViewModal() {
        document.getElementById('viewModal').classList.add('hidden');
        document.getElementById('viewModal').classList.remove('flex');
    }

    function openPhotoModal(src) {
        document.getElementById('photoModalImg').src = src;
        document.getElementById('photoModal').classList.remove('hidden');
        document.getElementById('photoModal').classList.add('flex');
    }

    function closePhotoModal() {
        document.getElementById('photoModal').classList.add('hidden');
        document.getElementById('photoModal').classList.remove('flex');
    }

    document.getElementById('taskForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch(this.action, {
            method: 'POST',
            body: formData
        }).then(res => {
            if (res.ok) {
                closeCreateModal();
                // herlaad events voor huidig geselecteerd team
                const teamId = document.getElementById('teamSelect')?.value || {{ $defaultTeamId }}
                calendar.removeAllEvents();
                calendar.addEventSource('/schedule/tasks/' + teamId);
            }
        });
    });

    function setupAddressAutocomplete() {
        const input = document.getElementById('createAddress');
        const suggestions = document.getElementById('addressSuggestions');

        input.addEventListener('input', function() {
            const query = this.value.trim();
            if (query.length < 2) {
                suggestions.classList.add('hidden');
                return;
            }

            fetch(`/schedule/address-suggest?query=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    suggestions.innerHTML = "";
                    if (data.length === 0) {
                        suggestions.classList.add('hidden');
                        return;
                    }

                    data.forEach(addr => {
                        const option = document.createElement('div');
                        option.className = "px-3 py-2 hover:bg-gray-100 cursor-pointer";
                        option.textContent =
                            `${addr.street} ${addr.number}, ${addr.zipcode} ${addr.city}`;
                        option.onclick = () => {
                            input.value = addr.street;
                            document.getElementById('createNumber').value = addr.number ?? '';
                            document.getElementById('createZip').value = addr.zipcode ?? '';
                            document.getElementById('createCity').value = addr.city ?? '';
                            document.getElementById('createNoteDisplay').textContent = addr
                                .note ?? '';
                            suggestions.classList.add('hidden');
                        };
                        suggestions.appendChild(option);
                    });

                    suggestions.classList.remove('hidden');
                });
        });

        // klik buiten → sluit dropdown
        document.addEventListener('click', (e) => {
            if (!suggestions.contains(e.target) && e.target !== input) {
                suggestions.classList.add('hidden');
            }
        });
    }
</script>
<style>
    @media (max-width: 768px) {
        #calendar {
            min-height: 80vh !important;
            font-size: 14px !important;
        }

        .fc-toolbar-title {
            font-size: 1.25rem !important;
            /* text-xl */
            font-weight: bold !important;
            text-align: center !important;
        }

        .fc-daygrid-day-frame,
        .fc-timegrid-slot {
            min-height: 3rem !important;
            /* hogere cellen */
        }

        .fc-daygrid-day {
            padding: 8px !important;
            /* extra ruimte in maandweergave */
        }
    }
</style>
