<!DOCTYPE html>
<html lang="nl">
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color: #333;">

    <h2 style="color: #283142;">📦 Nieuwe Bestelling Binnen</h2>

    <p>Beste Magazijnier,</p>
    <p>Er is een nieuwe materiaalbestelling geplaatst door <strong>{{ $order->team->name ?? 'Onbekend' }}</strong>.</p>

    {{-- Details Blok --}}
    <div style="background-color: #f3f4f6; padding: 15px; border-radius: 8px; margin: 20px 0;">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding-bottom: 5px;"><strong>Bestelnummer:</strong></td>
                <td style="padding-bottom: 5px;">#{{ $order->id }}</td>
            </tr>
            <tr>
                <td style="padding-bottom: 5px;"><strong>📅 Afhaaldatum:</strong></td>
                <td style="padding-bottom: 5px;">{{ \Carbon\Carbon::parse($order->pickup_date)->format('d-m-Y') }}</td>
            </tr>
            <tr>
                <td style="padding-bottom: 5px;"><strong>🚛 Nummerplaat:</strong></td>
                <td style="padding-bottom: 5px;">{{ $order->license_plate }}</td>
            </tr>
        </table>
    </div>

    {{-- Tabel met producten --}}
    <h3>Bestelde Materialen:</h3>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 14px;">
        <thead>
            <tr style="background-color: #283142; color: white;">
                <th style="padding: 10px; text-align: left;">SAP</th>
                <th style="padding: 10px; text-align: left;">Omschrijving</th>
                <th style="padding: 10px; text-align: center;">Aantal</th>
                <th style="padding: 10px; text-align: left;">Verpakking</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->materials as $material)
                <tr style="border-bottom: 1px solid #ddd;">
                    {{-- SAP Nummer --}}
                    <td style="padding: 10px; font-weight: bold;">{{ $material->sap_number }}</td>

                    {{-- Omschrijving --}}
                    <td style="padding: 10px;">{{ $material->description }}</td>
                    
                    {{-- Aantal (uit pivot) --}}
                    <td style="padding: 10px; text-align: center; font-weight: bold; font-size: 16px;">
                        {{ $material->pivot->quantity }} {{ $material->unit }}
                    </td>

                    {{-- Verpakking & Eenheid --}}
                    <td style="padding: 10px;">
                            {{ $material->packaging }} 
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p>Gelieve deze bestelling klaar te zetten.</p>

     <div style="margin-top: 25px;">
        <p><strong>Met vriendelijke groeten<br> IT Support Team (Emre Akkus)</strong></p>
        <table role="presentation" style="border-collapse: collapse; border: none; margin-top: 8px;">
            <tr>
                <td style="padding-right: 10px;">
                    <img src="{{ asset('images/logo/ms-infra.png') }}"
                         alt="MS Infra logo mail"
                         style="height: 60px; width: auto; display: inline-block;">
                </td>
                <td>
                    <img src="{{ asset('images/logo/trends_en_gazellen.jpg') }}"
                         alt="Trends en Gazellen logo mail"
                         style="height: 100px; width: auto; display: inline-block;">
                </td>
            </tr>
        </table>
    </div>
</body>
</html>