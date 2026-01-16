<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Orderbon #{{ $order->id }}</title>
    <style>
        body { font-family: sans-serif; font-size: 14px; }
        .header { margin-bottom: 20px; border-bottom: 2px solid black; padding-bottom: 10px; }
        .details { display: flex; justify-content: space-between; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; }
        
        /* Zorg dat de checkbox kolom smal is */
        .check-col { width: 50px; text-align: center; }

        /* Print specifieke regels */
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 10px; }
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 20px; padding: 10px; background: #eee;">
        <button onclick="window.print()" style="font-size: 20px; padding: 10px 20px; cursor: pointer;">🖨️ Nu Printen</button>
        <a href="{{ route('warehouse.index') }}" style="margin-left: 20px;">Terug naar Dashboard</a>
    </div>

    <div class="header">
        <img src="{{ asset('images/logo/ms-infra.png') }}" style="height: 60px; width: auto; display: inline-block;" alt="Logo">
        <h1>Bestelbon #{{ $order->id }}</h1>
    </div>

    <div class="details">
        <div>
            <strong>Ploeg:</strong><br>
            {{ $order->team->name ?? 'Ploeg ' . $order->team_id }}<br>
        </div>
        <div>
            <strong>Afhaalgegevens:</strong><br>
            Datum: {{ $order->pickup_date->format('d-m-Y') }}<br>
            Voertuig: <strong>{{ $order->license_plate }}</strong>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>SAP Nr</th>
                <th>Omschrijving</th>
                <th>Aantal</th>
                <th>Verpakking</th>
                <th class="check-col">✅</th> </tr>
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