<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; color: #333;">

    <h2 style="margin-bottom: 10px;">🚧 Dagelijks hersteloverzicht</h2>

    <p>
        Taken met herstelnotities tussen
        <strong>{{ $periodStart }}</strong> en <strong>{{ $periodEnd }}</strong>.
    </p>

    <p>Er zijn <strong>{{ $tasks->count() }}</strong> adressen gevonden met een notitie voor herstel.</p>

    <ul style="margin: 0 0 20px 20px; padding: 0;">
        @foreach ($tasks as $task)
            <li style="margin-bottom: 10px;">
                <strong>
                    {{ optional($task->address)->street ?? 'Onbekend adres' }}
                    {{ optional($task->address)->number ?? 'Onbekend nummer' }},
                    {{ optional($task->address)->zipcode ?? 'Onbekend postcode' }}
                    {{ optional($task->address)->city ?? 'Onbekend stad' }}
                </strong><br>
                📝 <em>{{ $task->note ?? 'Geen notitie' }}</em>
            </li>
        @endforeach
    </ul>

    <p>📨 Verzonden om {{ $sendTime }}</p>

    <div style="margin-top: 25px;">
        <p style="margin: 0 0 10px 0;">
            <strong>Met vriendelijke groet,<br>
            IT Support Team (Emre)</strong>
        </p>

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
