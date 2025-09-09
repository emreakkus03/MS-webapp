<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Laravel site' }}</title>
</head>
<body>
    <header>
        <div class="brand">MS INFRA BV</div>
    </header>
    <main>
    {{ $slot }}
    </main>
    <footer>
        &copy; {{ date('Y') }} - MS-Infra
    </footer>
</body>
</html>