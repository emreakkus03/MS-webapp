<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    <title>{{ $title ?? 'MS-Webapp' }}</title>
    <link rel="stylesheet" href="{{ asset('css/reset.css') }}">

    @if(auth()->check())
        <!-- Globale Laravel helper voor JS -->
        <script>
            window.Laravel = {
                userId: {{ auth()->id() }},
                userRole: "{{ auth()->user()->role }}"
            };
        </script>
        <script>
            function changeFavicon(src) {
                let link = document.querySelector("link[rel~='icon']");
                if (!link) {
                    link = document.createElement("link");
                    link.rel = "icon";
                    document.head.appendChild(link);
                }
                link.href = src;
            }
        </script>
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    <header>
        <div class="brand text-[2rem] font-bold bg-gray-100">
            <div class="md:pl-20 xl:pl-40 md:pt-8 pb-4 pt-4 flex justify-center gap-1 md:justify-start">
                <span class="text-[#283142]">MS INFRA</span>
                <span class="text-[#B51D2D]">BV</span>
            </div>
        </div>
    </header>

    <main>
        {{ $slot }}
    </main>

    <footer class="w-full fixed bottom-0 left-0 flex justify-center items-center bg-gray-100 py-4">
        <strong>
            <span class="text-gray-500 text-sm">
                &copy; {{ date('Y') }} - MS-Infra -
                <a href="https://www.linkedin.com/in/emre-akkus-118363251/" target="_blank">Emre Akkus</a>
            </span>
        </strong>
    </footer>

    @if(auth()->check() && auth()->user()->role === 'admin')
        <script>
            window.Echo.channel('admin-tasks')
                .listen('.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated', (e) => {
                    const data = e.notification;

                    // Favicon en titel rood
                    changeFavicon("/images/favicon-red.png");
                    document.title = "(1) Nieuwe melding - MS Infra";

                    // Browser push
                    if (Notification.permission === "granted") {
                        new Notification("Nieuwe taakmelding", { body: data.message });
                    }

                    // Eventueel UI updaten
                    console.log("Nieuwe notificatie:", data.message);
                });

            if (Notification.permission !== "granted" && Notification.permission !== "denied") {
                Notification.requestPermission();
            }
        </script>
    @endif
</body>
</html>
