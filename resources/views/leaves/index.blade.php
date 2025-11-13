<x-layouts.dashboard>
<section class="max-w-6xl mx-auto mt-10">
    <h2 class="text-2xl font-semibold text-gray-800 mb-6">
        @if($user->role === 'admin')
            Alle verlofaanvragen
        @else
            Mijn verlofaanvragen
        @endif
    </h2>

    @if($user->role !== 'admin')
<div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
    <strong>Dringend verlof?</strong> Neem altijd telefonisch contact op met je leidinggevende.
</div>
@endif
    <!-- ✅ Succesbericht -->
    @if(session('success'))
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            {{ session('success') }}
        </div>
    @endif

    <!-- Nieuwe aanvraagknop voor normale user -->
    @if($user->role !== 'admin')
        <div class="mt-6 text-right">
            <a href="{{ route('leaves.create') }}" 
               class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
                + Nieuwe Verlofaanvraag
            </a>
        </div>
    @endif

    <!-- ✅ Geen aanvragen -->
    @if($requests->isEmpty())
        <div class="bg-gray-100 border text-gray-600 px-4 py-4 rounded text-center">
            Geen verlofaanvragen gevonden.
        </div>
    @else
        <div class="overflow-x-auto bg-white shadow-md rounded-lg">
            <table class="min-w-full border-collapse">
                <thead class="bg-gray-100 text-gray-700">
                    <tr>
                        <th class="text-left px-4 py-3 border-b">Teamlid</th>
                        <th class="text-left px-4 py-3 border-b">Verloftype</th>
                        <th class="text-left px-4 py-3 border-b">Startdatum</th>
                        <th class="text-left px-4 py-3 border-b">Einddatum</th>
                        <th class="text-left px-4 py-3 border-b">Status</th>
                        @if($user->role === 'admin')
                            <th class="text-left px-4 py-3 border-b">Team</th>
                        @endif
                        <th class="text-left px-4 py-3 border-b">Acties</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($requests as $req)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 border-b">{{ $req->member_name }}</td>
                            <td class="px-4 py-3 border-b">{{ $req->leaveType->name ?? '-' }}</td>
                            <td class="px-4 py-3 border-b">{{ \Carbon\Carbon::parse($req->start_date)->format('d-m-Y') }}</td>
                            <td class="px-4 py-3 border-b">{{ \Carbon\Carbon::parse($req->end_date)->format('d-m-Y') }}</td>
                            <td class="px-4 py-3 border-b">
                                @php
                                    $statusColors = [
                                        'pending'  => 'bg-yellow-100 text-yellow-800',
                                        'approved' => 'bg-green-100 text-green-800',
                                        'rejected' => 'bg-red-100 text-red-800',
                                    ];
                                @endphp
                                <span class="px-3 py-1 text-sm font-medium rounded-full {{ $statusColors[$req->status] ?? 'bg-gray-200 text-gray-700' }}">
                                    {{ $req->status_label }}
                                </span>
                            </td>

                            @if($user->role === 'admin')
                                <td class="px-4 py-3 border-b">{{ $req->team->name ?? 'Onbekend team' }}</td>
                            @endif

                            <td class="px-4 py-3 border-b">
                                <div class="flex flex-wrap gap-2">
                                    @if($user->role === 'admin')
                                        <!-- ✅ Admin goedkeuren / afwijzen -->
                                        @if($req->status === 'pending')
                                            <form action="{{ route('leaves.status', $req->id) }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="status" value="approved">
                                                <button type="submit" class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700 text-sm">
                                                    Goedkeuren
                                                </button>
                                            </form>

                                            <form action="{{ route('leaves.status', $req->id) }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="status" value="rejected">
                                                <button type="submit" class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 text-sm">
                                                    Afwijzen
                                                </button>
                                            </form>
                                        @endif
                                    @endif

                                    <!-- Bewerken -->
                                    @if($req->status === 'pending' || $user->role === 'admin')
                                        <a href="{{ route('leaves.edit', $req->id) }}" 
                                           class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 text-sm">
                                            Bewerken
                                        </a>
                                    @endif

                                    <!-- Verwijderen -->
                                    @if($req->status === 'pending' || $user->role === 'admin')
                                        <form action="{{ route('leaves.destroy', $req->id) }}" method="POST" 
                                              onsubmit="return confirm('Weet je zeker dat je deze aanvraag wilt verwijderen?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="bg-gray-500 text-white px-3 py-1 rounded hover:bg-gray-600 text-sm">
                                                Verwijderen
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
</x-layouts.dashboard>
