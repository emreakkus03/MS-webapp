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
                        <select id="perceelSelect"
                            class="w-full border px-3 py-2 rounded mt-1 text-sm md:text-base"></select>
                        <p id="errorPerceel" class="text-red-500 text-xs md:text-sm mt-1 hidden"></p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Map</label>
                        <select id="regioSelect" class="w-full border px-3 py-2 rounded mt-1 text-sm md:text-base"
                            disabled></select>
                        <p id="errorRegio" class="text-red-500 text-xs md:text-sm mt-1 hidden"></p>
                    </div>

                    <div class="relative">
                        <label class="block text-sm font-medium">Adres</label>
                        <div class="flex gap-2 items-center mt-1">
                            <input type="text" id="adresComboInput" placeholder="Zoek of kies adres..."
                                class="border px-3 py-2 rounded w-full text-sm md:text-base" disabled
                                autocomplete="off">
                            <button type="button" id="newAdresBtn"
                                class="flex items-center justify-center bg-gray-200 w-8 h-8 rounded hover:bg-gray-300">+
                            </button>
                        </div>

                        <select id="adresSelect" name="folder" class="hidden"></select>

                        <div id="adresDropdown"
                            class="absolute z-10 mt-1 w-full bg-white border rounded shadow max-h-60 overflow-auto hidden">
                        </div>

                        <button type="button" id="loadMoreAdressenBtn"
                            class="mt-2 text-blue-600 text-xs md:text-sm underline hidden">Meer laden...</button>
                        <p id="errorAdres" class="text-red-500 text-xs md:text-sm mt-1 hidden"></p>
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

            if (navigator.serviceWorker) {
                navigator.serviceWorker.addEventListener("message", (event) => {
                    const msg = event.data;
                    if (!msg) return;

                    if (msg.type === "QUEUED") {
                        console.log(`📥 In wachtrij geplaatst: ${msg.file}`);
                    }

                    if (msg.type === "PROGRESS") {
                        showGlobalUploadProgress(msg.current, msg.total, msg.name);
                    }

                    if (msg.type === "UPLOADED") {
                        console.log(`☁️ Geüpload naar R2: ${msg.name}`);
                    }

                    if (msg.type === "COMPLETE") {
                        hideGlobalUploadProgress();
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
        </script>
    @endpush


    <script type="module">
        navigator.serviceWorker.addEventListener("controllerchange", () => {
            console.log("🔥 SW heeft nu control → opnieuw laden");
            window.location.reload();
        });


        window.showToast = function(message, duration = 4000) {
            const toast = document.createElement("div");
            toast.textContent = message;
            toast.className =
                "fixed bottom-5 right-5 bg-gray-900 text-white px-4 py-2 rounded-lg shadow-lg text-sm";
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), duration);
        };
        /**
         * 🛠️ Stuurt een bericht naar de Service Worker (SW).
         * Wacht netjes als de SW nog aan het opstarten is.
         */
        async function sendToSW(msg) {
            if (!navigator.serviceWorker) {
                console.error("❌ Service Workers worden niet ondersteund in deze browser.");
                return;
            }

            // 1. Wacht tot de SW 'ready' is (geïnstalleerd & actief)
            const reg = await navigator.serviceWorker.ready;

            // 2. Check of er een controller is
            if (reg.active) {
                // Gebruik reg.active.postMessage in plaats van navigator.serviceWorker.controller
                // Dit is veiliger omdat reg.active altijd de actieve worker van deze scope is.
                reg.active.postMessage(msg);
            } else if (navigator.serviceWorker.controller) {
                // Fallback
                navigator.serviceWorker.controller.postMessage(msg);
            } else {
                // 3. Noodgeval: forceer reload als er echt geen controller is na 'ready'
                console.warn("⚠️ Wel SW ready, maar geen controller. Pagina wordt herladen...");
                window.location.reload();
            }
        }

        import imageCompression from "https://cdn.jsdelivr.net/npm/browser-image-compression@2.0.2/+esm";

        // 💤 Voorkom dat uploads pauzeren als scherm uitgaat (Android wake lock)
        if ("wakeLock" in navigator) {
            let wakeLock = null;

            async function requestWakeLock() {
                try {
                    wakeLock = await navigator.wakeLock.request("screen");
                    console.log("🔋 Wake Lock actief — scherm blijft aan tijdens upload");
                    wakeLock.addEventListener("release", () => {
                        console.log("💤 Wake Lock vrijgegeven");
                    });
                } catch (err) {
                    console.warn("⚠️ Wake Lock niet toegestaan:", err);
                }
            }

            // Automatisch activeren bij uploadstart of focus
            document.addEventListener("visibilitychange", () => {
                if (document.visibilityState === "visible" && !wakeLock) {
                    requestWakeLock();
                }
            });

            // Initieel aanvragen
            requestWakeLock();
        }


        // ===============================================
        // 🔹 Tijdelijke adresmap: checken en herstellen
        // ===============================================
        function checkTempAdres() {
            const data = localStorage.getItem("tempAdresFolder");
            if (!data) return;

            try {
                const temp = JSON.parse(data);

                // ✅ Check of map nog geldig is (10 minuten)
                if (Date.now() > temp.expiresAt) {
                    console.log("🕒 Tijdelijke map verlopen — verwijderen");
                    localStorage.removeItem("tempAdresFolder");
                    return;
                }

                // 🔹 Nog geldig → toon deze map in dropdown
                const input = document.getElementById("adresComboInput");
                const select = document.getElementById("adresSelect");

                input.value = temp.name;
                select.innerHTML = `
            <option value="${temp.path}" data-namespace="${temp.namespace}" selected>
                ${temp.name}
            </option>`;
                select.disabled = false;
            } catch (err) {
                console.error("❌ Fout bij lezen tijdelijke map:", err);
                localStorage.removeItem("tempAdresFolder");
            }
        }

        // 🔸 Controleer tijdelijke map bij pagina-load
        document.addEventListener("DOMContentLoaded", checkTempAdres);

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



        // Nieuwe adresmap
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

            let safeName = name
                .replace(/,/g, '') // verwijder komma’s
                .replace(/[^a-zA-Z0-9 _-]/g, '') // verwijder rare tekens
                .replace(/\s+/g, ' ') // dubbele spaties → 1 spatie
                .trim(); // spaties begin/eind weg

            if (!safeName) {
                alert("Ongeldige mapnaam. Gebruik enkel letters en cijfers.");
                return;
            }
            try {
                const res = await fetch("{{ route('dropbox.create_adres') }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name=\"csrf-token\"]').content
                    },
                    body: JSON.stringify({
                        namespace_id: namespaceId,
                        path: regioPath,
                        adres: safeName
                    })
                });

                const json = await res.json();

                if (res.status === 201 && json.success) {
                    alert(json.message || "Adresmap aangemaakt in Webapp uploads.");

                    // ✅ Alleen de nieuw gemaakte map tonen
                    const input = document.getElementById("adresComboInput");
                    const select = document.getElementById("adresSelect");
                    const drop = document.getElementById("adresDropdown");

                    input.value = json.folder.name;
                    select.innerHTML = `
                <option value="${json.folder.path}" data-namespace="${json.folder.namespace}" selected>
                    ${json.folder.name}
                </option>`;
                    select.disabled = false;
                    drop.classList.add("hidden");

                    // 🕒 10 minuten geldig (tijdelijke opslag)
                    const expiresAt = Date.now() + (10 * 60 * 1000);
                    localStorage.setItem("tempAdresFolder", JSON.stringify({
                        path: json.folder.path,
                        namespace: json.folder.namespace,
                        name: json.folder.name,
                        expiresAt
                    }));

                    console.log("💾 Tijdelijke map opgeslagen (vervalt over 10 minuten)");
                } else {
                    alert(json.message || "Kon adresmap niet maken.");
                }
            } catch (err) {
                console.error(err);
                alert("Serverfout bij map maken.");
            }
        });



        // ✅ Progressbar element toevoegen
        const progressWrapper = document.createElement("div");
        progressWrapper.className = "w-full bg-gray-200 rounded h-3 mt-2 hidden";
        const progressBar = document.createElement("div");
        progressBar.className = "bg-green-500 h-3 rounded w-0";
        progressWrapper.appendChild(progressBar);
        document.getElementById("photoPreview").after(progressWrapper);
