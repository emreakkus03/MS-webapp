<x-layouts.dashboard>
    <div class="md:p-6">
        <h1 class="text-2xl font-bold mb-4 text-center md:text-left">Beheer je taken hier</h1>

        @if (session('success'))
            <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-4 p-3 bg-red-100 text-red-700 rounded">
                {{ session('error') }}
            </div>
        @endif

        <!-- Filters -->
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Status</label>
                <select name="status" class="border px-3 py-2 rounded">
                    <option value="">Alle</option>
                    <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open</option>
                    <option value="in behandeling" {{ request('status') === 'in behandeling' ? 'selected' : '' }}>In behandeling</option>
                    <option value="finished" {{ request('status') === 'finished' ? 'selected' : '' }}>Finished</option>
                    <option value="reopened" {{ request('status') === 'reopened' ? 'selected' : '' }}>Reopened</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Zoek adres</label>
                <input type="text" name="q" value="{{ request('q') }}"
                       placeholder="Straat / nr / postcode / stad"
                       class="border px-3 py-2 rounded w-64">
            </div>
        </form>
<!-- Kolomfilter -->
<div class="mb-3 relative inline-block">
    <button id="toggleColumnsBtn" 
        class="flex items-center gap-1 px-3 py-2 bg-gray-100 border rounded hover:bg-gray-200">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-700" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L14 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 018 21v-7.586L3.293 6.707A1 1 0 013 6V4z" />
        </svg>
        <span class="text-sm text-gray-700">Kolommen</span>
    </button>

    <div id="columnsMenu"
        class="hidden absolute mt-2 left-0 bg-white border rounded shadow p-3 z-50 w-52 text-sm">
        <label class="flex items-center gap-2 mb-1"><input type="checkbox" data-col="1" checked> Datum & Tijd</label>
        <label class="flex items-center gap-2 mb-1"><input type="checkbox" data-col="2" checked> Adres</label>
        <label class="flex items-center gap-2 mb-1"><input type="checkbox" data-col="3" checked> Team</label>
        <label class="flex items-center gap-2 mb-1"><input type="checkbox" data-col="4" checked> Status</label>
        <label class="flex items-center gap-2 mb-1"><input type="checkbox" data-col="5" checked> Notitie</label>
        <label class="flex items-center gap-2 mb-1"><input type="checkbox" data-col="6" checked> Foto’s</label>
        <label class="flex items-center gap-2 mb-1"><input type="checkbox" data-col="7" checked> Acties</label>
    </div>
</div>

       <!-- Desktop/tablet tabel -->
<div class="overflow-x-auto hidden md:block">
    <table class="w-full border border-gray-300 rounded shadow text-sm">
        <thead>
            <tr class="bg-gray-100">
                <th class="border px-3 py-2 text-left">Datum & Tijd</th>
                <th class="border px-3 py-2 text-left">Adres</th>
                <th class="border px-3 py-2 text-left">Team</th>
                <th class="border px-3 py-2 text-left">Status</th>
                <th class="border px-3 py-2 text-left">Notitie</th>
                <th class="border px-3 py-2 text-left">Foto's</th>
                <th class="border px-3 py-2 text-left">Acties</th>
            </tr>
        </thead>
        <tbody id="taskRowsTable">
            @include('tasks._rows_table', ['tasks' => $tasks])
        </tbody>
    </table>
</div>

<!-- Mobiele cards -->
<div class="md:hidden space-y-4" id="taskRowsCards">
    @include('tasks._rows_cards', ['tasks' => $tasks])
</div>

        <!-- Paginatie -->
        <div id="taskPagination" class="mt-4">
            @include('tasks._pagination', ['tasks' => $tasks])
        </div>
    </div>

    <!-- 🔹 Gedeelde Foto-Lightbox -->
