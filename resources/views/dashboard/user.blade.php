<x-layouts.dashboard>

    <head>
        <meta name="csrf-token" content="{{ csrf_token() }}">
    </head>

    <!-- Datum -->
    <h2 class="text-lg font-semibold text-gray-700">
        {{ \Carbon\Carbon::now()->locale('nl')->isoFormat('dd, DD.MM.YYYY') }}
    </h2>
    <div class="grid grid-cols-3 gap-6 w-50">

        <!-- Linkerzijde: Takenlijst -->
<!-- Linkerzijde: Takenlijst -->
<div class="col-span-2 flex flex-col min-h-full">
    {{-- Kop --}}
    <div>
        <h1 class="text-2xl font-bold mb-4">Taken van vandaag</h1>

        @if (session('success'))
            <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">
                {{ session('success') }}
            </div>
        @endif
    </div>

    {{-- Lijst neemt alle beschikbare ruimte --}}
    <div class="flex-1">
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
                        <tr class="cursor-pointer hover:bg-gray-100"
                            data-task-id="{{ $task->id }}"
                            data-address="{{ $task->address->street }} {{ $task->address->number }}"
                            data-zipcode="{{ $task->address->zipcode }}"
                            data-city="{{ $task->address->city }}"
                            data-time="{{ \Carbon\Carbon::parse($task->time)->format('Y-m-d H:i') }}"
                            data-status="{{ $task->status }}"
                            data-note="{{ $task->current_note ?? '' }}"
                            data-previous-notes='@json($task->previous_notes)'
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

    {{-- Status onderaan, geen extra scroll --}}
    @if ($tasksTotal > 0)
        <div style="margin-top: 320px;" class="bg-white p-4 rounded-lg shadow">
            <div>
                <h2 class="text-2xl font-semibold">Status</h2>
                <hr class="my-2 border-black">
            </div>
            <div class="w-full bg-gray-200 rounded-full h-3 mt-6">
                <div class="bg-green-500 h-3 rounded-full"
                     style="width: {{ ($tasksFinished / $tasksTotal) * 100 }}%"></div>
            </div>
            <p class="text-sm text-gray-600 mb-0">
                {{ $tasksFinished }} van {{ $tasksTotal }} taken voltooid
            </p>
        </div>
    @endif
