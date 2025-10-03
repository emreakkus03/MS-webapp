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

        <!-- Desktop/tablet tabel -->
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

    <!-- Foto Lightbox -->
    <div id="photoModal" class="fixed inset-0 bg-black bg-opacity-75 hidden items-center justify-center z-50">
        <!-- Sluitknop -->
        <span onclick="closePhotoModal()"
              class="absolute top-5 right-8 text-white text-3xl cursor-pointer">&times;</span>

        <!-- Grote foto -->
        <img id="photoModalImg" src=""
             class="max-h-[90%] max-w-[90%] rounded shadow-lg border-4 border-white" />
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
</script>
