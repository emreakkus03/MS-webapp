<x-layouts.dashboard>
    <h1 class="text-2xl font-bold mb-4 text-center">Ploeg Bewerken</h1>

    <form action="{{ route('teams.update', $team->id) }}" method="POST" class="space-y-4 bg-white p-6 rounded shadow">
        @csrf
        @method('PUT')

        <!-- Ploegnaam -->
        <div>
            <label class="block text-sm font-medium">Ploegnaam</label>
            <input 
                type="text" 
                name="name" 
                value="{{ $team->name }}" 
                required 
                class="w-full border px-3 py-2 rounded"
            >
        </div>

        <!-- Wachtwoord -->
        <div>
            <label class="block text-sm font-medium">Wachtwoord (laat leeg om niet te wijzigen)</label>
            <input 
                type="password" 
                name="password" 
                class="w-full border px-3 py-2 rounded"
            >
        </div>

        <!-- Rol -->
        <div>
            <label class="block text-sm font-medium">Rol</label>
            <select name="role" class="w-full border px-3 py-2 rounded">
                <option value="team" {{ $team->role === 'team' ? 'selected' : '' }}>team</option>
                <option value="admin" {{ $team->role === 'admin' ? 'selected' : '' }}>admin</option>
            </select>
        </div>

        <!-- Leden -->
        <div>
            <label class="block text-sm font-medium">Leden</label>
            <input 
                id="membersInput" 
                name="members" 
                value="{{ $team->members }}" 
                class="w-full border px-3 py-2 rounded" 
                placeholder="Typ een naam en druk op Enter"
            >
        </div>

        <!-- Acties -->
        <div class="flex justify-between">
            <a 
                href="{{ route('teams.index') }}" 
                class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400"
            >
                Annuleren
            </a>
            <button 
                type="submit" 
                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
            >
                Opslaan
            </button>
        </div>
    </form>

    <!-- ✅ Tagify -->
    <link 
        rel="stylesheet" 
        href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css"
    >
    <script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const input = document.querySelector('#membersInput');

            // Convert de bestaande CSV-waarde (bijv. "Emre, Jan van den Berg") naar tags
            const initialValue = input.value
                ? input.value.split(',').map(name => ({ value: name.trim() }))
                : [];

            const tagify = new Tagify(input, {
                delimiters: ",", // maakt tags bij Enter of komma
                pattern: /^[A-Za-zÀ-ÿ\s'-]+$/, // alleen letters en spaties
                originalInputValueFormat: valuesArr => 
                    valuesArr.map(item => item.value).join(', '),
            });

            // Stel bestaande namen in als tags
            tagify.addTags(initialValue);
        });
    </script>
</x-layouts.dashboard>
