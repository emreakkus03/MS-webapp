<x-layouts.dashboard>
    <div class="md:p-6">

        <h1 class="text-2xl font-bold mb-4">Bekijk hier jouw ploegen</h1>
    
        <button type="button" onclick="openCreateModal()"
            class="mb-6 bg-[#283142] text-white px-4 py-2 rounded hover:bg-[#B51D2D]">
            Nieuwe Ploeg
        </button>
    
        <table class="w-full border-collapse border border-gray-300">
            <thead>
                <tr class="bg-gray-200">
                    <th class="p-2 border">Naam</th>
                    <th class="p-2 border">Leden</th>
                    <th class="p-2 border">Rol</th>
                    <th class="p-2 border">Acties</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($teams as $team)
                    <tr class="text-center">
                        <td class="p-2 border">{{ $team->name }}</td>
                        <td class="p-2 border">{{ $team->members }}</td>
                        <td class="p-2 border">{{ $team->role }}</td>
                        <td class="p-2 border">
                            <a href="{{ route('teams.edit', $team->id) }}" class="text-blue-600 hover:text-blue-800 mr-2">
                                Bewerken
                            </a>
                            
                            <button type="button" class="text-red-600 hover:text-red-800"
                                onclick="openDeleteModal({{ $team->id }})">
                                Verwijderen
                            </button>
    
                            <form id="delete-form-{{ $team->id }}" action="{{ route('teams.destroy', $team->id) }}"
                                method="POST" class="hidden">
                                @csrf
                                @method('DELETE')
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    
        
        <div id="createModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-lg p-6">
                <h2 class="text-lg font-semibold mb-4">Nieuwe Ploeg Toevoegen</h2>
    
                @if (session('success'))
                    <div class="mb-2 text-green-600">{{ session('success') }}</div>
                @endif
    
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
                <p class="mb-6 text-gray-600">Weet je zeker dat je dit team wilt verwijderen? Deze actie kan niet ongedaan
                    worden gemaakt.</p>
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
</script>
