<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    <title>{{ $title ?? 'MS-Webapp' }}</title>
    <link rel="stylesheet" href="{{ asset('css/reset.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header>
        <div class="brand text-[2rem] font-bold  bg-gray-100 ">
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
        <span class="text-gray-500 text-sm">&copy; {{ date('Y') }} - MS-Infra</span>
    </footer>
</body>
</html>