<div id="photoLightbox"
     class="fixed inset-0 bg-black bg-opacity-80 hidden items-center justify-center z-50 select-none">
    <!-- Sluitknop -->
    <span id="closeLightbox"
          class="absolute top-5 right-8 text-white text-4xl cursor-pointer">&times;</span>

    <!-- Pijlen -->
    <button id="prevPhoto"
            class="absolute left-5 text-white text-5xl p-2 bg-black bg-opacity-40 rounded-full hover:bg-opacity-70">
        &#10094;
    </button>
    <button id="nextPhoto"
            class="absolute right-5 text-white text-5xl p-2 bg-black bg-opacity-40 rounded-full hover:bg-opacity-70">
        &#10095;
    </button>

    <!-- Grote foto -->
    <img id="lightboxImg" src=""
         class="max-h-[90%] max-w-[90%] rounded shadow-lg border-4 border-white transition-transform duration-300" />
</div>

    <!-- Delete Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-sm p-6">
        <h2 class="text-lg font-semibold mb-4">Bevestig verwijderen</h2>
        <p class="mb-6 text-gray-600 text-center">
            Weet je zeker dat je deze taak wilt verwijderen?<br>
            <span class="text-sm text-gray-400">Deze actie kan niet ongedaan gemaakt worden.</span>
        </p>
        <div class="flex justify-center gap-3">
            <button type="button" onclick="closeDeleteModal()"
                class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">
                Annuleren
            </button>
            <button type="button" id="confirmDeleteBtn"
                class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                Verwijderen
            </button>
        </div>
    </div>
</div>

</x-layouts.dashboard>

<script>

    // Delete modal
let deleteFormId = null;

function openDeleteModal(taskId) {
    deleteFormId = `delete-form-${taskId}`;
    document.getElementById('deleteModal').classList.remove('hidden');
    document.getElementById('deleteModal').classList.add('flex');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    document.getElementById('deleteModal').classList.remove('flex');
    deleteFormId = null;
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (deleteFormId) {
        document.getElementById(deleteFormId).submit();
    }
});

    function openPhotoModal(src) {
        document.getElementById('photoModalImg').src = src;
        document.getElementById('photoModal').classList.remove('hidden');
        document.getElementById('photoModal').classList.add('flex');
    }

    function closePhotoModal() {
        document.getElementById('photoModal').classList.add('hidden');
        document.getElementById('photoModal').classList.remove('flex');
    }

   document.addEventListener("DOMContentLoaded", () => {
    const statusSelect = document.querySelector('select[name="status"]');
    const searchInput = document.querySelector('input[name="q"]');
    const taskRowsTable = document.getElementById('taskRowsTable');
    const taskRowsCards = document.getElementById('taskRowsCards');
    const taskPagination = document.getElementById('taskPagination');

    function fetchTasks(url = null) {
        const status = statusSelect.value;
        const q = searchInput.value;
        const baseUrl = "{{ route('tasks.filter') }}";
        const fetchUrl = url || `${baseUrl}?status=${encodeURIComponent(status)}&q=${encodeURIComponent(q)}`;

        fetch(fetchUrl, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
           if (taskRowsTable) taskRowsTable.innerHTML = data.rows_table;
    if (taskRowsCards) taskRowsCards.innerHTML = data.rows_cards;
    if (taskPagination) taskPagination.innerHTML = data.pagination;

            // push state naar browser url
            window.history.pushState({}, '', fetchUrl);

            // herbind pagination links
            document.querySelectorAll('#taskPagination a').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    fetchTasks(this.href);
                });
            });
        })
        .catch(err => console.error(err));
    }

    // Realtime zoeken met debounce
    searchInput.addEventListener('input', () => {
        clearTimeout(window.searchDebounce);
        window.searchDebounce = setTimeout(() => fetchTasks(), 300);
    });

    // Filter direct toepassen
    statusSelect.addEventListener('change', () => fetchTasks());

    // Init pagination clicks
    document.querySelectorAll('#taskPagination a').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            fetchTasks(this.href);
        });
    });
});

