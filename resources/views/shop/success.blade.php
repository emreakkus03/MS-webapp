<x-layouts.dashboard>
<div class="flex flex-col items-center justify-center min-h-[60vh] text-center px-4">
    
    {{-- SLIMME LOGICA: Waar moeten we naartoe terug? --}}
    @php
        // 1. Probeer de categorie te raden uit de bestelling zelf (beste optie)
        $redirectCategory = $order->materials->first()->category ?? null;

        // 2. Als dat niet lukt, pak de sessie
        if (!$redirectCategory) {
            $redirectCategory = session('last_shop_category', 'fluvius');
        }

        // Zorg dat het altijd kleine letters zijn
        $redirectCategory = strtolower($redirectCategory);
    @endphp

    <div class="mb-6 bg-green-100 rounded-full p-4 animate-bounce">
        <svg class="w-16 h-16 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
        </svg>
    </div>

    <h1 class="text-3xl font-bold mb-2 text-gray-800">Bedankt!</h1>
    <h2 class="text-xl text-gray-600 mb-8">Je bestelling is succesvol geplaatst.</h2>

    <div class="bg-white border border-gray-200 p-6 rounded-lg shadow-sm mb-8 max-w-sm w-full">
        <div class="flex justify-between border-b pb-2 mb-2">
            <span class="text-gray-500">Bestelnummer:</span>
            <span class="font-bold text-gray-900">#{{ $order->id }}</span>
        </div>
        <div class="flex justify-between border-b pb-2 mb-2">
            <span class="text-gray-500">Afhaaldatum:</span>
            <span class="font-bold text-gray-900">{{ $order->pickup_date->format('d-m-Y') }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-gray-500">Voertuig:</span>
            <span class="font-bold text-gray-900 uppercase">{{ $order->license_plate }}</span>
        </div>
    </div>

    <div class="relative w-20 h-20 mb-6 mx-auto"> 
        <svg class="w-full h-full transform -rotate-90">
             <circle cx="40" cy="40" r="36" stroke="#e5e7eb" stroke-width="6" fill="none" />
             <circle id="progress-ring" cx="40" cy="40" r="36" 
                     stroke="#16a34a" stroke-width="6" fill="none" 
                     stroke-dasharray="226" stroke-dashoffset="0"
                     style="transition: stroke-dashoffset 1s linear;" />
        </svg>
        <div class="absolute inset-0 flex items-center justify-center z-10">
            <span id="countdown" class="text-2xl font-bold text-gray-700 leading-none pt-1">5</span>
        </div>
    </div>

    <p class="text-sm text-gray-500 mb-6">Je wordt automatisch teruggestuurd naar de {{ ucfirst($redirectCategory) }} shop...</p>

    {{-- KNOP MET JUISTE LINK --}}
    <a href="{{ route('shop.index', $redirectCategory) }}" class="bg-[#283142] text-white px-6 py-2 rounded hover:bg-[#284142] transition">
        Nu terug naar de shop
    </a>
</div>

<script>
    let timeLeft = 5; 
    const totalTime = 5;
    
    const circle = document.getElementById('progress-ring');
    const radius = circle.r.baseVal.value;
    const circumference = radius * 2 * Math.PI;
    const countdownEl = document.getElementById('countdown');

    function setProgress(percent) {
        const offset = circumference - (percent / 100) * circumference;
        circle.style.strokeDashoffset = offset;
    }

    const interval = setInterval(() => {
        timeLeft--;
        countdownEl.innerText = timeLeft;
        
        const percentage = (timeLeft / totalTime) * 100;
        setProgress(percentage);
        
        if (timeLeft <= 0) {
            clearInterval(interval);
            // JAVASCRIPT REDIRECT MET JUISTE CATEGORIE
            window.location.href = "{{ route('shop.index', $redirectCategory) }}";
        }
    }, 1000);
</script>
</x-layouts.dashboard>