@php
    $owned = $profile->achievements()->get()->keyBy('id');
    $all = \App\Models\Achievement::where('is_active', true)->orderBy('sort_order')->get();
    $next = $isOwner ? app(\App\Domain\Gamification\Services\Achievements::class)->nextTitle($profile) : null;
@endphp
@if (! $achievementsVisible && ! $isOwner)
    <x-empty title="Uživatel ocenění nezobrazuje." />
@else
    @if ($next)
        <div class="mb-4">
            <div class="flex justify-between text-sm"><b>Do titulu {{ $next['name'] }}</b><span>{{ $next['current'] }} / {{ $next['target'] }}</span></div>
            <div class="mt-1 h-2.5 rounded bg-surface-2"><div class="h-2.5 rounded bg-forest dark:bg-primary" style="width: {{ min(100, round($next['current'] / max(1, $next['target']) * 100)) }}%"></div></div>
        </div>
    @endif
    <div class="flex flex-wrap gap-2">
        @foreach ($all as $a)
            @if ($owned->has($a->id))
                <span class="badge px-2.5 py-1 text-[12px] {{ $a->badgeClass() }}" title="{{ $a->description }} · {{ local_time(\Carbon\Carbon::parse($owned[$a->id]->pivot->awarded_at))->format('j. n. Y') }}">{{ $a->name }}</span>
            @elseif ($isOwner)
                <span class="badge px-2.5 py-1 text-[12px] {{ \App\Models\Achievement::COLORS['dashed'] }}" title="{{ $a->description }}">{{ $a->name }} – zamčeno</span>
            @endif
        @endforeach
    </div>
    @if ($isOwner && ! $achievementsVisible)<p class="mt-3 text-sm text-muted">Ostatní tvá ocenění nevidí (Nastavení → Soukromí).</p>@endif
@endif
