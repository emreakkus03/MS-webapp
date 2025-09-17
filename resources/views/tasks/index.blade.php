<x-layouts.dashboard>
    <div class="md:p-6">
        <h1 class="text-2xl font-bold mb-4">Taken</h1>

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
                    <option value="open" {{ request('status')==='open' ? 'selected' : '' }}>Open</option>
                    <option value="in behandeling" {{ request('status')==='in behandeling' ? 'selected' : '' }}>In behandeling</option>
                    <option value="finished" {{ request('status')==='finished' ? 'selected' : '' }}>Finished</option>
                    <option value="reopened" {{ request('status')==='reopened' ? 'selected' : '' }}>Reopened</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Zoek adres</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Straat / nr / postcode / stad"
                       class="border px-3 py-2 rounded w-64">
            </div>
        </form>

        <!-- Tabel -->
        <div class="overflow-x-auto">
            <table class="w-full border border-gray-300 rounded shadow text-sm">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border px-3 py-2 text-left">Datum & Tijd</th>
                        <th class="border px-3 py-2 text-left">Adres</th>
                        <th class="border px-3 py-2 text-left">Team</th>
                        <th class="border px-3 py-2 text-left">Status</th>
                        <th class="border px-3 py-2 text-left">Acties</th>
                    </tr>
                </thead>
                <tbody id="taskRows">
        @include('tasks._rows', ['tasks' => $tasks])
    </tbody>
            </table>
            <div id="taskPagination">
    @include('tasks._pagination', ['tasks' => $tasks])
</div>
        </div>
    </div>
</x-layouts.dashboard>


<script>
document.addEventListener("DOMContentLoaded", () => {
    const statusSelect = document.querySelector('select[name="status"]');
    const searchInput = document.querySelector('input[name="q"]');
    const taskRows = document.getElementById('taskRows');
    const taskPagination = document.getElementById('taskPagination');

    function fetchTasks(url = null) {
        const status = statusSelect.value;
        const q = searchInput.value;

        const baseUrl = "{{ route('tasks.filter') }}";
        const fetchUrl = url || `${baseUrl}?status=${encodeURIComponent(status)}&q=${encodeURIComponent(q)}`;


        fetch(fetchUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(res => res.json())
            .then(data => {
                taskRows.innerHTML = data.rows;
                taskPagination.innerHTML = data.pagination;

                // ✅ History API → update URL in browser
                window.history.pushState({}, '', fetchUrl);

                // Pagination links click intercept
                document.querySelectorAll('#taskPagination a').forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        fetchTasks(this.href);
                    });
                });
            })
            .catch(err => console.error(err));
    }

    // Realtime filter
    statusSelect.addEventListener('change', () => fetchTasks());
    searchInput.addEventListener('input', () => {
        clearTimeout(window.searchDebounce);
        window.searchDebounce = setTimeout(() => fetchTasks(), 300);
    });

    // Initial bind for pagination
    document.querySelectorAll('#taskPagination a').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            fetchTasks(this.href);
        });
    });
});
</script>