</div>



        <!-- Rechterzijde: Formulier -->
        <div id="taskFormPanel" class="col-span-1 hidden">
            <div class="border rounded-lg shadow bg-white p-5">
                <!-- Adres + tijd -->
                <div class=" flex justify-between items-center">
                    <div>
                        <h2 id="taskAddressTitle" class="text-lg font-semibold"></h2>
                        <p id="taskZipCity" class="text-sm text-gray-600"></p>
                        <p id="taskTimeTitle" class="text-sm text-gray-600"></p>

                    </div>
                     <!-- Status -->
                    <div class="flex gap-2">
                         <img src="{{ asset('images/icon/info.svg') }}" alt="Information-icon" class="w-7 h-7 text-gray-500 mt-2">
                         <div>
                             <label class="block text-sm font-semibold text-gray-700 mb-1">Status</label>
                             <div class="flex items-center gap-2 text-gray-700">
                                 <span id="taskStatus">Open</span>
                             </div>
                         </div>
                    </div>
                   
                </div>
                <hr class="mb-8" >
                <form id="finishForm" method="POST" enctype="multipart/form-data" class="-mt-5">
                    @csrf

                   

                    <!-- Schade / Geen schade -->
                    <div class="mb-2">
                        <label class="block text-sm font-medium">Beoordeling</label>
                        <div class="flex gap-6 mt-1">
                            <label class="flex items-center gap-2">
                                <input type="radio" name="damage" value="none" id="damageNone">
                                <span>Geen schade</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="damage" value="damage" id="damageYes">
                                <span>Schade</span>
                            </label>
                        </div>
                        <p id="errorDamage" class="text-red-500 text-sm mt-1 hidden"></p>
                    </div>

                    <!-- Notitie veld -->
                    <div id="noteWrapper" class="mb-2 hidden">
                        <label class="block text-sm font-medium">Notitie</label>
                        <textarea name="note" rows="2" class="w-full border px-3 py-2 rounded"></textarea>
                        <p id="errorNote" class="text-red-500 text-sm mt-1 hidden"></p>
                    </div>

                    <!-- Cascade dropdowns -->
                    <div class="mb-2">
                        <label class="block text-sm font-medium">Type werk</label>
                        <select id="perceelSelect" class="w-full border px-3 py-2 rounded mt-1"></select>
                        <p id="errorPerceel" class="text-red-500 text-sm mt-1 hidden"></p>
                    </div>

                    <div class="mb-2">
                        <label class="block text-sm font-medium">Map</label>
                        <select id="regioSelect" class="w-full border px-3 py-2 rounded mt-1" disabled></select>
                        <p id="errorRegio" class="text-red-500 text-sm mt-1 hidden"></p>
                    </div>

                    <div class="mb-2 relative">
                        <label class="block text-sm font-medium">Adres</label>

                        <!-- Input die als combobox werkt -->
                        <div class="flex gap-2 items-center mt-1">

                            <input type="text" id="adresComboInput" placeholder="Zoek of kies adres..."
                                class="border px-3 py-2 rounded w-full" disabled autocomplete="off">
                                <button type="button" id="newAdresBtn"
                                class=" flex items-center justify-center bg-gray-200 w-8 h-8 rounded hover:bg-gray-300">+</button>
                        </div>

                        <!-- Hidden select (blijft voor backend) -->
                        <select id="adresSelect" name="folder" class="hidden"></select>

                        <!-- Custom dropdown -->
                        <div id="adresDropdown"
                            class="absolute z-10 mt-1 w-full bg-white border rounded shadow max-h-60 overflow-auto hidden">
                        </div>

                        
                        <button type="button" id="loadMoreAdressenBtn"
                            class="mt-2 text-blue-600 text-sm underline hidden">Meer laden...</button>
                        <p id="errorAdres" class="text-red-500 text-sm mt-1 hidden"></p>
                    </div>

                    <!-- Foto upload -->
                    <div class="mb-2">
                        <label class="block text-sm font-medium">Upload foto's (Max 3 foto's)</label>
                        <input type="file" id="photoUpload" name="photos[]" accept="image/*" multiple
                            class="w-full border px-3 py-2 rounded mt-1">
                        <p id="errorPhoto" class="text-red-500 text-sm mt-1 hidden"></p>
                       
                        <div id="photoPreview" class="flex gap-2 mt-2"></div>
                    </div>

                    <!-- Knop -->
                    <div class="mt-4">
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
        async function loadPercelen() {
            let res = await fetch("/dropbox/percelen");
            let data = await res.json();
            let perceelSelect = document.getElementById("perceelSelect");
            perceelSelect.innerHTML = "<option value=''>-- Kies type werk --</option>";

            data.forEach(p => {
                let displayName = p.name;
                if (p.name.toLowerCase().includes("perceel 1")) {
                    displayName = "Aansluitingen";
                } else if (p.name.toLowerCase().includes("perceel 2")) {
                    displayName = "Graafwerk";
                }

                perceelSelect.innerHTML +=
                    `<option value="${p.id}" data-type="${p.type}" data-original="${p.name}">${displayName}</option>`;

            });
        }

        async function loadRegios(id, type) {
            let res = await fetch(`/dropbox/regios?id=${encodeURIComponent(id)}&type=${encodeURIComponent(type)}`);
            let data = await res.json();
            let regioSelect = document.getElementById("regioSelect");
            regioSelect.innerHTML = "<option value=''>-- Kies map --</option>";

            // 🔹 Zoek expliciet naar "Webapp uploads"
            let webappOnly = data.filter(r =>
                r.name && r.name.toLowerCase().includes("webapp uploads")
            );

            if (webappOnly.length > 0) {
                // ✅ Toon alleen Webapp uploads
                webappOnly.forEach(r => {
                    regioSelect.innerHTML +=
                        `<option value="${r.path}" data-namespace="${r.namespace}">${r.name}</option>`;
                });

                regioSelect.disabled = false;

                // ✅ Automatisch selecteren
                regioSelect.value = webappOnly[0].path;
                loadAdressen(webappOnly[0].namespace, webappOnly[0].path);
            } else {
                // ❌ Geen Webapp uploads → zet melding in de dropdown
                regioSelect.innerHTML = "<option value=''>Geen 'Webapp uploads' map gevonden</option>";
                regioSelect.disabled = true;
            }
        }



        // --- Adressen laden ---
        let currentAdresCursor = null;
        let currentNamespaceId = null;
        let currentRegioPath = null;
        let allAdressen = [];

        async function loadAdressen(namespaceId, regioPath, cursor = null, search = "") {
            let url =
                `/dropbox/adressen?namespace_id=${encodeURIComponent(namespaceId)}&path=${encodeURIComponent(regioPath)}`;
            if (cursor) url += `&cursor=${encodeURIComponent(cursor)}`;
            if (search) url += `&search=${encodeURIComponent(search)}`;

            let res = await fetch(url);
            let data = await res.json();

            if (!cursor) allAdressen = [];
            allAdressen = allAdressen.concat(data.entries);

            renderAdresDropdown(allAdressen);

            document.getElementById("adresComboInput").disabled = false;

            currentAdresCursor = data.cursor;
            currentNamespaceId = namespaceId;
            currentRegioPath = regioPath;

            // "meer laden" knop tonen/verbergen
            let loadMoreBtn = document.getElementById("loadMoreAdressenBtn");
            if (data.has_more) {
                loadMoreBtn.classList.remove("hidden");
            } else {
                loadMoreBtn.classList.add("hidden");
            }
        }

        function renderAdresDropdown(adressen) {
            let dropdown = document.getElementById("adresDropdown");
            dropdown.innerHTML = "";

            adressen.forEach(a => {
                let div = document.createElement("div");
                div.className = "px-3 py-2 hover:bg-gray-100 cursor-pointer";
                div.textContent = a.name;
                div.dataset.path = a.path;
                div.dataset.namespace = a.namespace;
                div.addEventListener("click", () => {
                    document.getElementById("adresComboInput").value = a.name;

                    let select = document.getElementById("adresSelect");
                    select.innerHTML =
                        `<option value="${a.path}" data-namespace="${a.namespace}" selected>${a.name}</option>`;

                    dropdown.classList.add("hidden");
                });
                dropdown.appendChild(div);
            });
        }

        // Typen in combobox → zoekadressen
        document.getElementById("adresComboInput").addEventListener("input", (e) => {
            let term = e.target.value.trim();
            if (currentNamespaceId && currentRegioPath) {
                loadAdressen(currentNamespaceId, currentRegioPath, null, term);
            }
            document.getElementById("adresDropdown").classList.remove("hidden");
        });

        // Focus en klik buiten → dropdown sluiten
        document.getElementById("adresComboInput").addEventListener("focus", () => {
            document.getElementById("adresDropdown").classList.remove("hidden");
        });
        document.addEventListener("click", (e) => {
            if (!e.target.closest("#adresDropdown") && e.target.id !== "adresComboInput") {
                document.getElementById("adresDropdown").classList.add("hidden");
            }
        });

        // Meer adressen
        document.getElementById("loadMoreAdressenBtn").addEventListener("click", () => {
            if (currentAdresCursor) {
                loadAdressen(currentNamespaceId, currentRegioPath, currentAdresCursor);
            }
        });

        // Perceel → laad regio's
        document.getElementById("perceelSelect").addEventListener("change", (e) => {
            let opt = e.target.options[e.target.selectedIndex];
            let id = opt.value;
            let type = opt.dataset.type;
            if (id && type) {
                loadRegios(id, type);
            }
        });

        // Regio → laad adressen
        document.getElementById("regioSelect").addEventListener("change", (e) => {
            let namespaceId = e.target.options[e.target.selectedIndex].dataset.namespace;
            if (e.target.value) {
                loadAdressen(namespaceId, e.target.value);
            }
        });

        // Nieuwe adresmap
        // Nieuwe adresmap (altijd binnen Webapp uploads)
        document.getElementById("newAdresBtn").addEventListener("click", async () => {
            const regioSelect = document.getElementById("regioSelect");

            // Altijd Webapp uploads forceren
            const webappOption = [...regioSelect.options].find(opt =>
                opt.textContent.toLowerCase().includes("webapp uploads")
            );

            if (!webappOption) {
                alert("Map 'Webapp uploads' niet gevonden. Kies eerst perceel 1 of 2.");
                return;
            }

            const regioPath = webappOption.value;
            const namespaceId = webappOption.dataset.namespace;

            const name = prompt("Naam nieuwe adresmap:");
            if (!name) return;

            try {
                const res = await fetch("/dropbox/create-adres", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        namespace_id: namespaceId,
                        path: regioPath,
                        adres: name
                    })
                });

                const json = await res.json();

                if (res.status === 201 && json.success) {
                    alert(json.message || "Adresmap aangemaakt in Webapp uploads.");

                    await loadAdressen(namespaceId, regioPath, null, "");

                    const input = document.getElementById("adresComboInput");
                    const select = document.getElementById("adresSelect");
                    const drop = document.getElementById("adresDropdown");

                    input.value = json.folder.name;
                    select.innerHTML =
                        `<option value="${json.folder.path}" data-namespace="${json.folder.namespace}" selected>${json.folder.name}</option>`;
                    drop.classList.add("hidden");

                } else {
                    alert(json.message || "Kon adresmap niet maken.");
                }
            } catch (err) {
                console.error(err);
                alert("Serverfout bij map maken.");
            }
        });


        // Foto preview
        document.getElementById("photoUpload").addEventListener("change", (e) => {
            let files = [...e.target.files];
            let preview = document.getElementById("photoPreview");
            preview.innerHTML = "";

            if (files.length > 3) {
                alert("Je mag maximaal 3 foto's uploaden.");
                files = files.slice(0, 3);
            }

            files.forEach(file => {
                if (file.size > 5 * 1024 * 1024) {
                    alert(`Bestand ${file.name} is groter dan 5MB en wordt overgeslagen.`);
                    return;
                }
                let reader = new FileReader();
                reader.onload = (ev) => {
                    let img = document.createElement("img");
                    img.src = ev.target.result;
                    img.classList.add("h-16", "w-16", "object-cover", "rounded");
                    preview.appendChild(img);
                };
                reader.readAsDataURL(file);
            });
        });

        function showError(id, message) {
            let el = document.getElementById(id);
            if (el) {
                el.textContent = message;
                el.classList.remove("hidden");
            }
        }

        function clearErrors() {
            ["errorPerceel", "errorRegio", "errorAdres", "errorPhoto", "errorDamage", "errorNote"]
            .forEach(id => {
                let el = document.getElementById(id);
                if (el) {
                    el.textContent = "";
                    el.classList.add("hidden");
                }
            });
        }

        function validateForm() {
            clearErrors();

            let perceelSelect = document.getElementById("perceelSelect");
            let regioSelect = document.getElementById("regioSelect");
            let adresSelect = document.getElementById("adresSelect");
            let photoUpload = document.getElementById("photoUpload");
            let damageNone = document.getElementById("damageNone");
            let damageYes = document.getElementById("damageYes");
            let noteField = document.querySelector('#finishForm textarea[name="note"]');

            let isValid = true;

            if (!perceelSelect.value) {
                showError("errorPerceel", "Kies een perceel.");
                isValid = false;
            }

            if (!regioSelect.value) {
                showError("errorRegio", "Kies een regio (Webapp uploads).");
                isValid = false;
            }

            if (!adresSelect.value) {
                showError("errorAdres", "Kies of maak een adresmap.");
                isValid = false;
            }

            if (photoUpload.files.length === 0) {
                showError("errorPhoto", "Upload minstens 1 foto.");
                isValid = false;
            }

            if (!damageNone.checked && !damageYes.checked) {
                showError("errorDamage", "Selecteer of er schade is of niet.");
                isValid = false;
            }

            if (damageYes.checked && !noteField.value.trim()) {
                showError("errorNote", "Notitie is verplicht bij schade.");
                isValid = false;
            }

            return isValid;
        }



        // ✅ Nieuw: finish + upload gecombineerd
       // ✅ Nieuw: finish + upload gecombineerd
