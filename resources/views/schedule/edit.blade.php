<x-layouts.dashboard>
    <div class="md:p-6">
        <h1 class="text-2xl font-bold mb-4">Taak Bewerken</h1>

        <form action="{{ route('schedule.update', $task) }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')
             <input type="hidden" name="redirect_to" value="{{ request('redirect') }}">

            @if(Auth::user()->role === 'admin')
            <div>
                <label class="block text-sm font-medium">Ploeg</label>
                <select name="team_id" class="w-full border px-3 py-2 rounded">
                    @foreach($teams as $team)
                        <option value="{{ $team->id }}" {{ $task->team_id == $team->id ? 'selected' : '' }}>
                            {{ $team->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif

            <div>
                <label class="block text-sm font-medium">Tijdstip</label>
                <input type="datetime-local" name="time" value="{{ \Carbon\Carbon::parse($task->time)->format('Y-m-d\TH:i') }}" required class="w-full border px-3 py-2 rounded">
            </div>

            <div>
                <label class="block text-sm font-medium">Adres</label>
                <input list="addresses" name="address_name" class="w-full border px-3 py-2 rounded" value="{{ $task->address->street }}" required>
                <datalist id="addresses">
                    @foreach($addresses as $address)
                        <option value="{{ $address->street }}">{{ $address->number }}, {{ $address->zipcode }}, {{ $address->city }}</option>
                    @endforeach
                </datalist>
            </div>

            <div>
                <label class="block text-sm font-medium">Nummer</label>
                <input type="text" name="address_number" value="{{ $task->address->number }}" class="w-full border px-3 py-2 rounded">
            </div>

            <div>
                <label class="block text-sm font-medium">Postcode</label>
                <input type="text" name="address_zipcode" value="{{ $task->address->zipcode }}" class="w-full border px-3 py-2 rounded">
            </div>

            <div>
                <label class="block text-sm font-medium">Stad</label>
                <input type="text" name="address_city" value="{{ $task->address->city }}" class="w-full border px-3 py-2 rounded">
            </div>

         

            <div class="flex justify-end gap-3 mt-4">
                <a href="{{ route('schedule.index') }}" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Annuleren</a>
                <button type="submit" class="bg-[#283142] text-white px-4 py-2 rounded hover:bg-[#B51D2D]">Opslaan</button>
            </div>
        </form>
    </div>
</x-layouts.dashboard>
