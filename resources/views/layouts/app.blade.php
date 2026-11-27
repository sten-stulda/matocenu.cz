@props(['title' => null, 'description' => null, 'canonical' => null, 'ogImage' => null, 'ogType' => 'website', 'noindex' => false, 'jsonLd' => [], 'current' => null])
@php
    $user = auth()->user();
    $nav = collect([
        'home' => ['Domů', 'home'],
        'daily' => ['Otázka dne', 'daily.show'],
        'latest' => ['Nejnovější', 'questions.index'],
        'funniest' => ['Nejvtipnější', 'answers.best'],
        'needs' => ['Co lidem chybí', 'needs.index'],
        'rankings' => ['Žebříčky', 'rankings.index'],
    ])->filter(fn ($i) => Route::has($i[1]));
    $current ??= collect($nav)->search(fn ($i) => request()->routeIs($i[1]));
    $bottom = collect([
        'home' => ['Domů', 'home', 'home'],
        'discover' => ['Objevovat', 'discover', 'compass'],
        'add' => ['Přidat', 'questions.create', 'plus'],
        'rankings' => ['Žebříčky', 'rankings.index', 'trophy'],
        'profile' => ['Profil', $user ? 'account.profile' : 'login', 'user'],
    ])->filter(fn ($i) => Route::has($i[1]));
    $unread = $user ? $user->unreadNotifications()->count() : 0;
@endphp
<!DOCTYPE html>
<html lang="cs">
<head>
    @include('partials.head', compact('title', 'description', 'canonical', 'ogImage', 'ogType', 'noindex', 'jsonLd'))
</head>
<body class="paper min-h-screen pb-24 md:pb-0">
<a href="#obsah" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:rounded focus:bg-mustard focus:px-3 focus:py-2 focus:text-charcoal">Přeskočit na obsah</a>

