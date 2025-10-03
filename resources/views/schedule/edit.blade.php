<x-layouts.dashboard>
    <div class="max-w-3xl mx-auto md:p-6">
        <h1 class="text-2xl font-bold mb-6 text-center md:text-left text-gray-800">
            Taak Bewerken
        </h1>

        <form action="{{ route('schedule.update', $task) }}" method="POST" class="bg-white shadow rounded-lg p-6 space-y-6">
            @csrf
            @method('PUT')
            <input type="hidden" name="redirect_to" value="{{ request('redirect') }}">

            @if(Auth::user()->role === 'admin')
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ploeg</label>
                    <select name="team_id" 
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-[#283142]/50 focus:border-[#283142]">
                        @foreach($teams as $team)
                            <option value="{{ $team->id }}" {{ $task->team_id == $team->id ? 'selected' : '' }}>
                                {{ $team->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tijdstip</label>
                <input type="datetime-local" name="time" 
                    value="{{ \Carbon\Carbon::parse($task->time)->format('Y-m-d\TH:i') }}" required
                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-[#283142]/50 focus:border-[#283142]">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Adres</label>
                <input list="addresses" name="address_name" 
                    value="{{ $task->address->street }}" required
                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-[#283142]/50 focus:border-[#283142]">
                <datalist id="addresses">
                    @foreach($addresses as $address)
                        <option value="{{ $address->street }}">{{ $address->number }}, {{ $address->zipcode }}, {{ $address->city }}</option>
                    @endforeach
                </datalist>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nummer</label>
                    <input type="text" name="address_number" 
                        value="{{ $task->address->number }}"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-[#283142]/50 focus:border-[#283142]">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Postcode</label>
                    <input type="text" name="address_zipcode" 
                        value="{{ $task->address->zipcode }}"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-[#283142]/50 focus:border-[#283142]">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Stad</label>
                    <input type="text" name="address_city" 
                        value="{{ $task->address->city }}"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-[#283142]/50 focus:border-[#283142]">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <a href="{{ route('schedule.index') }}" 
                   class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition">
                   Annuleren
                </a>
                <button type="submit" 
                        class="px-5 py-2 bg-[#283142] text-white rounded-md hover:bg-[#B51D2D] transition">
                    Opslaan
                </button>
            </div>
        </form>
    </div>
</x-layouts.dashboard>
