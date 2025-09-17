<aside class="h-screen md:w-64 bg-white border-r flex flex-col justify-between">

<div x-data="{ open: false }" class="flex h-screen bg-gray-100">

    <!-- Hamburger / Close knop voor mobiel -->
    <button @click="open = !open"
            class="p-4 md:hidden fixed z-50">
        <!-- Hamburger icon -->
        <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mt-4" fill="none"
             viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 6h16M4 12h16M4 18h16"/>
        </svg>

       
    </button>

    <!-- Sidebar / Aside -->
<aside :class="open ? 'translate-x-0' : '-translate-x-full'"
       class="fixed inset-y-0 left-0 w-64 bg-white border-r transform md:translate-x-0 transition-transform duration-300 ease-in-out z-40 flex flex-col justify-between">

    <div>
        <!-- Header -->
        <header class="p-4 ml-4 flex items-center gap-4 border-b relative">
            <img class="w-12 h-12 object-contain"
                 src="{{ asset('images/logo/msinfra_logo.png') }}" alt="MS Infra Logo">
            <div class="text-gray-700">
                <p class="font-semibold">{{ auth()->user()->name }}</p>
                <p class="text-sm text-gray-500">Welkom terug</p>
            </div>

            <!-- Close icon voor mobiel -->
            <button @click="open = false" class="absolute top-4 right-4 md:hidden">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-500 mt-4" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </header>

        <!-- Navigation -->
        <nav class="mt-6 ml-4">
            <ul class="space-y-1">
                <li>
    <a href="{{ Auth::user()->role === 'admin' ? route('dashboard.admin') : route('dashboard.user') }}"
       class="flex items-center gap-3 px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-md transition">
        <img src="{{ asset('images/icon/home.svg') }}" alt="Logo"
             class="w-7 h-7 text-gray-500">
        Dashboard
    </a>
</li>
                <li>
                    <a href="/schedule"
                       class="flex items-center gap-3 px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-md transition">
                        <img src="{{ asset('images/icon/schedule.svg') }}" alt="Logo"
                             class="w-7 h-7 text-gray-500">
                        Planning
                    </a>
                </li>
                @if(auth()->user()->role === 'admin')
                    <li>
                        <a href="/teams"
                           class="flex items-center gap-3 px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-md transition">
                            <img src="{{ asset('images/icon/team.svg') }}" alt="Logo"
                                 class="w-7 h-7 text-gray-500">
                            Ploegen
                        </a>
                    </li>
                @endif
                @if(auth()->user()->role === 'admin')
                    <li>
                        <a href="/tasks"
                           class="flex items-center gap-3 px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-md transition">
                            <img src="{{ asset('images/icon/team.svg') }}" alt="Logo"
                                 class="w-7 h-7 text-gray-500">
                            Takenbeheer
                        </a>
                    </li>
                @endif
            </ul>
        </nav>
    </div>

    <!-- Logout -->
    <div class="p-4 border-t">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    class="flex items-center gap-2 text-[#B51D2D] hover:text-red-700 transition w-full ml-4">
                <img src="{{ asset('images/icon/logout.svg') }}" alt="Logo"
                     class="w-7 h-7 text-gray-500">
                Uitloggen
            </button>
        </form>
    </div>
</aside>


    
</div>




</aside>
<script src="//unpkg.com/alpinejs" defer></script>