<header class="sticky top-0 z-30 border-b border-line bg-bg/95 backdrop-blur">
    <div class="container-page flex h-16 items-center gap-3 md:h-[72px] md:gap-6">
        <a href="{{ route('home') }}" class="shrink-0" aria-label="MáToCenu.cz – domů"><x-logo size="md" /></a>

        <nav class="hidden items-center gap-1 lg:flex" aria-label="Hlavní navigace">
            @foreach ($nav as $key => [$label, $route])
                <a href="{{ route($route) }}" @class(['rounded-md px-3 py-2 text-[14px] font-semibold no-underline', 'bg-forest text-cream' => $current === $key, 'text-ink hover:bg-surface-2' => $current !== $key])
                   @if ($current === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        <div class="ml-auto flex items-center gap-1.5 sm:gap-2">
            @if (Route::has('search'))
                <a href="{{ route('search') }}" class="btn-ghost w-11 px-0" aria-label="Hledat"><x-icon name="search" /></a>
            @endif
            <button type="button" x-data="theme" @click="toggle()" class="btn-ghost w-11 px-0" aria-label="Přepnout světlý / tmavý režim">
                <x-icon name="moon" class="dark:hidden" /><x-icon name="sun" class="hidden dark:block" />
            </button>
            @auth
                @if (Route::has('account.notifications'))
                    <a href="{{ route('account.notifications') }}" class="btn-ghost relative w-11 px-0" aria-label="Upozornění{{ $unread ? ' ('.$unread.' nepřečtených)' : '' }}">
                        <x-icon name="bell" />
                        @if ($unread)<span class="absolute right-2 top-2 h-2.5 w-2.5 rounded-full bg-terra ring-2 ring-bg"></span>@endif
                    </a>
                @endif
                @if (Route::has('questions.create'))
                    <a href="{{ route('questions.create') }}" class="btn-accent hidden md:inline-flex"><x-icon name="plus" :size="16" /> Přidat otázku</a>
                @endif
                <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                    <button type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="menu" class="rounded-full" aria-label="Uživatelská nabídka">
                        <x-avatar :user="$user" :size="40" />
                    </button>
                    <div x-cloak x-show="open" x-transition role="menu" class="absolute right-0 mt-2 w-60 rounded-[10px] border border-line bg-surface p-2 shadow-[var(--shadow-pop)]">
                        <p class="px-3 py-2 text-sm"><b>{{ $user->name }}</b></p>
                        @if (Route::has('account.profile'))<a role="menuitem" href="{{ route('account.profile') }}" class="block rounded px-3 py-2 text-sm text-ink no-underline hover:bg-surface-2">Můj profil</a>@endif
                        @if (Route::has('account.settings'))<a role="menuitem" href="{{ route('account.settings') }}" class="block rounded px-3 py-2 text-sm text-ink no-underline hover:bg-surface-2">Nastavení a soukromí</a>@endif
                        @if ($user->canAccessAdmin())<a role="menuitem" href="{{ route('admin.dashboard') }}" class="block rounded px-3 py-2 text-sm text-ink no-underline hover:bg-surface-2">Administrace</a>@endif
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button role="menuitem" class="block w-full rounded px-3 py-2 text-left text-sm hover:bg-surface-2">Odhlásit se</button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="btn-ghost">Přihlásit</a>
                <a href="{{ route('register') }}" class="btn-accent hidden sm:inline-flex">Registrace</a>
            @endauth
        </div>
    </div>
</header>

<x-flash />

<main id="obsah" {{ $attributes->merge(['class' => 'container-page py-6 md:py-10']) }}>
    {{ $slot }}
</main>

<footer class="mt-10 bg-charcoal text-cream">
    <div class="container-page flex flex-col gap-4 py-8 md:flex-row md:items-center md:justify-between">
        <p class="font-display text-lg font-bold">{{ app(\App\Domain\Administration\Services\Settings::class)->list('brand.slogans_alt')[0] ?? setting('brand.slogan') }}</p>
        <nav aria-label="Patička" class="flex flex-wrap gap-x-4 gap-y-2 text-sm">
            @foreach (['community' => 'Pravidla komunity', 'ai' => 'Jak pracuje AI', 'privacy' => 'Soukromí', 'terms' => 'Podmínky', 'cookies' => 'Cookies'] as $type => $label)
                <a class="text-cream/85 no-underline hover:text-mustard" href="{{ route('legal.show', \App\Models\LegalDocument::TYPES[$type][1]) }}">{{ $label }}</a>
            @endforeach
            @if (Route::has('quotes.index') && feature('quotes'))<a class="text-cream/85 no-underline hover:text-mustard" href="{{ route('quotes.index') }}">Hlášky</a>@endif
            @if (Route::has('rankings.method'))<a class="text-cream/85 no-underline hover:text-mustard" href="{{ route('rankings.method') }}">Jak počítáme žebříčky</a>@endif
            @if (Route::has('api.docs'))<a class="text-cream/85 no-underline hover:text-mustard" href="{{ route('api.docs') }}">API</a>@endif
        </nav>
    </div>
    <div class="container-page border-t border-white/10 py-4 text-xs text-cream/60">
        © {{ now()->year }} MáToCenu.cz · Názory patří lidem, AI jen pomáhá s formou.
    </div>
</footer>

{{-- Mobile bottom navigation --}}
<nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden" aria-label="Spodní navigace">
    <ul class="grid grid-cols-5 items-end">
        @foreach ($bottom as $key => [$label, $route, $icon])
            <li class="flex justify-center">
                @if ($key === 'add')
                    <a href="{{ route($route) }}" class="-mt-6 flex h-16 w-16 flex-col items-center justify-center rounded-full bg-mustard text-charcoal no-underline shadow-[var(--shadow-pop)] ring-4 ring-bg" aria-label="{{ $label }}">
                        <x-icon name="plus" :size="28" />
                    </a>
                @else
                    @php $active = request()->routeIs($route); @endphp
                    <a href="{{ route($route) }}" @class(['flex min-h-14 flex-col items-center justify-center gap-0.5 px-2 text-[12px] font-semibold no-underline', 'text-primary' => $active, 'text-muted' => ! $active]) @if ($active) aria-current="page" @endif>
                        <x-icon :name="$icon" :size="22" />{{ $label }}
                    </a>
                @endif
            </li>
        @endforeach
    </ul>
</nav>

@livewireScripts
@stack('scripts')
</body>
</html>
