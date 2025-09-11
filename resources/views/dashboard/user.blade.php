<x-layouts.dashboard style="background-color: #f0f0f0;">
    <h1><strong>{{ ucfirst(\Carbon\Carbon::now()->locale('nl')->isoFormat('dd')) }},
        {{ \Carbon\Carbon::now()->format('d.m.Y') }}</strong></h1>
    <p>Hier zal de inhoud komen</p>

     <div class="md:p-6">
        <h1 class="text-2xl font-bold mb-4">Taken van vandaag</h1>

        @if($tasksToday->isEmpty())
            <p>Geen taken vandaag!</p>
        @else
            <table class="w-full border border-gray-300 rounded">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border px-4 py-2 text-left">Tijd</th>
                        <th class="border px-4 py-2 text-left">Adres</th>
                        <th class="border px-4 py-2 text-left">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tasksToday as $task)
                        <tr>
                            <td class="border px-4 py-2">
                                {{ \Carbon\Carbon::parse($task->time)->format('H:i') }}
                            </td>
                            <td class="border px-4 py-2">
                                {{ $task->address->street }} {{ $task->address->number ?? '' }}, {{ $task->address->zipcode ?? '' }} {{ $task->address->city ?? '' }}
                            </td>
                            <td class="border px-4 py-2 capitalize">
                                {{ $task->status }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-layouts.dashboard>
