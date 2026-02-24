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


{{-- 
    ============================================================
    VERVANG ALLES TUSSEN @push('scripts') ... @endpush 
    EN het <script type="module"> blok met onderstaande code
    ============================================================
--}}

@push('scripts')
<script>
    // ============================================================
    // 🔹 GLOBALE VARIABELEN (beschikbaar in ALLE scripts)
    // ============================================================
    let overlay = null;

    // 👇 FIX: showToast als globale functie (was dubbel gedefinieerd)
    window.showToast = function(message, duration = 4000) {
        const toast = document.createElement("div");
        toast.textContent = message;
        toast.className = "fixed bottom-5 right-5 bg-gray-900 text-white px-4 py-2 rounded-lg shadow-lg text-sm z-50";
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), duration);
    };

    // 👇 FIX: sendToSW als globale functie (was alleen in module scope)
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
            console.warn("⚠️ Geen SW controller gevonden.");
        }
    };

    // ============================================================
    // 🔹 IndexedDB functies (ZELFDE als in SW — shared logic)
    //    Dit is FIX 1: Main thread schrijft DIRECT naar IndexedDB
    // ============================================================
    const DB_NAME = "R2UploadDB";
    const STORE = "pending";

    window.openUploadDB = function() {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open(DB_NAME, 2);
            req.onupgradeneeded = (e) => {
                const db = e.target.result;
                if (!db.objectStoreNames.contains(STORE)) {
                    const store = db.createObjectStore(STORE, { keyPath: "id", autoIncrement: true });
                    store.createIndex("by_task_name", ["task_id", "name", "adres_path"], { unique: false });
                }
            };
            req.onsuccess = (e) => resolve(e.target.result);
            req.onerror = (e) => reject(e.target.error);
        });
    };

    window.addToUploadQueue = async function(data) {
        const db = await openUploadDB();

        // Deduplicatie check
        const allItems = await new Promise((resolve) => {
            const tx = db.transaction(STORE, "readonly");
            const req = tx.objectStore(STORE).getAll();
            req.onsuccess = () => resolve(req.result);
        });

        const exists = allItems.find(item =>
            item.name === data.name &&
            item.task_id === data.task_id &&
            item.adres_path === data.adres_path
        );

        if (exists) {
            console.log(`⚠️ Main: Dubbel genegeerd: '${data.name}' voor task ${data.task_id}`);
            return false;
        }

        // Blob naar ArrayBuffer converteren voor opslag
        let blobData;
        if (data.blob instanceof Blob) {
            blobData = await data.blob.arrayBuffer();
        } else {
            blobData = data.blob;
        }

        const clean = {
            name: data.name,
            fileType: data.fileType,
            task_id: data.task_id,
            namespace_id: data.namespace_id,
            adres_path: data.adres_path,
            blob: blobData,
            addedAt: Date.now()
        };

        return new Promise((resolve, reject) => {
            const tx = db.transaction(STORE, "readwrite");
            const store = tx.objectStore(STORE);
            const req = store.add(clean);
            req.onsuccess = () => {
                console.log(`✅ Main: '${data.name}' opgeslagen in IndexedDB`);
                resolve(true);
            };
            req.onerror = (e) => {
                console.error(`❌ Main: Kon '${data.name}' niet opslaan:`, e);
                reject(e);
            };
        });
    };

    window.getUploadQueueCount = async function() {
        const db = await openUploadDB();
        return new Promise((resolve) => {
            const tx = db.transaction(STORE, "readonly");
            const req = tx.objectStore(STORE).count();
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => resolve(0);
        });
    };

    // ============================================================
    // 🔹 Upload Progress UI
    // ============================================================
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
        document.getElementById("uploadStatus").textContent = `${current}/${total} • ${filename}`;
        document.getElementById("uploadBar").style.width = percent + "%";
    }

    function hideGlobalUploadProgress() {
        if (overlay) {
            overlay.remove();
            overlay = null;
        }
    }

    // ============================================================
    // 🔹 Service Worker berichten ontvangen
    // ============================================================
    if (navigator.serviceWorker) {
        navigator.serviceWorker.addEventListener("message", (event) => {
            const msg = event.data;
            if (!msg) return;

            if (msg.type === "QUEUED") {
                console.log(`📥 In wachtrij: ${msg.file}`);
            }
            if (msg.type === "PROGRESS") {
                showGlobalUploadProgress(msg.current, msg.total, msg.name);
            }
            if (msg.type === "UPLOADED") {
                console.log(`☁️ Geüpload: ${msg.name}`);
            }
            if (msg.type === "UPLOAD_PARTIAL") {
                console.warn(`⚠️ Deels geüpload: ${msg.name} — ${msg.reason}`);
            }
            if (msg.type === "COMPLETE") {
                hideGlobalUploadProgress();
                if (msg.remaining > 0) {
                    showToast(`📦 ${msg.uploaded} geüpload, ${msg.remaining} wachten nog...`, 5000);
                } else {
                    showToast("✅ Alle foto's zijn geüpload!", 5000);
                }
            }
        });
    }

    // ============================================================
    // 🔹 Service Worker registratie (FIX 5: geen auto-reload)
    // ============================================================
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js')
            .then(reg => {
                console.log("SW geregistreerd:", reg.scope);

                // Check of er nog uploads in de queue staan
                if (reg.sync) {
                    reg.sync.register("sync-r2-uploads").catch(console.warn);
                }

                // 👇 FIX 5: GEEN automatische reload meer
                reg.addEventListener('updatefound', () => {
                    const newSW = reg.installing;
                    newSW.addEventListener('statechange', () => {
                        if (newSW.state === 'installed' && navigator.serviceWorker.controller) {
                            // Toon melding in plaats van te reloaden
                            showToast("🔄 Update beschikbaar. Herlaad wanneer je klaar bent.", 8000);
                        }
                    });
                });
            });
    }

    // ============================================================
    // 🔹 Online herstel (FIX: sendToSW is nu globaal beschikbaar)
    // ============================================================
    window.addEventListener("online", async () => {
        console.log("📶 Verbinding hersteld!");

        // Check of er items in de queue staan
        const count = await getUploadQueueCount();
        if (count > 0) {
            console.log(`📦 ${count} items in queue, forceer verwerking...`);
            showToast(`📶 Online! ${count} foto's worden nu geüpload...`, 4000);
            sendToSW({ type: "FORCE_PROCESS" });
        }

        // Backup: background sync
        navigator.serviceWorker.ready.then(reg => {
            if (reg.sync) {
                reg.sync.register("sync-r2-uploads").catch(console.warn);
            }
        });
    });

    // ============================================================
    // 🔹 Periodieke check: als er items in IDB staan, trigger SW
    // ============================================================
    setInterval(async () => {
        const count = await getUploadQueueCount();
        if (count > 0 && navigator.onLine) {
            console.log(`⏰ Periodieke check: ${count} items wachten, trigger SW...`);
            sendToSW({ type: "PROCESS_QUEUE" });
        }
    }, 60000); // Elke minuut checken
