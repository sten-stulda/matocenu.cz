@php $author = $need->publicAuthor(); @endphp
<x-layouts::app :title="$need->title" :description="\Illuminate\Support\Str::limit($need->description, 160)" :noindex="! $need->isPubliclyVisible()">
    <div class="grid gap-8 lg:grid-cols-[1fr_360px]">
        <div class="min-w-0 space-y-6">
            <nav class="text-[13px] text-muted"><a href="{{ route('needs.index') }}">Co lidem chybí</a> / {{ $need->category->name }}</nav>
            <div class="flex flex-wrap items-center gap-2 text-[12px]">
                <span class="rounded px-2 py-1 font-semibold {{ $need->category->tileClass() }}">{{ $need->category->name }}</span>
                <span class="badge-outline">{{ $need->type->label() }}</span>
                <span class="badge-outline">{{ $need->location?->name ?? 'Celá ČR' }}</span>
                <span class="{{ $need->status === 'solved' ? 'badge-forest' : 'badge-mustard' }}">{{ \App\Models\Need::STATUSES[$need->status] }}</span>
                <span class="badge-outline">{{ \App\Models\Need::URGENCY[$need->urgency] }}</span>
                @if (! $need->isPubliclyVisible())<span class="badge-dashed">{{ $need->moderation_status->label() }}</span>@endif
            </div>
            <h1 class="font-display text-4xl leading-[1.05] font-extrabold tracking-tight sm:text-5xl">{{ $need->title }}</h1>
            <p class="whitespace-pre-line text-[17px]">{{ $need->description }}</p>
            <p class="text-sm text-muted">Přidal{{ $author ? '/a' : 'a' }} <b class="text-ink">{{ $author?->name ?? 'Anonym' }}</b> · {{ local_time($need->published_at)->diffForHumans() }}</p>
            @if ($need->solved_note)<p class="card-dashed text-sm"><span class="font-hand text-xl text-forest">Splněno!</span> {{ $need->solved_note }}</p>@endif

            <livewire:need-interest :need-id="$need->id" />
            <livewire:need-offers :need-id="$need->id" />
            <div class="card"><livewire:comments type="need" :id="$need->id" /></div>
        </div>

        <aside class="space-y-4">
            <div class="card text-center">
                <p class="font-display text-5xl font-extrabold text-forest dark:text-primary">{{ cz_number($need->interested_count) }}</p>
                <p class="text-sm text-muted">{{ cz_plural($need->interested_count, 'člověk má', 'lidé mají', 'lidí má', false) }} zájem o řešení</p>
                <dl class="mt-4 grid grid-cols-2 gap-2 text-left text-sm">
                    @foreach (\App\Domain\Needs\Enums\InterestKind::cases() as $k)
                        <div class="rounded bg-surface-2 px-3 py-2"><dt class="text-[12px] text-muted">{{ $k->label() }}</dt><dd class="font-bold">{{ $need->{$k->counter()} }}</dd></div>
                    @endforeach
                </dl>
            </div>
            <div class="card space-y-2 text-sm">
                <h2 class="font-display text-lg font-bold">Ochota zaplatit</h2>
                @if ($stats['amounts_shown'])
                    <p>Medián <b>{{ cz_number($stats['median']) }} Kč{{ $stats['frequency'] ? ' '.$stats['frequency']->short() : '' }}</b>, běžně {{ cz_number($stats['p25']) }}–{{ cz_number($stats['p75']) }} Kč ({{ $stats['amount_samples'] }} odpovědí).</p>
                    @if ($stats['monthly_estimate'])<p>Hrubý odhad poptávky: <b>{{ cz_number($stats['monthly_estimate']) }} Kč měsíčně</b>.</p>@endif
                @else
                    <p class="text-muted">Částky ukážeme od {{ \App\Domain\Needs\Services\NeedStats::K_AMOUNT }} odpovědí (teď {{ $stats['amount_samples'] }}) – aby nešlo poznat jednotlivce.</p>
                @endif
                <p class="text-[12px] text-muted">{{ $stats['payers'] }} lidí deklaruje ochotu platit, {{ $stats['commitments'] }} z nich závazně. Deklarovaná ochota je nezávazná – nejde o objednávky ani garantované tržby.</p>
            </div>
            @if ($stats['regions'])
                <div class="card text-sm"><h2 class="font-display text-lg font-bold">Zájem podle kraje</h2>
                    <ul class="mt-2 space-y-1">@foreach ($stats['regions'] as $r)<li class="flex justify-between"><span>{{ $r['name'] }}</span><b>{{ $r['count'] }}</b></li>@endforeach</ul></div>
            @endif
            <div class="card text-sm"><h2 class="font-display text-lg font-bold">Vývoj</h2>
                <p class="mt-1">Za posledních 14 dní {{ cz_plural($stats['growth']['recent'], 'nový zájemce', 'noví zájemci', 'nových zájemců') }} (předtím {{ $stats['growth']['previous'] }}).</p>
                <ul class="mt-2 space-y-1 text-[12px] text-muted">@foreach ($history as $h)<li>{{ local_time(\Carbon\Carbon::parse($h->created_at))->format('j. n. Y') }} – {{ \App\Models\Need::STATUSES[$h->to_status] ?? $h->to_status }}@if ($h->note): {{ $h->note }}@endif</li>@endforeach</ul></div>
            @auth
                @if (auth()->id() === $need->user_id)<livewire:need-manage :need-id="$need->id" />
                @else<div class="card flex items-center justify-between text-sm"><span>Nepatří to sem?</span><livewire:report-button type="need" :id="$need->id" /></div>@endif
            @endauth
        </aside>
    </div>
</x-layouts::app>
