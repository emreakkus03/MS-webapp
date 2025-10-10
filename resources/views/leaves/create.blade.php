<x-layouts.dashboard>
<section>
    <div class="max-w-3xl mx-auto bg-white p-6 rounded-xl shadow-md mt-10">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4">Nieuwe Verlofaanvraag</h2>

        <!-- ✅ Server-side fouten -->
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

        <form id="leaveForm" action="{{ route('leaves.store') }}" method="POST" class="space-y-4">
            @csrf

            <!-- Teamlid -->
            <div>
                <label class="block text-gray-600 mb-1">Teamlid</label>
                <select id="member_name" name="member_name" class="w-full border-gray-300 rounded-lg" >
                    <option value="">-- Selecteer teamlid --</option>
                    @foreach($members as $member)
                        <option value="{{ $member }}" {{ old('member_name') === $member ? 'selected' : '' }}>
                            {{ $member }}
                        </option>
                    @endforeach
                </select>
                <p id="errorMember" class="text-red-500 text-sm mt-1 hidden"></p>
            </div>

            <!-- Verloftype -->
            <div>
                <label class="block text-gray-600 mb-1">Verloftype</label>
                <select id="leave_type_id" name="leave_type_id" class="w-full border-gray-300 rounded-lg" >
                    <option value="">-- Selecteer verloftype --</option>
                    @foreach($leaveTypes as $type)
                        <option value="{{ $type->id }}" {{ old('leave_type_id') == $type->id ? 'selected' : '' }}>
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
                        name="start_date" 
                        id="start_date"
                        value="{{ old('start_date') }}"
                        class="w-full border-gray-300 rounded-lg" 
                        
                    >
                    <p id="errorStart" class="text-red-500 text-sm mt-1 hidden"></p>
                </div>
                <div>
                    <label class="block text-gray-600 mb-1">Einddatum</label>
                    <input 
                        type="date" 
                        name="end_date" 
                        id="end_date"
                        value="{{ old('end_date') }}"
                        class="w-full border-gray-300 rounded-lg" 
                        
                    >
                    <p id="errorEnd" class="text-red-500 text-sm mt-1 hidden"></p>
                </div>
            </div>

            <!-- Knoppen -->
            <div class="flex justify-end gap-3">
                <a href="{{ route('leaves.index') }}" 
                   class="bg-gray-300 text-gray-800 px-6 py-2 rounded-lg hover:bg-gray-400 transition">
                    Annuleren
                </a>

                <button type="submit" 
                        class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
                    Verzoek Indienen
                </button>
            </div>
        </form>
    </div>
</section>
</x-layouts.dashboard>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('leaveForm');
    const startInput = document.getElementById('start_date');
    const endInput = document.getElementById('end_date');
    const memberInput = document.getElementById('member_name');
    const typeInput = document.getElementById('leave_type_id');

    // ✅ Bereken de datum van over 14 dagen
    const today = new Date();
    today.setDate(today.getDate() + 14);
    const minDate = today.toISOString().split('T')[0];
    startInput.min = minDate;
    endInput.min = minDate;

    // ✅ Einddatum afhankelijk van startdatum
    startInput.addEventListener('change', function () {
        endInput.min = this.value;
        if (endInput.value && endInput.value < this.value) {
            endInput.value = this.value;
        }
    });

    // ✅ Custom front-end validatie
    form.addEventListener('submit', function (e) {
        let valid = true;

        // Helper om fout te tonen
        const showError = (id, msg) => {
            const el = document.getElementById(id);
            el.textContent = msg;
            el.classList.remove('hidden');
        };

        // Helper om fout te verbergen
        const clearErrors = () => {
            document.querySelectorAll('[id^="error"]').forEach(el => {
                el.textContent = '';
                el.classList.add('hidden');
            });
        };

        clearErrors();

        // Teamlid
        if (!memberInput.value) {
            showError('errorMember', 'Kies een teamlid.');
            valid = false;
        }

        // Verloftype
        if (!typeInput.value) {
            showError('errorType', 'Kies een verloftype.');
            valid = false;
        }

        // Startdatum
        const startDate = new Date(startInput.value);
        const minStart = new Date(minDate);
        if (!startInput.value) {
            showError('errorStart', 'Selecteer een startdatum.');
            valid = false;
        } else if (startDate < minStart) {
            showError('errorStart', 'De startdatum moet minstens 2 weken op voorhand liggen.');
            valid = false;
        }

        // Einddatum
        const endDate = new Date(endInput.value);
        if (!endInput.value) {
            showError('errorEnd', 'Selecteer een einddatum.');
            valid = false;
        } else if (endDate < minStart) {
            showError('errorEnd', 'De einddatum moet minstens 2 weken op voorhand liggen.');
            valid = false;
        } else if (startInput.value && endDate < startDate) {
            showError('errorEnd', 'De einddatum mag niet vóór de startdatum liggen.');
            valid = false;
        }

        if (!valid) {
            e.preventDefault(); // 🚫 Stop verzenden als er fouten zijn
        }
    });
});
</script>
