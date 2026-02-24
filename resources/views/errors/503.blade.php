<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Even geduld...</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #283142;
            color: white;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 20px;
        }
        .container {
            max-width: 400px;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 24px;
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        p {
            font-size: 16px;
            color: #b0bec5;
            line-height: 1.6;
            margin-bottom: 8px;
        }
        .timer {
            margin-top: 24px;
            padding: 12px 24px;
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            display: inline-block;
        }
        .timer span {
            font-size: 20px;
            font-weight: 600;
            color: #4CAF50;
        }
        .subtitle {
            font-size: 13px;
            color: #78909c;
            margin-top: 16px;
        }

        /* Auto-refresh: elke 15 seconden checken of app weer online is */
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔧</div>
        <h1>Even geduld a.u.b.</h1>
        <p>We voeren een korte update uit om de app te verbeteren.</p>
        <p>Dit duurt maximaal <strong>1 uur</strong>.</p>

        

        <p class="subtitle">
            De pagina herlaadt automatisch wanneer de update klaar is.
        </p>
    </div>

    <script>
        // Countdown timer (visueel, niet blokkerend)
        let seconds = 6000; // 5 minuten
        const el = document.getElementById('countdown');
        setInterval(() => {
            if (seconds > 0) seconds--;
            const m = Math.floor(seconds / 60);
            const s = seconds % 60;
            el.textContent = `${m}:${s.toString().padStart(2, '0')}`;
        }, 1000);

        // Auto-refresh: check elke 15 seconden of de app weer online is
        setInterval(async () => {
            try {
                const res = await fetch('/', { cache: 'no-store', redirect: 'follow' });
                // Als we GEEN 503 meer krijgen, is maintenance voorbij
                if (res.status !== 503) {
                    window.location.reload();
                }
            } catch (e) {
                // Netwerk fout, probeer opnieuw
            }
        }, 15000);
    </script>
</body>
</html>