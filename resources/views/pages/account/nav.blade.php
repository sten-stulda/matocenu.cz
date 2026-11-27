@php
    $items = [
        'profile' => ['Můj profil', 'account.profile'],
        'settings' => ['Nastavení', 'account.settings'],
        'security' => ['Zabezpečení', 'account.security'],
        'privacy' => ['Soukromí a data', 'account.privacy'],
        'notifications' => ['Upozornění', 'account.notifications'],
    ];
@endphp
<nav aria-label="Účet" class="flex gap-1 overflow-x-auto border-b border-line">
    @foreach ($items as $key => [$label, $route])
        @if (Route::has($route))
            <a href="{{ route($route) }}" @class(['whitespace-nowrap border-b-[3px] px-3 py-2.5 text-sm font-semibold no-underline', 'border-forest text-ink dark:border-primary' => $active === $key, 'border-transparent text-muted hover:text-ink' => $active !== $key]) @if ($active === $key) aria-current="page" @endif>{{ $label }}</a>
        @endif
    @endforeach
</nav>
