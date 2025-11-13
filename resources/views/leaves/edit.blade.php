<x-layouts.dashboard>
<section>
    <div class="max-w-3xl mx-auto bg-white p-6 rounded-xl shadow-md mt-10">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4 text-center">Verlofaanvraag Bewerken</h2>

        <!-- ✅ Toon validatiefouten -->
        @if ($errors->any())
            <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <strong>Er zijn fouten gevonden:</strong>
                <ul class="mt-2 list-disc list-inside text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="editForm" action="{{ route('leaves.update', $leave->id) }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <!-- Teamlid (readonly) -->
            <div>
                <label class="block text-gray-600 mb-1">Teamlid</label>
                <input type="text" 
                       name="member_name" 
                       value="{{ $leave->member_name }}" 
                       readonly
                       class="w-full border-gray-300 rounded-lg bg-gray-100 text-gray-600">
            </div>

            <!-- Verloftype -->
            <div>
                <label class="block text-gray-600 mb-1">Verloftype</label>
                <select id="leave_type_id" name="leave_type_id" class="w-full border-gray-300 rounded-lg" required>
                    <option value="">-- Selecteer verloftype --</option>
                    @foreach($leaveTypes as $type)
                        <option value="{{ $type->id }}" {{ $leave->leave_type_id == $type->id ? 'selected' : '' }}>
                            {{ $type->name }}
                        </option>
                    @endforeach
                </select>
                <p id="errorType" class="text-red-500 text-sm mt-1 hidden"></p>
            </div>

            <!-- Data -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-600 mb-1">Startdatum</label>
                    <input 
                        type="date" 
                        id="start_date"
                        name="start_date"
                        value="{{ $leave->start_date }}"
                        class="w-full border-gray-300 rounded-lg"
                        required
                    >
                    <p id="errorStart" class="text-red-500 text-sm mt-1 hidden"></p>
                </div>

                <div>
                    <label class="block text-gray-600 mb-1">Einddatum</label>
                    <input 
                        type="date" 
                        id="end_date"
                        name="end_date"
                        value="{{ $leave->end_date }}"
                        class="w-full border-gray-300 rounded-lg"
                        required
                    >
                    <p id="errorEnd" class="text-red-500 text-sm mt-1 hidden"></p>
                </div>
            </div>

            <!-- ✅ Status dropdown -->
            @if($user->role === 'admin')
            <div>
                <label class="block text-gray-600 mb-1">Status</label>
                <select name="status" id="status" class="w-full border-gray-300 rounded-lg py-2">
                    <option value="pending" {{ $leave->status === 'pending' ? 'selected' : '' }}>🕓 In afwachting</option>
                    <option value="approved" {{ $leave->status === 'approved' ? 'selected' : '' }}>✅ Goedgekeurd</option>
                    <option value="rejected" {{ $leave->status === 'rejected' ? 'selected' : '' }}>❌ Afgewezen</option>
                </select>
            </div>
            @endif

            <!-- Knoppen -->
            <div class="flex justify-end gap-3">
                <a href="{{ route('leaves.index') }}" 
                   class="bg-gray-300 text-gray-800 px-6 py-2 rounded-lg hover:bg-gray-400 transition">
                    Annuleren
                </a>

                <button type="submit" 
                        class="bg-[#283142] text-white px-6 py-2 rounded-lg hover:bg-[#B51D2D] transition">
                    Opslaan
                </button>
            </div>
        </form>
    </div>
</section>
</x-layouts.dashboard>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const startInput = document.getElementById('start_date');
    const endInput = document.getElementById('end_date');
    const form = document.getElementById('editForm');

    // ✅ Minimaal 2 weken op voorhand
    const today = new Date();
    today.setDate(today.getDate() + 14);
    const minDate = today.toISOString().split('T')[0];

    startInput.min = minDate;
    endInput.min = minDate;

    // ✅ Einddatum aanpassen op basis van startdatum
    startInput.addEventListener('change', function () {
        endInput.min = this.value;
        if (endInput.value && endInput.value < this.value) {
            endInput.value = this.value;
        }
    });

    // ✅ Validatie bij submit
    form.addEventListener('submit', function (e) {
        let valid = true;

        document.querySelectorAll('[id^="error"]').forEach(el => {
            el.textContent = '';
            el.classList.add('hidden');
        });

        const startDate = new Date(startInput.value);
        const minStart = new Date(minDate);
        const endDate = new Date(endInput.value);

        if (!startInput.value) {
            document.getElementById('errorStart').textContent = 'Selecteer een startdatum.';
            document.getElementById('errorStart').classList.remove('hidden');
            valid = false;
        } else if (startDate < minStart) {
            document.getElementById('errorStart').textContent = 'Startdatum moet minstens 2 weken op voorhand liggen.';
            document.getElementById('errorStart').classList.remove('hidden');
            valid = false;
        }

        if (!endInput.value) {
            document.getElementById('errorEnd').textContent = 'Selecteer een einddatum.';
            document.getElementById('errorEnd').classList.remove('hidden');
            valid = false;
        } else if (endDate < startDate) {
            document.getElementById('errorEnd').textContent = 'Einddatum mag niet vóór de startdatum liggen.';
            document.getElementById('errorEnd').classList.remove('hidden');
            valid = false;
        }

        if (!valid) e.preventDefault();
    });
});
</script>
