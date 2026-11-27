@php
    $reactionData = $answers ? app(\App\Domain\Reactions\Services\ReactionSummary::class)->for('answer', $answers, auth()->user()) : [];
    $tabs = ['odpovedi' => 'Odpovědi', 'otazky' => 'Otázky'] + ($isOwner ? ['oblibene' => 'Oblíbené'] : []) + (Route::has('rankings.index') ? ['oceneni' => 'Ocenění'] : []);
@endphp
<x-layouts::app :title="$profile->name" :description="'Profil uživatele '.$profile->name.' na MáToCenu.cz'" :noindex="true">
    <div class="grid gap-6 lg:grid-cols-[360px_1fr]">
        <aside class="card h-fit space-y-5">
            <div class="flex items-center gap-4">
                <x-avatar :user="$profile" :size="88" class="ring-4 ring-mustard" />
                <div>
                    <h1 class="font-display text-2xl font-extrabold">{{ $profile->name }}</h1>
                    @includeIf('pages.profiles.partials.title-badge')
                    <p class="text-sm text-muted">@if ($region){{ $region->name }} · @endif člen od {{ $memberSince->year }}</p>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-2 text-center">
                @foreach (['questions' => ['otázka', 'otázky', 'otázek'], 'answers' => ['odpověď', 'odpovědi', 'odpovědí'], 'reactions' => ['reakce', 'reakce', 'reakcí']] as $k => $w)
                    <div class="rounded-md bg-surface-2 p-3"><p class="font-display text-2xl font-extrabold">{{ cz_number($stats[$k]) }}</p><p class="text-[12px] text-muted">{{ cz_plural($stats[$k], ...[...$w, false]) }}</p></div>
                @endforeach
            </div>
            @if ($streak > 1)<p class="font-hand text-xl text-terra-ink dark:text-mustard">Série: {{ $streak }} dní s odpovědí na otázku dne</p>@endif
            @if ($isOwner)<a href="{{ route('account.settings') }}" class="btn-outline btn-sm">Upravit profil</a>
            @elseif (auth()->check())<livewire:block-button :user-id="$profile->id" />@endif
        </aside>

        <section class="card">
            <nav class="flex gap-1 border-b border-line" aria-label="Obsah profilu">
                @foreach ($tabs as $k => $l)
                    <a href="?tab={{ $k }}" @class(['border-b-[3px] px-3 py-2 text-sm font-semibold no-underline', 'border-forest text-ink dark:border-primary' => $tab === $k, 'border-transparent text-muted' => $tab !== $k])>{{ $l }}</a>
                @endforeach
            </nav>
            <div class="mt-4 space-y-4">
                @if ($tab === 'odpovedi')
                    @forelse ($answers as $a)
                        <div class="border-b border-line pb-4 last:border-0">
                            <a href="{{ $a->question->url() }}" class="text-[13px] text-muted no-underline hover:underline">{{ $a->question->text }}</a>
                            <p class="font-display text-lg">„{{ $a->display_text }}“ @if ($isOwner && $a->is_anonymous)<span class="badge-outline">anonymně</span>@endif</p>
                            <p class="text-[12px] font-bold text-forest dark:text-primary">{{ cz_plural($a->reactions_count, 'reakce', 'reakce', 'reakcí') }}</p>
                        </div>
                    @empty <x-empty title="Zatím žádné veřejné odpovědi." /> @endforelse
                    {{ $answers->links() }}
                @elseif ($tab === 'otazky')
                    <ul>@forelse ($questions as $q)<x-question-row :question="$q" />@empty<x-empty title="Zatím žádné otázky." />@endforelse</ul>
                    {{ $questions->links() }}
                @elseif ($tab === 'oblibene')
                    @forelse ($bookmarks as $b)
                        @if ($b->bookmarkable instanceof \App\Models\Question)<x-question-row :question="$b->bookmarkable" />@endif
                    @empty <x-empty title="Nic sis zatím neuložil/a." /> @endforelse
                    {{ $bookmarks->links() }}
                @elseif ($tab === 'oceneni')
                    @includeIf('pages.profiles.partials.achievements')
                @endif
            </div>
        </section>
    </div>
</x-layouts::app>
