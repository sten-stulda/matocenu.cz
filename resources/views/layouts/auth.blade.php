@props(['title' => null, 'subtitle' => null])
<!DOCTYPE html>
<html lang="cs">
<head>
    @include('partials.head', ['title' => $title, 'noindex' => true])
</head>
<body class="paper min-h-screen">
<div class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-4 py-10">
    <a href="{{ route('home') }}" class="mb-6 self-center" aria-label="MáToCenu.cz – domů"><x-logo size="md" /></a>
    <x-flash />
    <main class="card">
        @if ($title)<h1 class="font-display text-2xl font-extrabold">{{ $title }}</h1>@endif
        @if ($subtitle)<p class="mt-1 text-sm text-muted">{{ $subtitle }}</p>@endif
        <div class="mt-5">{{ $slot }}</div>
    </main>
    <p class="mt-6 text-center font-hand text-xl text-terra-ink dark:text-mustard">{{ setting('brand.slogan') }}</p>
</div>
@livewireScripts
</body>
</html>
