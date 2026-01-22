<x-layouts.dashboard>

    <head>
        <meta name="csrf-token" content="{{ csrf_token() }}">



    </head>


    <!-- Datum -->
    <h2 class="text-sm text-center md:text-left md:text-base lg:text-lg font-semibold text-gray-700 mb-4">
        {{ \Carbon\Carbon::now()->locale('nl')->isoFormat('dd, DD.MM.YYYY') }}
    </h2>

    <!-- responsive grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">

        <!-- Linkerzijde: Takenlijst -->
        <div class="lg:col-span-2 flex flex-col min-h-full">
            {{-- Kop --}}
            <div>
                <h1 class="text-lg text-center md:text-left md:text-xl lg:text-2xl font-bold mb-4">Taken van vandaag</h1>

                @if (session('success'))
                    <div class="mb-4 p-3 bg-green-100 text-green-700 rounded text-sm md:text-base">
                        {{ session('success') }}
                    </div>
                @endif
            </div>

            {{-- Lijst --}}
            <div class="flex-1">
                @if ($tasksToday->isEmpty())
                    <p class="text-sm md:text-base">Geen taken vandaag!</p>
                @else
                    <table class="w-full text-sm md:text-base border-collapse">
                        <thead class="hidden md:table-header-group">
                            <tr class="bg-gray-100">
                                <th class="border px-2 md:px-4 lg:px-6 py-2 text-left">Tijd</th>
                                <th class="border px-2 md:px-4 lg:px-6 py-2 text-left">Adres</th>
                                <th class="border px-2 md:px-4 lg:px-6 py-2 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tasksToday as $task)
                                @php
                                    $colors = [
                                        'open' => 'bg-gray-200 text-gray-800',
                                        'in behandeling' => 'bg-yellow-200 text-yellow-800',
                                        'finished' => 'bg-green-200 text-green-800',
                                    ];
                                    $statusColor = $colors[$task->status] ?? 'bg-gray-200 text-gray-800';
                                @endphp
                                <tr class="cursor-pointer hover:bg-gray-50
                                           block md:table-row mb-3 md:mb-0 rounded-lg md:rounded-none shadow md:shadow-none border md:border-0 p-3 md:p-0"
                                    data-task-id="{{ $task->id }}"
                                    data-address="{{ $task->address->street }} {{ $task->address->number }}"
                                    data-zipcode="{{ $task->address->zipcode }}" data-city="{{ $task->address->city }}"
                                    data-time="{{ \Carbon\Carbon::parse($task->time)->format('d-m-Y H:i') }}"
                                    data-status="{{ $task->status }}" data-note="{{ $task->current_note ?? '' }}"
                                    data-previous-notes='@json($task->previous_notes)'
                                    data-damage="{{ $task->note ? 'damage' : 'none' }}">

                                    <!-- Tijd -->
                                    <td
                                        class="block md:table-cell md:border px-0 md:px-4 lg:px-6 py-1 md:py-3 font-semibold md:font-normal">
                                        {{ \Carbon\Carbon::parse($task->time)->format('H:i') }}
                                    </td>

                                    <!-- Adres -->
                                    <td
                                        class="block md:table-cell md:border px-0 md:px-4 lg:px-6 py-1 md:py-3 whitespace-normal break-words">
                                        {{ $task->address->street }} {{ $task->address->number }},
                                        {{ $task->address->zipcode }} {{ $task->address->city }}
                                    </td>

                                    <!-- Status -->
                                    <td
                                        class="block md:table-cell md:border px-0 md:px-4 lg:px-6 py-1 md:py-3 capitalize mt-2 md:mt-0">
                                        <span
                                            class="px-2 py-1 rounded text-xs md:text-sm font-semibold {{ $statusColor }}">
                                            {{ $task->status }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- Status --}}
            @if ($tasksTotal > 0)
                <div class="mt-6 lg:mt-[320px] bg-white p-4 md:p-6 rounded-lg shadow">
                    <div>
                        <h2 class="text-base md:text-lg lg:text-2xl font-semibold">Status</h2>
                        <hr class="my-2 border-black">
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3 mt-4">
                        <div class="bg-green-500 h-3 rounded-full"
                            style="width: {{ ($tasksFinished / $tasksTotal) * 100 }}%"></div>
                    </div>
                    <p class="text-xs md:text-sm lg:text-base text-gray-600 mb-0">
                        {{ $tasksFinished }} van {{ $tasksTotal }} taken voltooid
                    </p>
                </div>
            @endif
        </div>

        <!-- Rechterzijde: Formulier -->
        <div id="taskFormPanel" class="hidden mt-6 lg:mt-0 lg:col-span-1">
            <div class="border rounded-lg shadow bg-white p-4 md:p-6">
                <!-- Adres + tijd -->
                <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                    <div>
                        <h2 id="taskAddressTitle" class="text-base md:text-lg font-semibold"></h2>
                        <p id="taskZipCity" class="text-sm md:text-base text-gray-600"></p>
                        <p id="taskTimeTitle" class="text-sm md:text-base text-gray-600"></p>
                    </div>
                    <!-- Status -->
                    <div class="flex gap-2">
                        <img src="{{ asset('images/icon/info.svg') }}" alt="Information-icon"
                            class="w-6 h-6 md:w-7 md:h-7 text-gray-500 mt-1 md:mt-2">
                        <div>
                            <label class="block text-xs md:text-sm font-semibold text-gray-700 mb-1">Status</label>
                            <div class="flex items-center gap-2 text-gray-700 text-sm md:text-base">
                                <span id="taskStatus">Open</span>
                            </div>
                        </div>
                    </div>
                </div>
                <hr class="my-4">

                <form id="finishForm" method="POST" enctype="multipart/form-data" class="space-y-3 md:space-y-5">
                    @csrf

                    <!-- Schade / Geen schade -->
                    <div>
                        <label class="block text-sm font-medium">Herstel?</label>
                        <div class="flex flex-col sm:flex-row gap-2 sm:gap-6 mt-1">
                            <label class="flex items-center gap-2">
                                <input type="radio" name="damage" value="none" id="damageNone">
                                <span>Nee</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="damage" value="damage" id="damageYes">
                                <span>Ja</span>
                            </label>
                        </div>
                        <p id="errorDamage" class="text-red-500 text-xs md:text-sm mt-1 hidden"></p>
                    </div>

                    <!-- Notitie veld -->
                    <div id="noteWrapper" class="hidden">
                        <label class="block text-sm font-medium">Notitie</label>
                        <textarea name="note" rows="2" class="w-full border px-3 py-2 rounded text-sm md:text-base"></textarea>
                        <p id="errorNote" class="text-red-500 text-xs md:text-sm mt-1 hidden"></p>
                    </div>

                    <!-- Cascade dropdowns -->
                    <div>
    <label class="block text-sm font-medium">Type werk</label>
    <select id="perceelSelect" class="w-full border px-3 py-2 rounded mt-1 text-sm md:text-base">
        <option value="">-- Laden... --</option>
    </select>
    <p id="errorPerceel" class="text-red-500 text-xs md:text-sm mt-1 hidden"></p>