document.getElementById("finishForm").addEventListener("submit", async (e) => {
    e.preventDefault();

    // ✅ eerst valideren
    if (!validateForm()) return;

    let form = e.target;
    let taskId = form.action.match(/tasks\/(\d+)/)?.[1];
    let formData = new FormData(form);

    // Upload foto's indien aanwezig
    let files = [...document.getElementById("photoUpload").files].slice(0, 3);
    if (files.length > 0) {
        let adresSelect = document.getElementById("adresSelect");
        let namespaceId = adresSelect.options[adresSelect.selectedIndex]?.dataset.namespace;
        let uploadData = new FormData();

        files.forEach(f => {
            if (f.size <= 5 * 1024 * 1024) {
                uploadData.append("photos[]", f);
            }
        });

        uploadData.append("namespace_id", namespaceId);
        uploadData.append("path", adresSelect.value);

        // Gebruik de "echte" backend naam (Perceel 1 of Perceel 2)
        let perceelOriginal = document.getElementById("perceelSelect").selectedOptions[0]?.dataset.original;
        if (perceelOriginal) {
            uploadData.append("perceel_name", perceelOriginal);
        }

        let resUpload = await fetch(`/tasks/${taskId}/upload-photo`, {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
            },
            body: uploadData
        });

        if (!resUpload.ok) {
            alert("Fout bij uploaden");
            return;
        }
    }

    // Daarna taakstatus aanpassen
    let resFinish = await fetch(`/tasks/${taskId}/finish`, {
        method: "POST",
        headers: {
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
            "Accept": "application/json"
        },
        body: formData
    });

    if (resFinish.ok) {
        const json = await resFinish.json();
        alert("Taak succesvol afgerond!");

        // Status in tabel bijwerken
        const row = document.querySelector(`tr[data-task-id="${taskId}"]`);
        if (row) {
            row.dataset.status = json.status;
            row.querySelector("td:nth-child(3) span").textContent = json.status;

            // ✅ Kleur realtime aanpassen
            const statusSpan = row.querySelector("td:nth-child(3) span");
            statusSpan.className = "px-2 py-1 rounded text-sm font-semibold"; // reset basis classes

            switch (json.status) {
                case "open":
                    statusSpan.classList.add("bg-gray-200", "text-gray-800");
                    break;
                case "in behandeling":
                    statusSpan.classList.add("bg-yellow-200", "text-yellow-800");
                    break;
                case "finished":
                    statusSpan.classList.add("bg-green-200", "text-green-800");
                    break;
                default:
                    statusSpan.classList.add("bg-gray-200", "text-gray-800");
            }
        }

        // ✅ Formulier leegmaken
        form.reset();
        document.getElementById("photoPreview").innerHTML = "";
        clearErrors();

        // Ook handmatig velden legen die niet door reset() gecleared worden
        document.getElementById('taskStatus').textContent = "";
        document.getElementById('taskAddressTitle').textContent = "";
        document.getElementById('taskZipCity').textContent = "";
        document.getElementById('taskTimeTitle').textContent = "";

        // ✅ Paneel sluiten
        document.getElementById("taskFormPanel").classList.add("hidden");
    } else {
        alert("Fout bij afronden van taak");
    }
});


        // Bij openen taakformulier
        function openTaskForm(taskId, address, time, status, note) {
            loadPercelen();

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

            // ✅ altijd reset
            damageNone.checked = false;
            damageYes.checked = false;
            noteWrapper.classList.add('hidden');
            noteField.value = '';

            if (status === 'finished') {
                form.action = "";
                finishButton.classList.add('hidden');
                damageNone.disabled = true;
                damageYes.disabled = true;
                noteField.setAttribute('readonly', true);
            } else {
                form.action = `/tasks/${taskId}/finish`;
                finishButton.classList.remove('hidden');
                damageNone.disabled = false;
                damageYes.disabled = false;
                noteField.removeAttribute('readonly');
            }

            // Event listeners (herstel)
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

        // Openen via rij-klik
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
