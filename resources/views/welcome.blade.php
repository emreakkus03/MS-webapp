<x-layouts.app>
    <h1>Welkom op de dashboard {{ ucfirst(\Carbon\Carbon::now()->locale('nl')->isoFormat('dd')) }}, {{ \Carbon\Carbon::now()->format('d.m.Y') }}</h1>
    <p>Hier zal de inhoud komen</p>
</x-layouts.app>