</div>

                    


                    <!-- Foto upload -->
                    <div>
                        <label class="block text-sm font-medium">Upload foto's (Max 30 foto's)</label>
                        <input type="file" id="photoUpload" name="photos[]" accept="image/*" multiple
                            class="w-full border px-3 py-2 rounded mt-1 text-sm md:text-base">
                        <p id="errorPhoto" class="text-red-500 text-xs md:text-sm mt-1 hidden"></p>
                        <div id="photoPreview" class="flex flex-wrap gap-2 mt-2"></div>
                    </div>

                    <!-- Knop -->
                    <div>
                        <button type="submit" id="finishButton"
                            class="w-full bg-[#283142] text-white py-2 rounded hover:bg-[#B51D2D] transition text-sm md:text-base">
                            Voltooien
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- 🔹 Algemene Upload Progress Popup -->
    <div id="uploadProgressPopup"
        class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 shadow-xl w-80 text-center">
            <h2 class="text-lg font-semibold mb-4">Bezig met uploaden...</h2>
            <div class="w-full bg-gray-200 rounded-full h-3 mb-3">
                <div id="uploadProgressBar" class="bg-blue-600 h-3 rounded-full" style="width: 0%;"></div>
            </div>
            <p id="uploadProgressText" class="text-sm text-gray-600">0%</p>
        </div>
    </div>
    <!-- 🔹 Lightbox met navigatie -->
    <div id="photoLightbox"
        class="fixed inset-0 bg-black bg-opacity-80 hidden items-center justify-center z-50 select-none">

        <!-- Sluitknop -->
        <span id="closeLightbox" class="absolute top-5 right-8 text-white text-4xl cursor-pointer">&times;</span>

        <!-- Pijlen -->
        <button id="prevPhoto"
            class="absolute left-5 text-white text-5xl p-2 bg-black bg-opacity-40 rounded-full hover:bg-opacity-70">
            &#10094;
        </button>
        <button id="nextPhoto"
            class="absolute right-5 text-white text-5xl p-2 bg-black bg-opacity-40 rounded-full hover:bg-opacity-70">
            &#10095;
        </button>

        <!-- Grote afbeelding -->
        <img id="lightboxImage" src=""
            class="max-h-[90%] max-w-[90%] rounded shadow-lg border-4 border-white transition-transform duration-300" />
    </div>

    @push('scripts')
        <script>
            let overlay = null;

            function showGlobalUploadProgress(current, total, filename) {
                if (!overlay) {
                    overlay = document.createElement("div");
                    overlay.className = "fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center";
                    overlay.innerHTML = `
            <div class="bg-white p-5 rounded-xl shadow-xl text-center">
                <h3 class="text-lg font-bold mb-2">Foto's worden geüpload...</h3>
                <p id="uploadStatus" class="text-sm text-gray-600 mb-2"></p>
                <div class="w-64 bg-gray-300 rounded h-3">
                    <div id="uploadBar" class="h-3 bg-green-500 rounded" style="width: 0%"></div>
                </div>
            </div>`;
                    document.body.appendChild(overlay);
                }

                const percent = Math.floor((current / total) * 100);
                document.getElementById("uploadStatus").textContent =
                    `${current}/${total} • ${filename}`;
                document.getElementById("uploadBar").style.width = percent + "%";
            }

            function hideGlobalUploadProgress() {
                if (overlay) {
                    overlay.remove();
                    overlay = null;
                }
            }

           let safetyTimer = null; // Timer om loader automatisch te sluiten bij vastloper

        if (navigator.serviceWorker) {
            navigator.serviceWorker.addEventListener("message", (event) => {
                const msg = event.data;
                if (!msg) return;

                // Reset de veiligheidstimer bij elk teken van leven
                if (overlay) {
                    clearTimeout(safetyTimer);
                    // Als we 10 seconden niks horen, sluit de loader automatisch
                    safetyTimer = setTimeout(() => {
                        console.warn("⚠️ Geen activiteit meer van SW, loader sluiten...");
                        hideGlobalUploadProgress();
                        isSaving = false; // Slot vrijgeven
                    }, 30000);
                }

                if (msg.type === "QUEUED") {
                    console.log(`📥 In wachtrij: ${msg.file}`);
                }

                if (msg.type === "PROGRESS") {
                    // Update de loader met de nieuwe, correcte totalen (bijv 3/6)
                    showGlobalUploadProgress(msg.current, msg.total, msg.name);
                }

                if (msg.type === "UPLOADED") {
                    console.log(`☁️ R2 Upload OK: ${msg.name}`);
                }
                
                if (msg.type === "COMPLETE") {
                    // Alles klaar! Timer stoppen en sluiten.
                    clearTimeout(safetyTimer);
                    hideGlobalUploadProgress();
                    
                    // Slot vrijgeven (belangrijk!)
                    isSaving = false;
                    
                    showToast("✨ Server update: Alle foto's zijn veilig aangekomen!", 5000);
                }
            });
        }




            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js')
                    .then(reg => {
                        console.log("SW geregistreerd:", reg.scope);

                        // 👇 DIT IS DE TOEVOEGING
                        // Check direct bij het openen van de app of er nog iets in de wachtrij staat
                        if (reg.sync) {
                            // We registreren de sync opnieuw. Als er niets in de queue zit, doet dit niks (veilig).
                            // Als er wél iets zit, wordt het nu direct geüpload.
                            reg.sync.register("sync-r2-uploads")
                                .catch(err => console.warn("Kon sync niet triggeren bij start:", err));
                        }
                        // 👆 EINDE TOEVOEGING

                        // ... hier staat je updatefound code ...

                        // Als een nieuwe SW klaar is om te activeren
                        reg.addEventListener('updatefound', () => {
                            const newSW = reg.installing;

                            newSW.addEventListener('statechange', () => {
                                if (newSW.state === 'installed' && navigator.serviceWorker.controller) {
                                    console.log("Nieuwe service worker beschikbaar — herladen...");
                                    window.location.reload();
                                }
                            });
                        });

                        // Als de service worker actief wordt en control krijgt
                        navigator.serviceWorker.addEventListener('controllerchange', () => {
                            console.log("🔥 SW heeft nu control over de pagina");
                            window.location.reload();
                        });
                    });
            }


            // In x-layouts.dashboard (of je blade file)

