<x-layouts.dashboard>
<section>
    <div class="max-w-6xl mx-auto mt-10">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold text-gray-800">
                {{ $user->role === 'admin' ? 'Verlofbeheer' : 'Mijn Verlofaanvragen' }}
            </h2>

            @if($user->role !== 'admin')
                <a href="{{ route('leaves.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    + Nieuwe Aanvraag
                </a>
            @endif
        </div>

        @if(session('success'))
            <div class="bg-green-100 text-green-800 p-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="w-full text-sm text-gray-700">
                <thead class="bg-gray-50 uppercase text-gray-600 text-sm">
                    <tr>
                        @if($user->role === 'admin')
                            <th class="p-3 text-center">Team</th>
                        @endif
                        <th class="p-3 text-center">Member</th>
                        <th class="p-3 text-center">Type</th>
                        <th class="p-3 text-center">Periode</th>
                        <th class="p-3 text-center">Status</th>
                        @if($user->role === 'admin')
                            <th class="p-3 text-center">Acties</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $r)
                        <tr class="border-b hover:bg-gray-50">
                            @if($user->role === 'admin')
                                <td class="p-3 text-center font-medium">{{ $r->team->name }}</td>
                            @endif
                            <td class="p-3 text-center">{{ $r->member_name }}</td>
                            <td class="p-3 text-center">{{ $r->leaveType->name }}</td>
                            <td class="p-3 text-center">
                                {{ \Carbon\Carbon::parse($r->start_date)->format('d/m/Y') }} →
                                {{ \Carbon\Carbon::parse($r->end_date)->format('d/m/Y') }}
                            </td>
                            <td class="p-3 text-center">
                                @if($r->status === 'pending')
                                    <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded">In behandeling</span>
                                @elseif($r->status === 'approved')
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded">Goedgekeurd</span>
                                @else
                                    <span class="bg-red-100 text-red-800 px-2 py-1 rounded">Afgewezen</span>
                                @endif
                            </td>

                            @if($user->role === 'admin')
                                <td class="p-3 text-center space-x-2">
                                    @if($r->status === 'pending')
                                        <!-- ✅ Goedkeuren -->
                                        <form action="{{ route('leaves.status', $r->id) }}" method="POST" class="inline">
                                            @csrf
                                            <input type="hidden" name="status" value="approved">
                                            <button class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700" title="Goedkeuren">✅</button>
                                        </form>

                                        <!-- ❌ Afwijzen -->
                                        <form action="{{ route('leaves.status', $r->id) }}" method="POST" class="inline">
                                            @csrf
                                            <input type="hidden" name="status" value="rejected">
                                            <button class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700" title="Afwijzen">❌</button>
                                        </form>
                                    @else
                                        <!-- ✏️ Bewerken -->
                                        <a href="{{ route('leaves.edit', $r->id) }}" 
                                           class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 inline-block"
                                           title="Bewerken">✏️</a>

                                        <!-- 🗑️ Verwijderen -->
                                        <button type="button"
                                            class="bg-gray-500 text-white px-3 py-1 rounded hover:bg-gray-600"
                                            title="Verwijderen"
                                            onclick="openDeleteModal({{ $r->id }})">
                                            🗑️
                                        </button>

                                        <form id="delete-form-{{ $r->id }}"
                                              action="{{ route('leaves.destroy', $r->id) }}"
                                              method="POST"
                                              class="hidden">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center p-4 text-gray-500">Geen verlofaanvragen gevonden.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- 🗑️ Delete Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-sm p-6">
            <h2 class="text-lg font-semibold mb-4">Bevestig verwijderen</h2>
            <p class="mb-6 text-gray-600">Weet je zeker dat je deze verlofaanvraag wilt verwijderen? Deze actie kan niet ongedaan gemaakt worden.</p>
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
</section>

<script>
    let deleteFormId = null;

    function openDeleteModal(leaveId) {
        deleteFormId = `delete-form-${leaveId}`;
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
</script>
</x-layouts.dashboard>
