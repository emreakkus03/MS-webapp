<x-layouts.dashboard style="background-color: #f0f0f0;">
    <h1><strong>{{ ucfirst(\Carbon\Carbon::now()->locale('nl')->isoFormat('dd')) }},
        {{ \Carbon\Carbon::now()->format('d.m.Y') }}</strong></h1>
    <p>Hier zal de inhoud komen</p>
</x-layouts.dashboard>
