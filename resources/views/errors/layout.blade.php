<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
    <title>{{ $code }} – MáToCenu.cz</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @if (is_file(public_path('build/manifest.json')) || is_file(public_path('hot')))
        @vite(['resources/css/app.css'])
    @endif
</head>
<body class="paper grid min-h-screen place-items-center px-4">
    <main class="max-w-md text-center">
        <a href="/" aria-label="Domů"><x-logo-mark :size="64" class="mx-auto" /></a>
        <p class="mt-4 font-display text-6xl font-extrabold text-forest dark:text-primary">{{ $code }}</p>
        <h1 class="mt-2 font-display text-2xl font-bold">{{ $title }}</h1>
        <p class="mt-2 text-muted">{{ $text }}</p>
        <a href="/" class="btn-forest mt-6">Zpět na úvod</a>
    </main>
</body>
</html>
