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
                    <select 
                        name="name" 
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">-- Selecteer je ploeg --</option>
                        @foreach ($teams as $team)
                            <option value="{{ $team->name }}" {{ old('name') == $team->name ? 'selected' : '' }}>
                                {{ $team->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- 🔹 Wachtwoordveld met toggle -->
                <div class="relative">
                    <label class="block mb-2 text-sm font-medium text-gray-700">Wachtwoord:</label>
                    <div class="relative">
                        <input 
                            type="password" 
                            name="password" 
                            id="password"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 pr-10"
                        >

                        <!-- 👁️ Toggle button -->
                        <button 
                            type="button" 
                            id="togglePassword"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-blue-600 focus:outline-none"
                            tabindex="-1"
                        >
                            <!-- Dicht oog -->
                            <svg id="eyeClosed" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M13.875 18.825A10.05 10.05 0 0112 19c-5.523 0-10-4.477-10-10 0-.653.063-1.29.183-1.905M6.343 6.343A9.956 9.956 0 0112 5c5.523 0 10 4.477 10 10 0 .797-.083 1.575-.242 2.328M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <line x1="3" y1="3" x2="21" y2="21" stroke="currentColor" stroke-width="2" />
                            </svg>

                            <!-- Open oog (verstopt eerst) -->
                            <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <button 
                    type="submit"
                    class="w-full bg-[#283142] text-white py-2 rounded hover:bg-[#B51D2D] transition"
                >
                    Inloggen
                </button>
            </form>

            @if ($errors->any())
                <div class="mt-4 text-red-600 text-center">
                    {{ $errors->first() }}
                </div>
            @endif
        </div>
    </div>

    <!-- 👇 Script voor wachtwoord toggle -->
    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const passwordInput = document.querySelector('#password');
        const eyeOpen = document.querySelector('#eyeOpen');
        const eyeClosed = document.querySelector('#eyeClosed');

        togglePassword.addEventListener('click', function () {
            const isHidden = passwordInput.type === 'password';
            passwordInput.type = isHidden ? 'text' : 'password';
            eyeOpen.classList.toggle('hidden', !isHidden);
            eyeClosed.classList.toggle('hidden', isHidden);
        });
    </script>
</x-layouts.app>
