<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#B51D2D">
    <link rel="apple-touch-icon" href="{{ asset('images/logo/msinfra_logo.png') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    <title>{{ $title ?? 'MS-Webapp' }}</title>
    <link rel="stylesheet" href="{{ asset('css/reset.css') }}">

    @if (auth()->check())
        <!-- Globale Laravel helper -->
        <script>
            window.Laravel = {
                userId: {{ auth()->id() }},
                userRole: "{{ auth()->user()->role }}"
            };
        </script>
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('scripts')
</head>

<body class="flex min-h-screen">
    <x-layouts.navigation></x-layouts.navigation>

    <main class="flex-1 min-h-screen p-6 overflow-y-auto bg-gray-50">
        {{ $slot }}
    </main>

   


    <script src="//unpkg.com/alpinejs" defer></script>
</body>

</html>
