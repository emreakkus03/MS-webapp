<x-layouts.app>
    @php
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: 0");
@endphp
    <div class="flex items-center justify-center min-h-[86vh] bg-gray-100">
        <div class="md:w-full max-w-md bg-white p-8 rounded shadow box-border">
            <h1 class="text-2xl font-bold mb-6 text-center">Inloggen</h1>
            <form method="POST" action="{{ route('login') }}" class="space-y-6">
                @csrf

                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-700">Ploeg:</label>
                    <select name="name" required
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Selecteer je ploeg --</option>
                        @foreach ($teams as $team)
                            <option value="{{ $team->name }}" {{ old('name') == $team->name ? 'selected' : '' }}>
                                {{ $team->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-700">Wachtwoord:</label>
                    <input type="password" name="password" required
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <button type="submit"
                    class="w-full bg-[#283142] text-white py-2 rounded hover:bg-[#B51D2D] transition">Inloggen</button>
            </form>

           @if ($errors->any())
    <div class="mt-4 text-red-600 text-center">
        {{ $errors->first() }}
    </div>
@endif

        </div>
    </div>
</x-layouts.app>