document.addEventListener("DOMContentLoaded", () => {
    // === 1. VARIABELEN & SELECTORS ===
    const photoUpload = document.getElementById("photoUpload");
    const previewContainer = document.getElementById("photoPreview");
    const lightbox = document.getElementById("photoLightbox");
    const lightboxImg = document.getElementById("lightboxImage");
    const closeLightbox = document.getElementById("closeLightbox");
    const prevPhoto = document.getElementById("prevPhoto");
    const nextPhoto = document.getElementById("nextPhoto");
    
    // Zorg dat deze bestaat in je HTML, anders geeft dit een error!
    const progressWrapper = document.getElementById("progressWrapper"); 

    let previewImages = [];
    let currentIndex = 0;

    // === 2. UPLOAD LOGICA (Alleen previews maken) ===
    photoUpload.addEventListener("change", (e) => {
        let files = [...e.target.files];
        previewContainer.innerHTML = ""; // Oude previews wissen

        if (files.length > 30) {
            alert("Je mag maximaal 30 foto's uploaden.");
            files = files.slice(0, 30);
        }

        files.forEach(file => {
            if (file.size > 30 * 1024 * 1024) {
                alert(`Bestand ${file.name} is groter dan 30MB.`);
                return;
            }

            const objectUrl = URL.createObjectURL(file);
            let img = document.createElement("img");
            img.src = objectUrl;
            img.className = "h-16 w-16 object-cover rounded cursor-pointer hover:opacity-80 transition";
            
            // ⚠️ BELANGRIJK: We revoken de URL NIET direct, 
            // anders werkt de lightbox niet.
            
            previewContainer.appendChild(img);
        });

        // Verberg loader indien aanwezig
        if (progressWrapper) progressWrapper.classList.add("hidden");
    });

    // === 3. LIGHTBOX LOGICA (Staat nu BUITEN de upload loop) ===
    
    // Functie om beeld te tonen
    function showImage(index) {
        // Update de lijst met huidige afbeeldingen (voor het geval er opnieuw geüpload is)
        previewImages = [...document.querySelectorAll("#photoPreview img")];
        
        if (previewImages.length === 0) return;

        // Loop logica (einde naar begin en andersom)
        if (index < 0) index = previewImages.length - 1;
        if (index >= previewImages.length) index = 0;

        currentIndex = index;
        
        // Zet de src van de grote foto gelijk aan de thumbnail src
        lightboxImg.src = previewImages[currentIndex].src;
        
        lightbox.classList.remove("hidden");
        lightbox.classList.add("flex");
    }

    // Openen via klik op thumbnail
    previewContainer.addEventListener("click", (e) => {
        if (e.target.tagName === "IMG") {
            const allImages = [...document.querySelectorAll("#photoPreview img")];
            currentIndex = allImages.indexOf(e.target);
            showImage(currentIndex);
        }
    });

    // Navigatie knoppen
    prevPhoto.addEventListener("click", (e) => {
        e.stopPropagation();
        showImage(currentIndex - 1);
    });

    nextPhoto.addEventListener("click", (e) => {
        e.stopPropagation();
        showImage(currentIndex + 1);
    });

    // Sluiten
    function hideLightbox() {
        lightbox.classList.add("hidden");
        lightbox.classList.remove("flex");
        lightboxImg.src = ""; // Leegmaken
    }

    closeLightbox.addEventListener("click", hideLightbox);

    // Sluiten door naast de foto te klikken
    lightbox.addEventListener("click", (e) => {
        if (e.target === lightbox) hideLightbox();
    });

    // Toetsenbord bediening
    document.addEventListener("keydown", (e) => {
        if (lightbox.classList.contains("hidden")) return;
        
        if (e.key === "ArrowLeft") showImage(currentIndex - 1);
        if (e.key === "ArrowRight") showImage(currentIndex + 1);
        if (e.key === "Escape") hideLightbox();
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

        // ===============================================
        // 🔹 Compressie & Upload helpers
        // ===============================================

        // 1️⃣ Comprimeer foto's voor upload
        /* ——— 3. Snelle compressie instellingen ——— */
        async function compressImages(files) {
            const options = {
                maxSizeMB: 0.25, // 250 KB max
                maxWidthOrHeight: 800, // kleiner
                useWebWorker: true,
                initialQuality: 0.6
            };

            const compressed = [];
            for (const file of files) {
                try {
                    const comp = await imageCompression(file, options);
                    compressed.push(comp);
                } catch (err) {
                    console.error("Compressie mislukt:", file.name);
                    compressed.push(file);
                }
            }
            return compressed;
        }



        async function uploadInBatches(files, taskId, namespaceId, adresPath) {
            const batchSize = 5;
            for (let i = 0; i < files.length; i += batchSize) {
                const batch = files.slice(i, i + batchSize);
                await Promise.all(batch.map(f => {
                    const fd = new FormData();
                    fd.append("photos[]", f);
                    fd.append("namespace_id", namespaceId);
                    fd.append("path", adresPath);
                    return fetch(`/tasks/${taskId}/upload-temp`, {
                        method: "POST",
                        headers: {
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]').content
                        },
                        body: fd
                    });
                }));
            }
        }





        // 🔹 Geoptimaliseerde compressie voor Android Chrome (stabieler)
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
                await new Promise((r) => setTimeout(r, 25)); // ademruimte voor Chrome
            }
            return compressed;
        }

        // 🔹 Submit handler met eerlijke feedback
document.getElementById("finishForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    if (!validateForm()) return;

    const form = e.target;
    const taskId = form.action.match(/tasks\/(\d+)/)?.[1];
    const formData = new FormData(form);
    const files = [...document.getElementById("photoUpload").files].slice(0, 30); 
    const adresSelect = document.getElementById("adresSelect");
    const namespaceId = adresSelect.options[adresSelect.selectedIndex]?.dataset.namespace;
    const adresPath = adresSelect.value;

    const finishButton = document.getElementById("finishButton");
    finishButton.disabled = true;
    finishButton.textContent = "Verwerken...";

    // 🔹 Loader tonen
    const loader = document.createElement("div");
    loader.className = "fixed inset-0 bg-black bg-opacity-60 flex flex-col items-center justify-center z-50 transition-opacity duration-300";
    loader.innerHTML = `
        <div class="bg-white p-6 rounded-2xl shadow-xl flex flex-col items-center">
            <svg class="animate-spin h-8 w-8 text-[#B51D2D] mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
            <p id="loaderText" class="text-gray-700 text-sm font-medium">Foto's klaarmaken voor verzending...</p>
        </div>
    `;
    document.body.appendChild(loader);

    try {
        if (files.length > 0) {
            const compressOptions = {
                maxSizeMB: 0.45,
                maxWidthOrHeight: 1280,
                useWebWorker: true,
                initialQuality: 0.65
            };

            // Comprimeren
            const compressedFiles = await compressInBatches(files, compressOptions, 2);
            const csrf = document.querySelector('meta[name="csrf-token"]').content;

            // Naar Service Worker sturen
            for (let i = 0; i < compressedFiles.length; i++) {
                await sendToSW({
                    type: "ADD_UPLOAD",
                    name: compressedFiles[i].name,
                    blob: compressedFiles[i],
                    fileType: compressedFiles[i].type,
                    task_id: taskId,
                    namespace_id: namespaceId,
                    adres_path: adresPath,
                    csrf: csrf
                });
            }
            
            console.log(`✅ ${compressedFiles.length} foto's naar wachtrij gestuurd.`);
        }

        // ✅ Taak afronden (Status update naar backend)
        const finishUrl = `/tasks/${taskId}/finish`;
        
        // Data voor de status update (zonder fotos, die gaan via SW)
        const statusData = new FormData();
        statusData.append("_token", document.querySelector('meta[name="csrf-token"]').content);
        statusData.append("damage", form.querySelector('input[name="damage"]:checked')?.value || "");
        statusData.append("note", form.querySelector('textarea[name="note"]').value || "");

        // Gebruik sendBeacon indien beschikbaar (robuuster bij afsluiten)
        if (navigator.sendBeacon) {
            navigator.sendBeacon(finishUrl, statusData);
            handleFrontendSuccess(taskId, form, loader);
        } else {
            // Fallback fetch
            const res = await fetch(finishUrl, {
                method: "POST",
                headers: { "Accept": "application/json" },
                body: statusData
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
        removeLoader(loader);
    } finally {
        finishButton.disabled = false;
        finishButton.textContent = "Voltooien";
    }
});

// 👇 Nieuwe hulpfunctie om dubbele code te voorkomen en tekst te fixen
function handleFrontendSuccess(taskId, form, loader, serverStatus = null) {
    // 1. Update de status in de tabel
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

    // 2. 👇 DE BELANGRIJKSTE WIJZIGING: DE TEKST
    const loaderText = document.getElementById("loaderText");
    if (loaderText) {
        // Zeg NIET "Afgerond", maar "In wachtrij"
        loaderText.textContent = "📦 Foto's in wachtrij geplaatst. Upload draait op achtergrond.";
    }

    // 3. Toon eerlijke toast
    showToast("📂 Wijzigingen opgeslagen & foto's in wachtrij!", 4000);

    // 4. Sluit formulier en verwijder loader na korte pauze
    closeTaskForm();
    setTimeout(() => {
        removeLoader(loader);
    }, 1500); // Iets langer laten staan zodat ze de tekst kunnen lezen
}

function removeLoader(loader) {
    if(loader) {
        loader.classList.add("opacity-0");
        setTimeout(() => loader.remove(), 300);
    }
}

        // 🔹 Toast helper
        function showToast(message, duration = 4000) {
            const toast = document.createElement("div");
            toast.textContent = message;
            toast.className =
                "fixed bottom-5 right-5 bg-gray-900 text-white px-4 py-2 rounded-lg shadow-lg text-sm transition-opacity animate-fadeIn";
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), duration);
        }

        // 🔹 Status update helper
        function updateTaskStatusRow(taskId, newStatus) {
            const row = document.querySelector(`tr[data-task-id="${taskId}"]`);
            if (!row) return;
            row.dataset.status = newStatus;
            const span = row.querySelector("td:nth-child(3) span");
            span.textContent = newStatus;
            span.className = "px-2 py-1 rounded text-sm font-semibold";
            switch (newStatus) {
                case "finished":
                    span.classList.add("bg-green-200", "text-green-800");
                    break;
                case "in behandeling":
                    span.classList.add("bg-yellow-200", "text-yellow-800");
                    break;
                default:
                    span.classList.add("bg-gray-200", "text-gray-800");
            }
        }

        // 🔹 Form sluiten helper
        function closeTaskForm() {
            const panel = document.getElementById("taskFormPanel");
            const form = document.getElementById("finishForm");
            form.reset();
            document.getElementById("photoPreview").innerHTML = "";
            clearErrors();
            panel.classList.add("hidden");

            document.getElementById('taskStatus').textContent = "";
            document.getElementById('taskAddressTitle').textContent = "";
            document.getElementById('taskZipCity').textContent = "";
            document.getElementById('taskTimeTitle').textContent = "";
        }



        // Bij openen taakformulier
        function openTaskForm(taskId, address, time, status, note) {
            loadPercelen();
            document.getElementById("adresSelect").innerHTML = "";
            document.getElementById("adresComboInput").value = "";
            document.getElementById("adresSelect").disabled = true;
            document.getElementById("adresDropdown").classList.add("hidden");
            localStorage.removeItem("tempAdresFolder");

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

        // 🔁 Herstel uploads als gebruiker weer online komt
    </script>

</x-layouts.dashboard>
