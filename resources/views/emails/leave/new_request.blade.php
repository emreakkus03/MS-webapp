<!DOCTYPE html>
<html lang="nl">
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color: #333;">

    <h2 style="color: #283142;">📅 Nieuwe Verlofaanvraag</h2>

    <p>Beste </p>
    <p>Er is een nieuwe verlofaanvraag ingediend door <strong>{{ $leaveRequest->member_name }}</strong>.</p>

    <div style="background-color: #f3f4f6; padding: 15px; border-radius: 8px; margin: 20px 0;">
        <ul style="list-style: none; padding: 0;">
            <li><strong>👤 Naam:</strong> {{ $leaveRequest->member_name }}</li>
            <li><strong>📂 Type:</strong> {{ $leaveRequest->leaveType->name ?? 'Onbekend' }}</li>
            <li><strong>📅 Periode:</strong> 
                {{ \Carbon\Carbon::parse($leaveRequest->start_date)->format('d-m-Y') }} 
                t/m 
                {{ \Carbon\Carbon::parse($leaveRequest->end_date)->format('d-m-Y') }}
            </li>
            @if($leaveRequest->note)
                <li style="margin-top: 10px;"><strong>📝 Notitie:</strong><br> {{ $leaveRequest->note }}</li>
            @endif
        </ul>
    </div>

    <p>Login op het <a href="https://ms-webapp-main-yfswth.laravel.cloud/">Platform</a> en open de pagina <strong>verlofbeheer</strong>.<br> Hier kan je de aanvraag goedkeuren of afwijzen.<br><p>Dit zijn de logingegevens:</p><ul><li>Gebruiker: <strong>Admin</strong></li><li>Wachtwoord: <strong>admin1</strong></li></ul></p>

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