</script>
@endpush

<script type="module">
    // ============================================================
    // Module-scoped code (imports etc.)
    // ============================================================

    import imageCompression from "https://cdn.jsdelivr.net/npm/browser-image-compression@2.0.2/+esm";

    // Wake Lock
    if ("wakeLock" in navigator) {
        let wakeLock = null;
        async function requestWakeLock() {
            try {
                wakeLock = await navigator.wakeLock.request("screen");
                console.log("🔋 Wake Lock actief");
                wakeLock.addEventListener("release", () => console.log("💤 Wake Lock vrijgegeven"));
            } catch (err) {
                console.warn("⚠️ Wake Lock niet toegestaan:", err);
            }
        }
        document.addEventListener("visibilitychange", () => {
            if (document.visibilityState === "visible" && !wakeLock) requestWakeLock();
        });
        requestWakeLock();
    }

    // ============================================================
    // Tijdelijke adresmap check
    // ============================================================
    function checkTempAdres() {
        const data = localStorage.getItem("tempAdresFolder");
        if (!data) return;
        try {
            const temp = JSON.parse(data);
            if (Date.now() > temp.expiresAt) {
                localStorage.removeItem("tempAdresFolder");
                return;
            }
            const input = document.getElementById("adresComboInput");
            const select = document.getElementById("adresSelect");
            input.value = temp.name;
            select.innerHTML = `<option value="${temp.path}" data-namespace="${temp.namespace}" selected>${temp.name}</option>`;
            select.disabled = false;
        } catch (err) {
            localStorage.removeItem("tempAdresFolder");
        }
    }
    document.addEventListener("DOMContentLoaded", checkTempAdres);

    // ============================================================
    // Dropbox cascade loaders (ongewijzigd)
    // ============================================================
    async function loadPercelen() {
        let res = await fetch("/dropbox/percelen");
        let data = await res.json();
        let perceelSelect = document.getElementById("perceelSelect");
        perceelSelect.innerHTML = "<option value=''>-- Kies type werk --</option>";
        data.forEach(p => {
            let displayName = p.name;
            if (p.name.toLowerCase().includes("perceel 1")) displayName = "Aansluitingen";
            else if (p.name.toLowerCase().includes("perceel 2")) displayName = "Graafwerk";
            perceelSelect.innerHTML += `<option value="${p.id}" data-type="${p.type}" data-original="${p.name}">${displayName}</option>`;
        });
    }

    async function loadRegios(id, type) {
        let res = await fetch(`/dropbox/regios?id=${encodeURIComponent(id)}&type=${encodeURIComponent(type)}`);
        let data = await res.json();
        let regioSelect = document.getElementById("regioSelect");
        regioSelect.innerHTML = "<option value=''>-- Kies map --</option>";
        let webappOnly = data.filter(r => r.name && r.name.toLowerCase().includes("webapp uploads"));
        if (webappOnly.length > 0) {
            webappOnly.forEach(r => {
                regioSelect.innerHTML += `<option value="${r.path}" data-namespace="${r.namespace}">${r.name}</option>`;
            });
            regioSelect.disabled = false;
            regioSelect.value = webappOnly[0].path;
        } else {
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
        let url = `/dropbox/adressen?namespace_id=${encodeURIComponent(namespaceId)}&path=${encodeURIComponent(regioPath)}`;
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
        let loadMoreBtn = document.getElementById("loadMoreAdressenBtn");
        loadMoreBtn.classList.toggle("hidden", !data.has_more);
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
                select.innerHTML = `<option value="${a.path}" data-namespace="${a.namespace}" selected>${a.name}</option>`;
                dropdown.classList.add("hidden");
            });
            dropdown.appendChild(div);
        });
    }

    document.getElementById("adresComboInput").addEventListener("input", (e) => {
        let term = e.target.value.trim();
        if (currentNamespaceId && currentRegioPath) loadAdressen(currentNamespaceId, currentRegioPath, null, term);
        document.getElementById("adresDropdown").classList.remove("hidden");
    });
    document.getElementById("adresComboInput").addEventListener("focus", () => {
        document.getElementById("adresDropdown").classList.remove("hidden");
    });
    document.addEventListener("click", (e) => {
        if (!e.target.closest("#adresDropdown") && e.target.id !== "adresComboInput") {
            document.getElementById("adresDropdown").classList.add("hidden");
        }
    });
    document.getElementById("loadMoreAdressenBtn").addEventListener("click", () => {
        if (currentAdresCursor) loadAdressen(currentNamespaceId, currentRegioPath, currentAdresCursor);
    });
    document.getElementById("perceelSelect").addEventListener("change", (e) => {
        let opt = e.target.options[e.target.selectedIndex];
        if (opt.value && opt.dataset.type) loadRegios(opt.value, opt.dataset.type);
    });

    // Regio → laad adressen
    document.getElementById("regioSelect").addEventListener("change", (e) => {
        let opt = e.target.options[e.target.selectedIndex];
        if (opt.value && opt.dataset.namespace) {
            loadAdressen(opt.dataset.namespace, opt.value);
        }
    });

    // Nieuwe adresmap
    document.getElementById("newAdresBtn").addEventListener("click", async () => {
        const regioSelect = document.getElementById("regioSelect");
        const webappOption = [...regioSelect.options].find(opt => opt.textContent.toLowerCase().includes("webapp uploads"));
        if (!webappOption) { alert("Map 'Webapp uploads' niet gevonden."); return; }

        const regioPath = webappOption.value;
        const namespaceId = webappOption.dataset.namespace;
        const name = prompt("Naam nieuwe adresmap:");
        if (!name) return;

        let safeName = name.replace(/,/g, '').replace(/[^a-zA-Z0-9 _-]/g, '').replace(/\s+/g, ' ').trim();
        if (!safeName) { alert("Ongeldige mapnaam."); return; }

        try {
            const res = await fetch("{{ route('dropbox.create_adres') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ namespace_id: namespaceId, path: regioPath, adres: safeName })
            });
            const json = await res.json();
            if (res.status === 201 && json.success) {
                alert(json.message || "Adresmap aangemaakt.");
                const input = document.getElementById("adresComboInput");
                const select = document.getElementById("adresSelect");
                input.value = json.folder.name;
                select.innerHTML = `<option value="${json.folder.path}" data-namespace="${json.folder.namespace}" selected>${json.folder.name}</option>`;
                select.disabled = false;
                document.getElementById("adresDropdown").classList.add("hidden");
                localStorage.setItem("tempAdresFolder", JSON.stringify({
                    path: json.folder.path, namespace: json.folder.namespace,
                    name: json.folder.name, expiresAt: Date.now() + (10 * 60 * 1000)
                }));
            } else {
                alert(json.message || "Kon adresmap niet maken.");
            }
        } catch (err) { alert("Serverfout bij map maken."); }
    });

    // ============================================================
    // Photo preview & lightbox (ongewijzigd)
    // ============================================================
    const progressWrapper = document.createElement("div");
    progressWrapper.className = "w-full bg-gray-200 rounded h-3 mt-2 hidden";
    const progressBar = document.createElement("div");
    progressBar.className = "bg-green-500 h-3 rounded w-0";
    progressWrapper.appendChild(progressBar);
    document.getElementById("photoPreview").after(progressWrapper);

    document.addEventListener("DOMContentLoaded", () => {
        const photoUpload = document.getElementById("photoUpload");
        const previewContainer = document.getElementById("photoPreview");
        const lightbox = document.getElementById("photoLightbox");
        const lightboxImg = document.getElementById("lightboxImage");
        const closeLightbox = document.getElementById("closeLightbox");
        const prevPhoto = document.getElementById("prevPhoto");
        const nextPhoto = document.getElementById("nextPhoto");
        let previewImages = [];
        let currentIndex = 0;

        photoUpload.addEventListener("change", (e) => {
            let files = [...e.target.files];
            previewContainer.innerHTML = "";
            if (files.length > 30) { alert("Max 30 foto's."); files = files.slice(0, 30); }
            files.forEach(file => {
                if (file.size > 30 * 1024 * 1024) { alert(`${file.name} > 30MB.`); return; }
                const url = URL.createObjectURL(file);
                let img = document.createElement("img");
                img.src = url;
                img.className = "h-16 w-16 object-cover rounded cursor-pointer hover:opacity-80 transition";
                previewContainer.appendChild(img);
            });
        });

        function showImage(index) {
            previewImages = [...document.querySelectorAll("#photoPreview img")];
            if (previewImages.length === 0) return;
            if (index < 0) index = previewImages.length - 1;
            if (index >= previewImages.length) index = 0;
            currentIndex = index;
            lightboxImg.src = previewImages[currentIndex].src;
            lightbox.classList.remove("hidden");
            lightbox.classList.add("flex");
        }

        previewContainer.addEventListener("click", (e) => {
            if (e.target.tagName === "IMG") {
                currentIndex = [...document.querySelectorAll("#photoPreview img")].indexOf(e.target);
                showImage(currentIndex);
            }
        });
        prevPhoto.addEventListener("click", (e) => { e.stopPropagation(); showImage(currentIndex - 1); });
        nextPhoto.addEventListener("click", (e) => { e.stopPropagation(); showImage(currentIndex + 1); });

        function hideLightbox() {
            lightbox.classList.add("hidden");
            lightbox.classList.remove("flex");
            lightboxImg.src = "";
        }
        closeLightbox.addEventListener("click", hideLightbox);
        lightbox.addEventListener("click", (e) => { if (e.target === lightbox) hideLightbox(); });
        document.addEventListener("keydown", (e) => {
            if (lightbox.classList.contains("hidden")) return;
            if (e.key === "ArrowLeft") showImage(currentIndex - 1);
            if (e.key === "ArrowRight") showImage(currentIndex + 1);
            if (e.key === "Escape") hideLightbox();
        });
    });

    // ============================================================
    // Form validation
    // ============================================================
    function showError(id, message) {
        let el = document.getElementById(id);
        if (el) { el.textContent = message; el.classList.remove("hidden"); }
    }
    function clearErrors() {
        ["errorPerceel","errorRegio","errorAdres","errorPhoto","errorDamage","errorNote"].forEach(id => {
            let el = document.getElementById(id);
            if (el) { el.textContent = ""; el.classList.add("hidden"); }
        });
    }
    function validateForm() {
        clearErrors();
        let isValid = true;
        if (!document.getElementById("perceelSelect").value) { showError("errorPerceel", "Kies een perceel."); isValid = false; }
        if (!document.getElementById("regioSelect").value) { showError("errorRegio", "Kies een regio."); isValid = false; }
        if (!document.getElementById("adresSelect").value) { showError("errorAdres", "Kies of maak een adresmap."); isValid = false; }
        if (document.getElementById("photoUpload").files.length === 0) { showError("errorPhoto", "Upload minstens 1 foto."); isValid = false; }
        if (!document.getElementById("damageNone").checked && !document.getElementById("damageYes").checked) { showError("errorDamage", "Selecteer schade optie."); isValid = false; }
        if (document.getElementById("damageYes").checked && !document.querySelector('#finishForm textarea[name="note"]').value.trim()) { showError("errorNote", "Notitie verplicht bij schade."); isValid = false; }
        return isValid;
    }

    // ============================================================
    // Compression
    // ============================================================
    async function compressInBatches(files, options, batchSize = 2) {
        const compressed = [];
        for (let i = 0; i < files.length; i += batchSize) {
            const batch = files.slice(i, i + batchSize);
            const results = await Promise.all(
                batch.map(async (file) => {
                    try { return await imageCompression(file, options); }
                    catch { return file; }
                })
            );
            compressed.push(...results);
            await new Promise(r => setTimeout(r, 25));
        }
        return compressed;
    }

    // ============================================================
    // 🔹 SUBMIT HANDLER (FIX 1: Schrijf naar IDB vanuit main thread)
    // ============================================================
    document.getElementById("finishForm").addEventListener("submit", async (e) => {
        e.preventDefault();
        if (!validateForm()) return;

        const form = e.target;
        const taskId = form.action.match(/tasks\/(\d+)/)?.[1];
        const files = [...document.getElementById("photoUpload").files].slice(0, 30);
        const adresSelect = document.getElementById("adresSelect");
        const namespaceId = adresSelect.options[adresSelect.selectedIndex]?.dataset.namespace;
        const adresPath = adresSelect.value;

        const finishButton = document.getElementById("finishButton");
        finishButton.disabled = true;
        finishButton.textContent = "Verwerken...";

        // Loader
        const loader = document.createElement("div");
        loader.className = "fixed inset-0 bg-black bg-opacity-60 flex flex-col items-center justify-center z-50";
        loader.innerHTML = `
            <div class="bg-white p-6 rounded-2xl shadow-xl flex flex-col items-center">
                <svg class="animate-spin h-8 w-8 text-[#B51D2D] mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <p id="loaderText" class="text-gray-700 text-sm font-medium">Foto's klaarmaken...</p>
            </div>`;
        document.body.appendChild(loader);

        try {
            if (files.length > 0) {
                const compressOptions = {
                    maxSizeMB: 0.45,
                    maxWidthOrHeight: 1280,
                    useWebWorker: true,
                    initialQuality: 0.65
                };

                const loaderText = document.getElementById("loaderText");

                // Comprimeren
                if (loaderText) loaderText.textContent = `Foto's comprimeren (0/${files.length})...`;
                const compressedFiles = await compressInBatches(files, compressOptions, 2);

                // 👇 FIX 1: Schrijf DIRECT naar IndexedDB vanuit main thread
                let savedCount = 0;
                for (let i = 0; i < compressedFiles.length; i++) {
                    if (loaderText) loaderText.textContent = `Opslaan ${i + 1}/${compressedFiles.length}...`;

                    try {
                        const added = await window.addToUploadQueue({
                            name: compressedFiles[i].name,
                            blob: compressedFiles[i],
                            fileType: compressedFiles[i].type,
                            task_id: taskId,
                            namespace_id: namespaceId,
                            adres_path: adresPath,
                        });
                        if (added) savedCount++;
                    } catch (err) {
                        console.error(`❌ Kon foto '${compressedFiles[i].name}' niet opslaan:`, err);
                    }
                }

                console.log(`✅ ${savedCount}/${compressedFiles.length} foto's opgeslagen in IndexedDB`);

                // 👇 Vertel de SW dat er werk is (maar data staat al veilig in IDB)
                window.sendToSW({ type: "PROCESS_QUEUE" });

                if (loaderText) loaderText.textContent = `📦 ${savedCount} foto's in wachtrij!`;
            }

            // ✅ FIX 4: Gebruik fetch i.p.v. sendBeacon voor status update
            const finishUrl = `/tasks/${taskId}/finish`;
            const statusData = new FormData();
            statusData.append("_token", document.querySelector('meta[name="csrf-token"]').content);
            statusData.append("damage", form.querySelector('input[name="damage"]:checked')?.value || "");
            statusData.append("note", form.querySelector('textarea[name="note"]').value || "");

            const res = await fetch(finishUrl, {
                method: "POST",
                headers: { "Accept": "application/json" },
                body: statusData
            });

            if (res.ok) {
                const json = await res.json();
                handleFrontendSuccess(taskId, form, loader, json.status);
            } else {
                showToast("⚠️ Status update mislukt. Foto's staan wel in wachtrij.", 5000);
                removeLoader(loader);
            }

        } catch (err) {
            console.error("Fout in submit flow:", err);
            showToast("⚠️ Er ging iets mis. Probeer opnieuw.", 5000);
            removeLoader(loader);
        } finally {
            finishButton.disabled = false;
            finishButton.textContent = "Voltooien";
        }
    });

    function handleFrontendSuccess(taskId, form, loader, serverStatus = null) {
        const row = document.querySelector(`tr[data-task-id="${taskId}"]`);
        const currentStatus = row?.dataset.status;
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
        if (loaderText) loaderText.textContent = "✅ Opgeslagen! Upload draait op achtergrond.";

        showToast("📂 Wijzigingen opgeslagen!", 4000);
        closeTaskForm();
        setTimeout(() => removeLoader(loader), 1500);
    }

    function removeLoader(loader) {
        if (loader) {
            loader.classList.add("opacity-0");
            setTimeout(() => loader.remove(), 300);
        }
    }

    function updateTaskStatusRow(taskId, newStatus) {
        const row = document.querySelector(`tr[data-task-id="${taskId}"]`);
        if (!row) return;
        row.dataset.status = newStatus;
        const span = row.querySelector("td:nth-child(3) span");
        if (!span) return;
        span.textContent = newStatus;
        span.className = "px-2 py-1 rounded text-sm font-semibold";
        switch (newStatus) {
            case "finished": span.classList.add("bg-green-200", "text-green-800"); break;
            case "in behandeling": span.classList.add("bg-yellow-200", "text-yellow-800"); break;
            default: span.classList.add("bg-gray-200", "text-gray-800");
        }
    }

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

    // ============================================================
    // Task form openen
    // ============================================================
    function openTaskForm(taskId, address, time, status, note) {
        loadPercelen();
        document.getElementById("adresSelect").innerHTML = "";
        document.getElementById("adresComboInput").value = "";
        document.getElementById("adresSelect").disabled = true;
        document.getElementById("adresDropdown").classList.add("hidden");
        localStorage.removeItem("tempAdresFolder");

        document.getElementById('taskFormPanel').classList.remove('hidden');

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

        damageNone.addEventListener('change', () => {
            if (damageNone.checked) { noteWrapper.classList.add('hidden'); noteField.value = ''; }
        });
        damageYes.addEventListener('change', () => {
            if (damageYes.checked) noteWrapper.classList.remove('hidden');
        });
    }

    document.querySelectorAll("tbody tr").forEach(row => {
        row.addEventListener("click", function() {
            openTaskForm(this.dataset.taskId, this.dataset.address, this.dataset.time, this.dataset.status, this.dataset.note);
        });
    });
</script>

</x-layouts.dashboard>
