<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Orderbon #{{ $order->id }}</title>
    <style>
        body { font-family: sans-serif; font-size: 14px; }
        .header { margin-bottom: 20px; border-bottom: 2px solid black; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .details { display: flex; justify-content: space-between; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; }
        
        /* Zorg dat de checkbox kolom smal is */
        .check-col { width: 50px; text-align: center; }

        /* Badge stijl voor het type */
        .type-badge {
            font-size: 18px;
            font-weight: bold;
            padding: 5px 15px;
            border-radius: 4px;
            color: white;
            text-transform: uppercase;
        }

        /* Print specifieke regels */
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 10px; }
            tr { page-break-inside: avoid; }
            /* Zorg dat achtergrondkleuren geprint worden */
            .type-badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    {{-- 1. LOGICA OM HET TYPE TE BEPALEN --}}
    @php
        // We kijken naar het eerste materiaal om de categorie te bepalen
        $firstItem = $order->materials->first();
        $category = $firstItem ? strtolower($firstItem->category) : 'onbekend';

        // Standaard waardes
        $typeLabel = 'ALGEMEEN';
        $typeColor = '#333'; // Zwart

        // Specifieke labels en kleuren
        if ($category == 'fluvius') {
            $typeLabel = 'FLUVIUS (Aansluit)';
            $typeColor = '#2563eb'; // Blauw
        } elseif ($category == 'handgereedschap') {
            $typeLabel = 'HANDGEREEDSCHAP (Aanleg)';
            $typeColor = '#B51D2D'; // Rood (Jouw huisstijl kleur)
        }
    @endphp

    <div class="no-print" style="margin-bottom: 20px; padding: 10px; background: #eee;">
        <button onclick="window.print()" style="font-size: 20px; padding: 10px 20px; cursor: pointer;">🖨️ Nu Printen</button>
        <a href="{{ route('warehouse.index') }}" style="margin-left: 20px;">Terug naar Dashboard</a>
    </div>

    {{-- 2. AANGEPASTE HEADER --}}
    <div class="header" style="border-bottom-color: {{ $typeColor }};">
        <div>
            <img src="{{ asset('images/logo/ms-infra.png') }}" style="height: 60px; width: auto; display: block; margin-bottom: 10px;" alt="Logo">
            <h1 style="margin: 0;">Bestelbon #{{ $order->id }}</h1>
        </div>
        
        {{-- Hier tonen we het grote label --}}
        <div class="type-badge" style="background-color: {{ $typeColor }};">
            {{ $typeLabel }}
        </div>
    </div>

    <div class="details">
        <div>
            <strong>Ploeg:</strong><br>
            {{-- Checken of team bestaat, anders fallback --}}
            {{ $order->team->name ?? 'Ploeg ' . $order->team_id }}<br>
            {{-- Optioneel: Naam van de aanvrager erbij als je User relatie hebt --}}
            {{-- <small>Aangevraagd door: {{ $order->team->name }}</small> --}}
        </div>
        <div style="text-align: right;">
            <strong>Afhaalgegevens:</strong><br>
            Datum: {{ $order->pickup_date->format('d-m-Y') }}<br>
            Voertuig: <strong style="font-size: 1.2em;">{{ $order->license_plate }}</strong>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>SAP Nr</th>
                <th>Omschrijving</th>
                <th>Aantal</th>
                <th>Verpakking</th>
                <th class="check-col">✅</th> 
            </tr>
        </thead>
        <tbody>
            @foreach($order->materials as $material)
            <tr>
                <td>{{ $material->sap_number }}</td>
                <td>{{ $material->description }}</td>
                <td style="font-size: 1.2em; font-weight: bold;">
                    {{ $material->pivot->quantity }} {{ $material->unit }}
                </td>
                <td>{{ $material->packaging }}</td>
                <td class="check-col">⬜</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top: 50px; border-top: 1px dashed black; padding-top: 10px;">
        <p>Handtekening voor ontvangst:</p>
        <br><br><br>
        _____________________________
    </div>

    <script>
        // Optioneel: Automatisch print dialoog openen bij laden
        // window.print();
    </script>
</body>
</html>