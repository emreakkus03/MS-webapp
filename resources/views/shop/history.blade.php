<x-layouts.dashboard>
<div class="container mx-auto px-4 max-w-4xl">
    
    <div class="flex flex-col sm:flex-row justify-between items-center mb-8 gap-4">
        <h1 class="text-2xl font-bold text-gray-800">Mijn Bestelhistoriek 📋</h1>
        <a href="{{ route('shop.index') }}" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            ← Nieuwe Bestelling
        </a>
    </div>

    @if($orders->isEmpty())
        <div class="bg-white rounded-lg shadow p-10 text-center">
            <div class="text-5xl mb-4">📭</div>
            <h3 class="text-lg font-semibold text-gray-700">Nog geen bestellingen</h3>
            <p class="text-gray-500 mb-6">Je hebt nog niets besteld met dit account.</p>
            <a href="{{ route('shop.index') }}" class="text-[#2ea5d7] hover:underline">Ga naar de shop</a>
        </div>
    @else
        <div class="space-y-6">
            @foreach($orders as $order)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    
                    <div class="bg-gray-50 px-6 py-4 border-b flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div>
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Bestelling #{{ $order->id }}</span>
                            <div class="font-bold text-lg text-gray-800">
                                {{ $order->created_at->format('d-m-Y') }} <span class="text-sm font-normal text-gray-500">(om {{ $order->created_at->format('H:i') }})</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            @php
                                $statusColors = [
                                    'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                    'printed' => 'bg-blue-100 text-blue-800 border-blue-200',
                                    'ready'   => 'bg-green-100 text-green-800 border-green-200',
                                ];
                                $statusLabels = [
                                    'pending' => '⏳ In Wacht',
                                    'printed' => '📦 Wordt verwerkt',
                                    'ready'   => '✅ Klaar om af te halen',
                                ];
                                $status = $order->status;
                            @endphp

                            <span class="px-3 py-1 rounded-full text-xs font-bold border {{ $statusColors[$status] ?? 'bg-gray-100 text-gray-800' }}">
                                {{ $statusLabels[$status] ?? $status }}
                            </span>
                        </div>
                    </div>

                    <div class="px-6 py-4 grid grid-cols-1 sm:grid-cols-2 gap-4 bg-white">
                        <div>
                            <span class="block text-xs text-gray-500 uppercase">Gewenste Afhaaldatum</span>
                            <span class="font-medium">{{ $order->pickup_date->format('d-m-Y') }}</span>
                        </div>
                        <div>
                            <span class="block text-xs text-gray-500 uppercase">Voertuig (Nummerplaat)</span>
                            <span class="font-medium">{{ $order->license_plate }}</span>
                        </div>
                    </div>

                    <div class="px-6 pb-6">
                        <div class="bg-gray-50 rounded p-4">
                            <h4 class="text-sm font-bold text-gray-700 mb-3 border-b pb-2">Bestelde Materialen:</h4>
                            <ul class="space-y-2">
                                @foreach($order->materials as $material)
                                    <li class="flex justify-between text-sm">
                                        <span class="text-gray-600">
                                            {{ $material->description }} 
                                            <span class="text-xs text-gray-400">({{ $material->sap_number }})</span>
                                        </span>
                                        <span class="font-bold text-gray-800">
                                            {{ $material->pivot->quantity }} {{ $material->unit }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                </div>
            @endforeach
        </div>
    @endif
</div>
</x-layouts.dashboard>