// ==================== 🖼️ Gedeelde Lightbox ====================
document.addEventListener("DOMContentLoaded", () => {
    const lightbox = document.getElementById("photoLightbox");
    const lightboxImg = document.getElementById("lightboxImg");
    const closeBtn = document.getElementById("closeLightbox");
    const prevBtn = document.getElementById("prevPhoto");
    const nextBtn = document.getElementById("nextPhoto");

    let images = [];
    let currentIndex = 0;

    // Klik op foto → open lightbox
    document.querySelectorAll("img[onclick^='openPhotoModal']").forEach((img, index, arr) => {
        img.addEventListener("click", () => {
            images = arr;
            currentIndex = index;
            showImage(currentIndex);
        });
    });

    function showImage(index) {
        if (index < 0) index = images.length - 1;
        if (index >= images.length) index = 0;
        currentIndex = index;
        lightboxImg.src = images[currentIndex].src;
        lightbox.classList.remove("hidden");
        lightbox.classList.add("flex");
    }

    prevBtn.addEventListener("click", e => {
        e.stopPropagation();
        showImage(currentIndex - 1);
    });
    nextBtn.addEventListener("click", e => {
        e.stopPropagation();
        showImage(currentIndex + 1);
    });

    closeBtn.addEventListener("click", () => {
        lightbox.classList.add("hidden");
        lightbox.classList.remove("flex");
    });

    lightbox.addEventListener("click", (e) => {
        if (e.target === lightbox) {
            lightbox.classList.add("hidden");
            lightbox.classList.remove("flex");
        }
    });

    document.addEventListener("keydown", (e) => {
        if (lightbox.classList.contains("hidden")) return;
        if (e.key === "ArrowLeft") showImage(currentIndex - 1);
        if (e.key === "ArrowRight") showImage(currentIndex + 1);
        if (e.key === "Escape") {
            lightbox.classList.add("hidden");
            lightbox.classList.remove("flex");
        }
    });
});
// ==================== Kolomfilter ====================
document.addEventListener("DOMContentLoaded", () => {
    const toggleBtn = document.getElementById("toggleColumnsBtn");
    const menu = document.getElementById("columnsMenu");
    const checkboxes = menu.querySelectorAll("input[type='checkbox']");
    const table = document.querySelector("table");

    // Toon/verberg menu
    toggleBtn.addEventListener("click", () => {
        menu.classList.toggle("hidden");
    });

    // Klik buiten menu = sluiten
    document.addEventListener("click", (e) => {
        if (!menu.contains(e.target) && !toggleBtn.contains(e.target)) {
            menu.classList.add("hidden");
        }
    });

    // Toon/verberg kolommen
    checkboxes.forEach(cb => {
        cb.addEventListener("change", () => {
            const colIndex = parseInt(cb.dataset.col);
            const cells = table.querySelectorAll(`th:nth-child(${colIndex}), td:nth-child(${colIndex})`);

            cells.forEach(cell => {
                cell.style.display = cb.checked ? "" : "none";
            });

            // Onthoud voorkeur
            saveColumnPrefs();
        });
    });

    // Onthoud keuze in localStorage
    function saveColumnPrefs() {
        const prefs = {};
        checkboxes.forEach(cb => prefs[cb.dataset.col] = cb.checked);
        localStorage.setItem("columnPrefs", JSON.stringify(prefs));
    }

    // Herstel vorige voorkeur
    const saved = JSON.parse(localStorage.getItem("columnPrefs") || "{}");
    Object.keys(saved).forEach(key => {
        const cb = menu.querySelector(`input[data-col='${key}']`);
        if (cb) {
            cb.checked = saved[key];
            const cells = table.querySelectorAll(`th:nth-child(${key}), td:nth-child(${key})`);
            cells.forEach(cell => {
                cell.style.display = cb.checked ? "" : "none";
            });
        }
    });
});

</script>
