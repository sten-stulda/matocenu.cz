@props(['title' => null])
@php
    $user = auth()->user();
    $groups = [
        'Obsah' => [
            ['Přehled', 'admin.dashboard', 'admin.access'],
            ['Otázky', 'admin.questions', 'questions.manage'],
            ['Plán otázky dne', 'admin.daily', 'daily.manage'],
            ['Odpovědi', 'admin.answers', 'answers.manage'],
            ['Komentáře', 'admin.comments', 'comments.manage'],
            ['Kategorie', 'admin.categories', 'categories.manage'],
            ['Jazykové styly', 'admin.styles', 'styles.manage'],
            ['Reakce', 'admin.reactions', 'reactions.manage'],
        ],
        'Moderace' => [
            ['Moderační fronta', 'admin.moderation', 'moderation.manage'],
            ['Odvolání', 'admin.appeals', 'moderation.manage'],
        ],
        'AI' => [
            ['Poskytovatelé a modely', 'admin.ai.providers', 'ai.manage'],
            ['Prompty a verze', 'admin.ai.prompts', 'prompts.manage'],
            ['AI cache', 'admin.ai.cache', 'ai.manage'],
            ['Náklady AI', 'admin.ai.costs', 'ai.costs.view'],
        ],
        'Komunita' => [
            ['Žebříčky a ocenění', 'admin.rankings', 'rankings.manage'],
            ['Hlášky', 'admin.quotes', 'quotes.manage'],
            ['Potřeby', 'admin.needs', 'needs.manage'],
            ['Příležitosti', 'admin.opportunities', 'needs.analytics'],
        ],
        'Právo a obchod' => [
            ['Právní evidence', 'admin.legal', 'legal.manage'],
            ['Právní dokumenty', 'admin.legal.documents', 'legal.manage'],
            ['Produkty', 'admin.products', 'products.manage'],
        ],
        'Systém' => [
            ['Uživatelé', 'admin.users', 'users.view'],
            ['Role a oprávnění', 'admin.roles', 'roles.manage'],
            ['Žádosti GDPR', 'admin.privacy', 'privacy.manage'],
            ['Feature flags', 'admin.features', 'features.manage'],
            ['Nastavení', 'admin.settings', 'settings.manage'],
            ['Statistiky', 'admin.stats', 'analytics.view'],
            ['Auditní log', 'admin.audit', 'audit.view'],
        ],
    ];
    $badges = app(\App\Domain\Administration\Services\AdminBadges::class)->all();
@endphp
<!DOCTYPE html>
<html lang="cs">
<head>
    @include('partials.head', ['title' => ($title ? $title.' · ' : '').'Administrace', 'noindex' => true])
</head>
<body class="min-h-screen bg-[#EFE9DA] dark:bg-bg" x-data="{ nav: false }">
<div class="flex min-h-screen">
    <aside :class="nav ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'" class="fixed inset-y-0 left-0 z-40 w-64 overflow-y-auto bg-charcoal px-3 py-5 text-cream transition lg:static lg:translate-x-0">
        <a href="{{ route('admin.dashboard') }}" class="mb-5 block px-3"><x-logo :admin="true" size="sm" /></a>
        <nav aria-label="Administrace" class="space-y-4">
            @foreach ($groups as $group => $items)
                @php $visible = collect($items)->filter(fn ($i) => Route::has($i[1]) && $user->can($i[2])); @endphp
                @if ($visible->isNotEmpty())
                    <div>
                        <p class="px-3 pb-1 text-[11px] font-bold uppercase tracking-[.12em] text-cream/45">{{ $group }}</p>
                        @foreach ($visible as [$label, $route])
                            @php $active = request()->routeIs($route) || request()->routeIs($route.'.*'); @endphp
                            <a href="{{ route($route) }}" @class(['flex items-center justify-between rounded-md px-3 py-2 text-[14px] no-underline', 'bg-forest font-semibold text-cream' => $active, 'text-cream/85 hover:bg-white/5' => ! $active]) @if ($active) aria-current="page" @endif>
                                {{ $label }}
                                @if (($badges[$route] ?? 0) > 0)<span class="text-xs font-bold text-mustard">{{ $badges[$route] }}</span>@endif
                            </a>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </nav>
        <div class="mt-6 border-t border-white/10 px-3 pt-4 text-sm">
            <a href="{{ route('home') }}" class="text-cream/80 no-underline hover:text-mustard">← Zpět na web</a>
        </div>
    </aside>
    <div x-cloak x-show="nav" @click="nav = false" class="fixed inset-0 z-30 bg-black/40 lg:hidden"></div>

    <div class="min-w-0 flex-1">
        <header class="flex items-center gap-3 px-4 pt-5 sm:px-8">
            <button type="button" class="btn-ghost w-11 px-0 lg:hidden" @click="nav = true" aria-label="Otevřít menu"><x-icon name="menu" /></button>
            <h1 class="font-display text-2xl font-extrabold sm:text-3xl">{{ $title }}</h1>
            <div class="ml-auto flex items-center gap-2">
                <button type="button" x-data="theme" @click="toggle()" class="btn-ghost w-11 px-0" aria-label="Přepnout režim"><x-icon name="moon" class="dark:hidden" /><x-icon name="sun" class="hidden dark:block" /></button>
                <x-avatar :user="$user" :size="36" />
            </div>
        </header>
        <x-flash />
        <main id="obsah" class="px-4 py-6 sm:px-8">
            {{ $slot }}
        </main>
    </div>
</div>
@livewireScripts
@stack('scripts')
</body>
</html>
