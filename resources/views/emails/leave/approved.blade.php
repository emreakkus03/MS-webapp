<!DOCTYPE html>
<html lang="nl">
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color: #333;">

    <h2 style="color: #28a745;">✅ Verlof Goedgekeurd</h2>

    <p>Beste,</p>
    <p>De volgende verlofaanvraag is <strong>goedgekeurd</strong> door de administratie.</p>

    <div style="background-color: #e8f5e9; padding: 15px; border-radius: 8px; border-left: 5px solid #28a745; margin: 20px 0;">
        <ul style="list-style: none; padding: 0;">
            <li><strong>👤 Naam:</strong> {{ $leaveRequest->member_name }}</li>
            <li><strong>📂 Type:</strong> {{ $leaveRequest->leaveType->name ?? 'Onbekend' }}</li>
            <li><strong>📅 Startdatum:</strong> {{ \Carbon\Carbon::parse($leaveRequest->start_date)->format('d-m-Y') }}</li>
            <li><strong>📅 Einddatum:</strong> {{ \Carbon\Carbon::parse($leaveRequest->end_date)->format('d-m-Y') }}</li>
        </ul>
    </div>

    <p>Gelieve dit te verwerken in de personeelsplanning.</p>

    <div style="margin-top: 25px;">
        <p><strong>Met vriendelijke groeten<br>IT Support Team (Emre Akkus)</strong></p>
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