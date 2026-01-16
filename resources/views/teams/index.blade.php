<x-layouts.dashboard>
    <div class="md:p-6">
        <h1 class="text-2xl font-bold mb-8 text-center md:text-left">Bekijk hier jouw ploegen</h1>

        <!-- Nieuwe ploeg knop -->
        <button type="button" onclick="openCreateModal()"
            class="mb-6 bg-[#283142] text-white px-4 py-2 rounded hover:bg-[#B51D2D]">
            Nieuwe Ploeg
        </button>

        <!-- Filters -->
        <div class="mb-6 flex flex-col sm:flex-row gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Filter op rol</label>
                <select id="roleFilter" class="border px-3 py-2 rounded w-full sm:w-auto">
                    <option value="">Alle rollen</option>
                    <option value="admin" {{ ($filters['role'] ?? '') === 'admin' ? 'selected' : '' }}>Admin</option>
                    <option value="team" {{ ($filters['role'] ?? '') === 'team' ? 'selected' : '' }}>Team</option>
                </select>
            </div>

            <div class="flex-1">
                <label class="block text-sm font-medium mb-1">Zoek op naam</label>
                <input type="text" id="searchFilter" value="{{ $filters['search'] ?? '' }}"
                    placeholder="Teamnaam..." class="border px-3 py-2 rounded w-full sm:w-48">
            </div>
        </div>

        <!-- ✅ Desktop / Tablet: tabel -->
        <div class="overflow-x-auto hidden md:block">
    <table class="min-w-full border border-gray-200 rounded-lg shadow-sm text-sm">
        <thead class="sticky top-0 bg-gray-100 z-10">
            <tr>
                <th class="px-4 py-2 border-b text-left font-semibold">Naam</th>
                <th class="px-4 py-2 border-b text-left font-semibold">Leden</th>
                <th class="px-4 py-2 border-b text-left font-semibold hidden sm:table-cell">Rol</th>
                
                <th class="px-4 py-2 border-b text-left font-semibold">Acties</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($teams as $team)
                <tr class="hover:bg-gray-50 transition">
                    <!-- Naam -->
                    <td class="px-4 py-2 border-b font-medium text-gray-800">
                        {{ $team->name }}
                    </td>

                    <!-- Leden -->
                    <td class="px-4 py-2 border-b text-gray-600">
                        <div class="max-w-[250px] truncate">
                            {{ $team->members ?: '-' }}
                        </div>
                    </td>

                    <!-- Rol (verborgen op kleinere tablets) -->
                    <td class="px-4 py-2 border-b text-gray-600 hidden sm:table-cell capitalize">
                        {{ $team->role }}
                    </td>

                    <!-- Acties -->
                    <td class="px-4 py-2 border-b">
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('teams.edit', $team->id) }}"
                               class="px-3 py-1.5 rounded bg-[#2ea5d7] text-white hover:bg-[#2eb5d7] text-xs sm:text-sm shadow-sm">
                                Bewerken
                            </a>

                            <button type="button"
                                class="px-3 py-1.5 rounded bg-[#B51D2D] text-white hover:bg-[#B53D2D] text-xs sm:text-sm shadow-sm"
                                onclick="openDeleteModal({{ $team->id }})">
                                Verwijderen
                            </button>
                        </div>

                        <form id="delete-form-{{ $team->id }}"
                              action="{{ route('teams.destroy', $team->id) }}"
                              method="POST" class="hidden">
                            @csrf
                            @method('DELETE')
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

        <!-- ✅ Mobile: cards -->
        <div class="md:hidden space-y-4">
            @foreach ($teams as $team)
                <div class="bg-white rounded-lg shadow p-4 space-y-2 border">
                    <div>
                        <span class="block text-sm font-medium text-gray-600">Naam</span>
                        <span class="text-base font-semibold">{{ $team->name }}</span>
                    </div>
                    <div>
                        <span class="block text-sm font-medium text-gray-600">Leden</span>
                        <span class="text-sm">{{ $team->members ?: '-' }}</span>
                    </div>
                    <div>
                        <span class="block text-sm font-medium text-gray-600">Rol</span>
                        <span class="text-sm capitalize">{{ $team->role }}</span>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <a href="{{ route('teams.edit', $team->id) }}"
                           class="flex-1 text-center px-3 py-2 rounded bg-[#2ea5d7] text-white hover:bg-[#2eb5d7] text-sm">
                            Bewerken
                        </a>
                        <button type="button"
                            class="flex-1 px-3 py-2 rounded bg-[#B51D2D] text-white hover:bg-[#B53D2D] text-sm"
                            onclick="openDeleteModal({{ $team->id }})">
                            Verwijderen
                        </button>
                    </div>

                    <form id="delete-form-{{ $team->id }}"
                          action="{{ route('teams.destroy', $team->id) }}"
                          method="POST" class="hidden">
                        @csrf
                        @method('DELETE')
                    </form>
                </div>
            @endforeach
        </div>

        <!-- Create Modal -->
        <div id="createModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-lg p-6">
                <h2 class="text-lg font-semibold mb-4">Nieuwe Ploeg Toevoegen</h2>
                <form action="{{ url('/teams') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium">Ploegnaam</label>
                        <input type="text" name="name" required class="w-full border px-3 py-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Wachtwoord</label>
                        <input type="password" name="password" required class="w-full border px-3 py-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Rol</label>
                        <select name="role" class="w-full border px-3 py-2 rounded">
                            <option value="team" selected>team</option>
                            <option value="warehouseman">warehouseman</option>
                            <option value="admin">admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Leden (komma gescheiden)</label>
                        <input type="text" name="members" class="w-full border px-3 py-2 rounded"
                               placeholder="Emre, Jan, ...">
                    </div>
                    <div class="flex justify-center gap-3">
                        <button type="button" onclick="closeCreateModal()"
                            class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">
                            Annuleren
                        </button>
                        <button type="submit" class="bg-[#283142] text-white px-4 py-2 rounded hover:bg-[#284142]">
                            Ploeg Toevoegen
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Modal -->
        <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Bevestig verwijderen</h2>
                <p class="mb-6 text-gray-600">Weet je zeker dat je dit team wilt verwijderen? Deze actie kan niet ongedaan gemaakt worden.</p>
                <div class="flex justify-end gap-3">
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
    </div>
</x-layouts.dashboard>

<script>
    // Delete modal
    let deleteFormId = null;

    function openDeleteModal(teamId) {
        deleteFormId = `delete-form-${teamId}`;
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

    // Create modal
    function openCreateModal() {
        document.getElementById('createModal').classList.remove('hidden');
        document.getElementById('createModal').classList.add('flex');
    }

    function closeCreateModal() {
        document.getElementById('createModal').classList.add('hidden');
        document.getElementById('createModal').classList.remove('flex');
    }

    // Filters
    document.addEventListener("DOMContentLoaded", function () {
        const roleFilter = document.getElementById("roleFilter");
        const searchFilter = document.getElementById("searchFilter");

        function updateTeams() {
            let role = roleFilter.value;
            let search = searchFilter.value;

            let params = new URLSearchParams();
            if (role) params.append("role", role);
            if (search) params.append("search", search);

            window.location.href = "{{ route('teams.index') }}?" + params.toString();
        }

        roleFilter.addEventListener("change", updateTeams);

        let searchTimeout;
        searchFilter.addEventListener("input", function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(updateTeams, 500);
        });
    });
</script>
