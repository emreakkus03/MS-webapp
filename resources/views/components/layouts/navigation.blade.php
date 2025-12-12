<aside class="h-screen md:w-64 bg-white border-r flex flex-col justify-between">

    <div x-data="{ open: false }" class="flex h-screen bg-gray-100">

        <!-- Hamburger / Close knop voor mobiel -->
        <button @click="open = !open" class="p-4 md:hidden fixed z-50">
            <!-- Hamburger icon -->
            <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mt-4" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>


        </button>

        <!-- Sidebar / Aside -->
        <aside :class="open ? 'translate-x-0' : '-translate-x-full'"
            class="fixed inset-y-0 left-0 w-64 bg-white border-r transform md:translate-x-0 transition-transform duration-300 ease-in-out z-40 flex flex-col justify-between">

            <div>
                <!-- Header -->
                <header class="p-4 ml-4 flex items-center gap-4 border-b relative">
                    <!-- Logo -->
                    <img class="w-12 h-12 object-contain" src="{{ asset('images/logo/msinfra_logo.png') }}"
                        alt="MS Infra Logo">

                    <!-- Gebruikersinfo -->
                    <div class="text-gray-700">
                        <p class="font-semibold">{{ auth()->user()->name }}</p>
                        <p class="text-sm text-gray-500">Welkom terug</p>
                    </div>

                    <!-- Flex-grow duwt bel helemaal rechts -->
                    <div class="flex-grow"></div>

                    <!-- Notificatie Bell -->
                  
                        <div x-data="{ openNotif: false }" class="relative right-8 md:right-0">
                            <!-- 🔹 extra margin rechts -->
                            <button @click="openNotif = !openNotif" class="relative ">
                                <img src="{{ asset('images/icon/notification.svg') }}" alt="Notificaties"
                                    class="w-6 h-6 text-gray-500"> <!-- 🔹 iets groter nu -->
                                <!-- Badge -->
                                @if (auth()->user()->unreadNotifications->count() > 0)
                                    <span
                                        class="absolute -top-1 -right-1 bg-red-600 text-white text-xs rounded-full px-1.5 py-0.5">
                                        {{ auth()->user()->unreadNotifications->count() }}
                                    </span>
                                @endif
                            </button>

                            <!-- Dropdown met notificaties -->
                            <div x-show="openNotif" @click.away="openNotif = false"
                                class="absolute -left-40 md:left-0 mt-2 w-72 bg-white border rounded-lg shadow-lg z-50">
                                <ul id="notifications" class="max-h-60 overflow-y-auto divide-y divide-gray-200">
                                    @forelse(auth()->user()->notifications as $notification)
                                        <li
                                            class="flex justify-between items-start gap-2 p-3 text-sm 
            {{ is_null($notification->read_at) ? 'font-bold text-gray-900 bg-gray-50' : 'text-gray-700' }}">

                                            <div class="flex-1">
                                                <div>{{ $notification->data['message'] }}</div>
                                                <div class="text-xs text-gray-500 mt-1">
                                                    {{ $notification->created_at->locale('nl')->diffForHumans() }}
                                                    ({{ $notification->created_at->format('d-m-Y H:i') }})
                                                </div>
                                            </div>

                                            @if (Auth::user()->role === 'admin')
                                                <form action="{{ route('notifications.destroy', $notification->id) }}"
                                                    method="POST"
                                                    onsubmit="return confirm('Weet je zeker dat je deze notificatie wilt verwijderen?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="text-gray-400 hover:text-red-600"
                                                        title="Verwijderen">
                                                        <!-- 🗑️ Trash icoon -->
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4"
                                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4
                                  a1 1 0 00-1-1h-4a1 1 0 00-1 1v3m-4 0h14" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            @endif
                                        </li>
                                    @empty
                                        <li class="p-3 text-sm text-gray-400">Geen meldingen</li>
                                    @endforelse
                                </ul>

                                <div class="p-2 text-center space-y-2">
                                    <form method="POST" action="{{ route('notifications.clear') }}">
                                        @csrf
                                        <button class="text-blue-600 text-sm hover:underline">
                                            Markeer alles als gelezen
                                        </button>
                                    </form>

                                    @if (Auth::user()->role === 'admin')
                                        <form method="POST" action="{{ route('notifications.delete') }}">
                                            @csrf
                                            <button class="text-red-600 text-sm hover:underline"
                                                onclick="return confirm('Weet je zeker dat je ALLE notificaties wilt verwijderen? Dit kan niet ongedaan worden gemaakt.')">
                                                Verwijder alle notificaties
                                            </button>
                                        </form>
                                    @endif
                                </div>

                            </div>

                        </div>
                   

                    <!-- Close icon voor mobiel -->
                    <button @click="open = false" class="absolute top-8 right-4 md:hidden">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-600" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </header>


                <!-- Navigation -->
                <nav class="mt-6 ml-4">
                    <ul class="space-y-1">
                        <li>
                            <a href="{{ Auth::user()->role === 'admin' ? route('dashboard.admin') : route('dashboard.user') }}"
                                class="flex items-center gap-3 px-4 py-2 rounded-md transition
                      {{ request()->routeIs('dashboard.*') ? 'bg-gray-100 text-[#B51D2D] font-bold' : 'text-gray-700 hover:bg-gray-100' }}">
                                <img src="{{ asset('images/icon/home.svg') }}" alt="Logo"
                                    class="w-7 h-7 text-gray-500">
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('schedule.index') }}"
                                class="flex items-center gap-3 px-4 py-2 rounded-md transition
                      {{ request()->is('schedule*') ? 'bg-gray-100 text-[#B51D2D] font-bold' : 'text-gray-700 hover:bg-gray-100' }}">
                                <img src="{{ asset('images/icon/schedule.svg') }}" alt="Logo"
                                    class="w-7 h-7 text-gray-500">
                                Planning
                            </a>
                        </li>
                        @if (auth()->user()->role === 'admin')
                            <li>
                                <a href="{{ route('teams.index') }}"
                                    class="flex items-center gap-3 px-4 py-2 rounded-md transition
                          {{ request()->is('teams*') ? 'bg-gray-100 text-[#B51D2D] font-bold' : 'text-gray-700 hover:bg-gray-100' }}">
                                    <img src="{{ asset('images/icon/team.svg') }}" alt="Logo"
                                        class="w-7 h-7 text-gray-500">
                                    Ploegen
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('tasks.index') }}"
                                    class="flex items-center gap-3 px-4 py-2 rounded-md transition
                          {{ request()->is('tasks*') ? 'bg-gray-100 text-[#B51D2D] font-bold' : 'text-gray-700 hover:bg-gray-100' }}">
                                    <img src="{{ asset('images/icon/clipboard.svg') }}" alt="Logo"
                                        class="w-7 h-7 text-gray-500">
                                    Takenbeheer
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('admin.dropbox.index') }}"
                                    class="flex items-center gap-3 px-4 py-2 rounded-md transition
                          {{ request()->is('admin*') ? 'bg-gray-100 text-[#B51D2D] font-bold' : 'text-gray-700 hover:bg-gray-100' }}">
                                    <img src="{{ asset('images/icon/file-settings.svg') }}" alt="Logo"
                                        class="w-7 h-7 text-gray-500">
                                    Vergunningbeheer
                                </a>
                            </li>
                        @endif
                        <li>
                            <a href="{{ route('dossiers.index') }}"
                                class="flex items-center gap-3 px-4 py-2 rounded-md transition
                          {{ request()->is('files*') ? 'bg-gray-100 text-[#B51D2D] font-bold' : 'text-gray-700 hover:bg-gray-100' }}">
                                <img src="{{ asset('images/icon/license.svg') }}" alt="Logo"
                                    class="w-7 h-7 text-gray-500">
                                Vergunningen
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('leaves.index') }}"
                                class="flex items-center gap-3 px-4 py-2 rounded-md transition
                          {{ request()->is('leaves*') ? 'bg-gray-100 text-[#B51D2D] font-bold' : 'text-gray-700 hover:bg-gray-100' }}">
                                <img src="{{ asset('images/icon/briefcase.svg') }}" alt="Logo"
                                    class="w-7 h-7 text-gray-500">
                                Verlofbeheer
                            </a>
                        </li>
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
@if (auth()->check() && auth()->user()->role === 'admin')
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            if (window.Echo) {
                console.log("Echo is ready, subscribing to admin channels...");

                // 🔹 Notities bij taken
                window.Echo.private('admin-tasks')
                    .notification((notification) => {
                        console.log("Realtime admin-task binnen:", notification);
                        updateNotifications(notification.message);
                    });

                // 🔹 Afgeronde taken
                window.Echo.private('App.Models.Team.admins')
                    .notification((notification) => {
                        console.log("Realtime taak voltooid:", notification);
                        updateNotifications(notification.message);
                    });
            } else {
                console.error("Echo is niet beschikbaar!");
            }

            function updateNotifications(message) {
                const now = new Date();
                const timestamp = now.toLocaleDateString('nl-BE') + " " + now.toLocaleTimeString('nl-BE', {
                    hour: '2-digit',
                    minute: '2-digit'
                });

                // Update badge
                let badge = document.querySelector(".absolute.-top-1.-right-1");
                if (!badge) {
                    badge = document.createElement("span");
                    badge.className =
                        "absolute -top-1 -right-1 bg-red-600 text-white text-xs rounded-full px-1.5 py-0.5";
                    badge.innerText = "1";
                    const btn = document.querySelector("button.relative");
                    if (btn) btn.appendChild(badge);
                } else {
                    badge.innerText = parseInt(badge.innerText) + 1;
                }

                // Update lijst
                const notifList = document.getElementById("notifications");
                if (notifList) {
                    if (notifList.children.length === 1 && notifList.children[0].classList.contains(
                            "text-gray-400")) {
                        notifList.innerHTML = "";
                    }
                    const li = document.createElement("li");
                    li.className = "p-3 text-sm font-bold text-gray-900 bg-gray-50";
                    li.innerHTML = `<div>${message}</div><div class="text-xs text-gray-500">${timestamp}</div>`;
                    notifList.prepend(li);
                }

                // ✅ Favicon rood maken
                changeFavicon("/favicon-red.ico");

                // ✅ Tabblad markeren
                if (!document.title.startsWith("(1)")) {
                    document.title = "(1) " + document.title;
                }

                // ✅ Desktop notification
                if (Notification.permission === "granted") {
                    let notif = new Notification("MS Infra - Nieuwe melding", {
                        body: `${message}\n(${timestamp})`,
                        icon: "/favicon-red.ico"
                    });

                    // Klik → open Takenbeheer
                    notif.onclick = function() {
                        window.focus();
                        window.location.href = "/tasks";
                    };
                }
            }

            function changeFavicon(src) {
                let link = document.querySelector("link[rel~='icon']");
                if (!link) {
                    link = document.createElement("link");
                    link.rel = "icon";
                    document.head.appendChild(link);
                }
                link.href = src;
            }

            // Vraag toestemming voor desktop notificaties
            if (Notification.permission !== "granted" && Notification.permission !== "denied") {
                Notification.requestPermission();
            }
        });
    </script>
@endif