window.addEventListener("online", () => {
    console.log("📶 Verbinding hersteld! Directe sync forceren...");

    // 1. Stuur een DIRECT commando naar de SW (Dit is de snelle fix)
    sendToSW({ type: "FORCE_PROCESS" });

    // 2. Als backup: registreer ook de background sync (voor als je tabblad net sluit)
    navigator.serviceWorker.ready.then(reg => {
        if(reg.sync) {
            reg.sync.register("sync-r2-uploads").catch(console.warn);
        }
    });
});

document.addEventListener("visibilitychange", async () => {
        if (document.visibilityState === "visible") {
            console.log("👀 App is weer zichtbaar! Checken op hangende uploads...");
            
            // Wacht heel even zodat de browser op adem kan komen
            await new Promise(r => setTimeout(r, 500));
            
            // Schop de Service Worker wakker
            if(window.sendToSW) {
                window.sendToSW({ type: "FORCE_PROCESS" });
            }
            
            // Herstel de progress bar als die weg was maar er nog taken zijn
            // (Optioneel, maar handig voor UX)
        }
    });
        </script>
    @endpush


 <script type="module">
    import imageCompression from "https://cdn.jsdelivr.net/npm/browser-image-compression@2.0.2/+esm";

    let isSaving = false;

window.addEventListener("beforeunload", (e) => {
    if (isSaving) {
        e.preventDefault();
        e.returnValue = "Er worden nog foto's verwerkt. Weet je zeker dat je wilt afsluiten?";
    }
});
    // ============================================================
    // 1. SERVICE WORKER & COMMUNICATIE
    // ============================================================

    window.sendToSW = async function(msg) {
        if (!navigator.serviceWorker) {
            console.error("❌ Service Workers niet ondersteund.");
            return;
        }
        const reg = await navigator.serviceWorker.ready;
        if (reg.active) {
            reg.active.postMessage(msg);
        } else if (navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage(msg);
        } else {
            window.location.reload();
        }
    }

    if ("wakeLock" in navigator) {
        let wakeLock = null;
        async function requestWakeLock() {
            try { wakeLock = await navigator.wakeLock.request("screen"); } 
            catch (err) { console.warn("Wake Lock failed:", err); }
        }
        document.addEventListener("visibilitychange", () => {
            if (document.visibilityState === "visible" && !wakeLock) requestWakeLock();
        });
        requestWakeLock();
    }

    if (navigator.serviceWorker) {
        navigator.serviceWorker.addEventListener("controllerchange", () => {
            window.location.reload();
        });
    }

    // ============================================================
    // 2. DATA LADEN (PERCELEN MET FIX VOOR TYPE)
    // ============================================================

    async function loadPercelen() {
        try {
            let res = await fetch("/dropbox/percelen");
            let data = await res.json();
            let perceelSelect = document.getElementById("perceelSelect");
            
            if(!perceelSelect) return;

            perceelSelect.innerHTML = "<option value=''>-- Kies type werk --</option>";

            data.forEach(p => {
                let displayName = p.name;
                
                if (p.name.toLowerCase().includes("perceel 1")) {
                    displayName = "Aansluitingen";
                } else if (p.name.toLowerCase().includes("perceel 2")) {
                    displayName = "Graafwerk";
                }

                // ⚠️ FIX: We slaan het TYPE op in een data-attribuut
                // p.id is bij namespace de ID, bij folder het pad.
                perceelSelect.innerHTML += `
                    <option value="${p.id}" data-type="${p.type}">
                        ${displayName}
                    </option>`;
            });
        } catch (error) {
            console.error("Kon percelen niet laden:", error);
        }
    }

    // ============================================================
    // 3. GLOBALE HELPERS (Validatie & UI)
    // ============================================================

    function validateForm() {
        clearErrors();

        let perceelSelect = document.getElementById("perceelSelect");
        let photoUpload = document.getElementById("photoUpload");
        let damageNone = document.getElementById("damageNone");
        let damageYes = document.getElementById("damageYes");
        let noteField = document.querySelector('#finishForm textarea[name="note"]');
        let isValid = true;

        if (!perceelSelect.value) {
            showError("errorPerceel", "Kies een type werk.");
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

    function showError(id, message) {
        let el = document.getElementById(id);
        if (el) {
            el.textContent = message;
            el.classList.remove("hidden");
        }
    }

    function clearErrors() {
        ["errorPerceel", "errorPhoto", "errorDamage", "errorNote"].forEach(id => {
            let el = document.getElementById(id);
            if (el) {
                el.textContent = "";
                el.classList.add("hidden");
            }
        });
    }

    window.handleFrontendSuccess = function(taskId, form, loader, serverStatus = null) {
        isSaving = false;
        const currentStatus = document.querySelector(`tr[data-task-id="${taskId}"]`)?.dataset.status;
        const damage = form.querySelector('input[name="damage"]:checked')?.value;
        let newStatus = serverStatus || currentStatus;

        if (!serverStatus) {
            if (currentStatus === "open") newStatus = "in behandeling";
            else if (["in behandeling", "reopened"].includes(currentStatus)) {
                newStatus = (damage === "none") ? "finished" : "in behandeling";
            }
        }
        updateTaskStatusRow(taskId, newStatus);

        const loaderText = document.getElementById("loaderText");
        if (loaderText) loaderText.textContent = "📦 Foto's in wachtrij geplaatst. Upload draait op achtergrond.";

        window.showToast("📂 Wijzigingen opgeslagen & foto's in wachtrij!", 4000);

        closeTaskForm();
        setTimeout(() => { removeLoader(loader); }, 1500);
    }

    function removeLoader(loader) {
        if (loader) {
            loader.classList.add("opacity-0");
            setTimeout(() => loader.remove(), 300);
        }
    }

    window.showToast = function(message, duration = 4000) {
        const toast = document.createElement("div");
        toast.textContent = message;
        toast.className = "fixed bottom-5 right-5 bg-gray-900 text-white px-4 py-2 rounded-lg shadow-lg text-sm transition-opacity animate-fadeIn z-50";
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), duration);
    }

    function updateTaskStatusRow(taskId, newStatus) {
        const row = document.querySelector(`tr[data-task-id="${taskId}"]`);
        if (!row) return;
        row.dataset.status = newStatus;
        const span = row.querySelector("td:nth-child(3) span");
        if (span) {
            span.textContent = newStatus;
            span.className = "px-2 py-1 rounded text-sm font-semibold " + 
                (newStatus === "finished" ? "bg-green-200 text-green-800" :
                 newStatus === "in behandeling" ? "bg-yellow-200 text-yellow-800" :
                 "bg-gray-200 text-gray-800");
        }
    }

    function clearPreviews() {
        const container = document.getElementById("photoPreview");
        if (!container) return;
        const images = container.querySelectorAll("img");
        images.forEach(img => {
            if (img.src.startsWith("blob:")) URL.revokeObjectURL(img.src);
        });
        container.innerHTML = "";
    }

    // ============================================================
    // 4. FORMULIER LOGICA
    // ============================================================

    window.openTaskForm = function(taskId, address, time, status, note) {
        const panel = document.getElementById('taskFormPanel');
        panel.classList.remove('hidden');

        const row = document.querySelector(`tr[data-task-id="${taskId}"]`);
        if (!row) return;

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

        form.reset();
        document.getElementById("photoUpload").value = "";
        clearPreviews();
        noteWrapper.classList.add('hidden');
        noteField.value = '';

        if (status === 'finished') {
            form.action = "";
            finishButton.classList.add('hidden');
            damageNone.disabled = true;
            damageYes.disabled = true;
            noteField.setAttribute('readonly', true);
            
            if (note) {
                damageYes.checked = true;
                noteWrapper.classList.remove('hidden');
                noteField.value = note;
            } else {
                damageNone.checked = true;
            }
        } else {
            form.action = `/tasks/${taskId}/finish`;
            finishButton.classList.remove('hidden');
            finishButton.disabled = false;
            finishButton.textContent = "Voltooien";
            damageNone.disabled = false;
            damageYes.disabled = false;
            noteField.removeAttribute('readonly');
            
            if (note) {
                damageYes.checked = true;
                noteWrapper.classList.remove('hidden');
                noteField.value = note;
            }
        }
    }

    window.closeTaskForm = function() {
        const panel = document.getElementById("taskFormPanel");
        const form = document.getElementById("finishForm");
        form.reset();
        document.getElementById("photoUpload").value = "";
        clearPreviews();
        clearErrors();
        panel.classList.add("hidden");
    }

    // ============================================================
    // 5. COMPRESSIE
    // ============================================================

    async function compressInBatches(files, options, batchSize = 2) {
        const compressed = [];
        for (let i = 0; i < files.length; i += batchSize) {
            const batch = files.slice(i, i + batchSize);
            const results = await Promise.all(
                batch.map(async (file) => {
                    try {
                        return await imageCompression(file, options);
                    } catch {
                        return file;
                    }
                })
            );
            compressed.push(...results);
            await new Promise((r) => setTimeout(r, 25));
        }
        return compressed;
    }

    // ============================================================
    // 6. DOM EVENTS (STARTPUNT)
    // ============================================================

    document.addEventListener("DOMContentLoaded", () => {
        
        loadPercelen();

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

        const damageNone = document.getElementById('damageNone');
        const damageYes = document.getElementById('damageYes');
        const noteWrapper = document.getElementById('noteWrapper');
        const noteField = document.querySelector('#finishForm textarea[name="note"]');

        if (damageNone && damageYes) {
            damageNone.addEventListener('change', () => {
                if (damageNone.checked) {
                    noteWrapper.classList.add('hidden');
                    noteField.value = '';
                }
            });
            damageYes.addEventListener('change', () => {
                if (damageYes.checked) {
                    noteWrapper.classList.remove('hidden');
                    setTimeout(() => noteField.focus(), 100);
                }
            });
        }

        const photoUpload = document.getElementById("photoUpload");
        const previewContainer = document.getElementById("photoPreview");

        if (photoUpload) {
            photoUpload.addEventListener("change", (e) => {
                clearPreviews();
                let files = [...e.target.files];
                if (files.length > 30) {
                    alert("Je mag maximaal 30 foto's uploaden.");
                    files = files.slice(0, 30);
                }
                files.forEach(file => {
                    const objectUrl = URL.createObjectURL(file);
                    let img = document.createElement("img");
                    img.src = objectUrl;
                    img.className = "preview-img h-16 w-16 object-cover rounded cursor-pointer hover:opacity-80 transition border border-gray-300";
                    previewContainer.appendChild(img);
                });
            });
        }

        const lightbox = document.getElementById("photoLightbox");
        const lightboxImg = document.getElementById("lightboxImage");
        const closeLightbox = document.getElementById("closeLightbox");
        const prevPhoto = document.getElementById("prevPhoto");
        const nextPhoto = document.getElementById("nextPhoto");
        let currentIndex = 0;

        function showImage(index) {
            let images = [...document.querySelectorAll("#photoPreview img")];
            if (images.length === 0) return;
            if (index < 0) index = images.length - 1;
            if (index >= images.length) index = 0;
            currentIndex = index;
            lightboxImg.src = images[currentIndex].src;
            lightbox.classList.remove("hidden");
            lightbox.classList.add("flex");
        }

        function hideLightbox() {
            if (!lightbox) return;
            lightbox.classList.add("hidden");
            lightbox.classList.remove("flex");
            lightboxImg.src = "";
        }

        if (previewContainer) {
            previewContainer.addEventListener("click", (e) => {
                if (e.target.tagName === "IMG") {
                    let images = [...document.querySelectorAll("#photoPreview img")];
                    currentIndex = images.indexOf(e.target);
                    showImage(currentIndex);
                }
            });
        }

        if (prevPhoto) prevPhoto.addEventListener("click", (e) => { e.stopPropagation(); showImage(currentIndex - 1); });
        if (nextPhoto) nextPhoto.addEventListener("click", (e) => { e.stopPropagation(); showImage(currentIndex + 1); });
        if (closeLightbox) closeLightbox.addEventListener("click", hideLightbox);
        if (lightbox) lightbox.addEventListener("click", (e) => { if (e.target === lightbox) hideLightbox(); });

        document.addEventListener("keydown", (e) => {
            if (lightbox && !lightbox.classList.contains("hidden")) {
                if (e.key === "ArrowLeft") showImage(currentIndex - 1);
                if (e.key === "ArrowRight") showImage(currentIndex + 1);
                if (e.key === "Escape") hideLightbox();
            }
        });

        // E. SUBMIT HANDLER (MET FIX VOOR PERCEEL 2)
        const finishForm = document.getElementById("finishForm");
        if (finishForm) {
            finishForm.addEventListener("submit", async (e) => {
                e.preventDefault();
                if (!validateForm()) return;
                isSaving = true;

                const form = e.target;
                const taskId = form.action.match(/tasks\/(\d+)/)?.[1];
                const files = [...document.getElementById("photoUpload").files].slice(0, 30);
                
                // 👇 HIER IS DE BELANGRIJKE WIJZIGING 👇
                const perceelSelect = document.getElementById("perceelSelect");
                const selectedOption = perceelSelect.options[perceelSelect.selectedIndex];
                
                const type = selectedOption.dataset.type; // 'namespace' of 'folder'
                const rawValue = selectedOption.value;    // ID of Pad

                let finalNamespaceId = "";
                let finalRootPath = "";

                if (type === 'namespace') {
                    // PERCEEL 1 (Aansluitingen)
                    finalNamespaceId = rawValue;
                    finalRootPath = ""; 
                } else {
                    // PERCEEL 2 (Graafwerk) -> Geen namespace ID sturen!
                    finalNamespaceId = ""; 
                    // We plakken 'Webapp uploads' achter het pad van Perceel 2
                    // rawValue is hier bijv: "/Fluvius Aansluitingen/PERCEEL 2"
                    finalRootPath = rawValue + "/Webapp uploads";
                }
               
                
                // Pad is leeg, want backend maakt: "Straatnaam... (ID)"
                const adresPath = "";   

                const finishButton = document.getElementById("finishButton");
                finishButton.disabled = true;
                finishButton.textContent = "Verwerken...";

                const loader = document.createElement("div");
                loader.className = "fixed inset-0 bg-black bg-opacity-60 flex flex-col items-center justify-center z-50 transition-opacity duration-300";
                loader.innerHTML = `
                    <div class="bg-white p-6 rounded-2xl shadow-xl flex flex-col items-center">
                        <svg class="animate-spin h-8 w-8 text-[#B51D2D] mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <p id="loaderText" class="text-gray-700 text-sm font-medium">Foto's klaarmaken voor verzending...</p>
                    </div>`;
                document.body.appendChild(loader);

                try {
                    if (files.length > 0) {
                        const compressOptions = { maxSizeMB: 0.45, maxWidthOrHeight: 1280, useWebWorker: true, initialQuality: 0.65 };
                        const compressedFiles = await compressInBatches(files, compressOptions, 2);
                        const csrf = document.querySelector('meta[name="csrf-token"]').content;

                        for (let i = 0; i < compressedFiles.length; i++) {
                            await sendToSW({
                                type: "ADD_UPLOAD",
                                name: compressedFiles[i].name,
                                blob: compressedFiles[i],
                                fileType: compressedFiles[i].type,
                                task_id: taskId,
                                
                                // 👇 Stuur de nieuwe variabelen mee
                                namespace_id: finalNamespaceId, 
                                root_path: finalRootPath,
                                adres_path: adresPath,
                                
                                csrf: csrf
                            });
                        }
                    }

                    const finishUrl = `/tasks/${taskId}/finish`;
                    const statusData = new FormData();
                    statusData.append("_token", document.querySelector('meta[name="csrf-token"]').content);
                    statusData.append("damage", form.querySelector('input[name="damage"]:checked')?.value || "");
                    statusData.append("note", form.querySelector('textarea[name="note"]').value || "");

                    if (navigator.sendBeacon) {
                        navigator.sendBeacon(finishUrl, statusData);
                        handleFrontendSuccess(taskId, form, loader);
                    } else {
                        const res = await fetch(finishUrl, {
                            method: "POST", headers: { "Accept": "application/json" }, body: statusData
                        });
                        if (res.ok) {
                            const json = await res.json();
                            handleFrontendSuccess(taskId, form, loader, json.status);
                        } else {
                            removeLoader(loader);
                        }
                    }
                } catch (err) {
                    console.error("Fout in submit flow:", err);
                    showToast("⚠️ Er ging iets mis. Probeer opnieuw.");

                    isSaving = false;
                    removeLoader(loader);
                } finally {
                    finishButton.disabled = false;
                    finishButton.textContent = "Voltooien";
                }
            });
        }
    });
</script>

</x-layouts.dashboard>
