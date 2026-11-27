@props(['answer', 'reactionData' => null, 'showQuestion' => false, 'compact' => false])
@php
    $author = $answer->publicAuthor();
    $viewer = auth()->user();
    $original = $answer->publicOriginal($viewer);
@endphp
<article {{ $attributes->merge(['class' => 'card flex flex-col gap-3']) }} id="odpoved-{{ $answer->id }}">
    <header class="flex items-center gap-2.5">
        <x-avatar :user="$author" :anonymous="! $author" :size="38" />
        <span class="flex min-w-0 flex-1 flex-col text-sm leading-tight">
            @if ($author)
                <a href="{{ route('profiles.show', $author) }}" class="truncate font-bold text-ink no-underline hover:underline">{{ $author->name }}</a>
            @else
                <b>Anonym</b>
            @endif
            <span class="text-[12px] text-muted">{{ local_time($answer->published_at)?->diffForHumans() }}@if ($answer->edited_at) · upraveno @endif</span>
        </span>
        <span class="badge-mustard">{{ $answer->is_stylized ? ($answer->style?->name ?? 'Stylizace') : 'Originál' }}</span>
    </header>

    @if ($showQuestion && $answer->question)
        <a href="{{ $answer->question->url() }}" class="text-[13px] text-muted no-underline hover:underline">{{ $answer->question->text }}</a>
    @endif

    <p class="font-display text-[19px] leading-snug font-medium">„{{ $answer->display_text }}“</p>

    <div class="flex flex-wrap items-center gap-1.5 text-[12px] text-muted" x-data="{ open: false }">
        @if ($answer->is_stylized)
            <x-icon name="sparkle" :size="14" /> Stylizováno AI
            @if ($original !== null)
                · <button type="button" class="underline" @click="open = !open" :aria-expanded="open">zobrazit originál</button>
                <span x-show="open" x-cloak class="w-full text-ink">Originál: „{{ $original }}“</span>
            @endif
        @else
            <x-icon name="sparkle" :size="14" /> Bez AI úprav
        @endif
        @if ($answer->moderation_status->value !== 'approved' && $viewer && ($viewer->id === $answer->user_id || $viewer->can('moderation.manage')))
            <span class="badge-terra">{{ $answer->moderation_status->label() }}</span>
        @endif
    </div>

    @unless ($compact)
        <footer class="mt-auto flex items-start gap-1 border-t border-line pt-3">
            <div class="min-w-0 flex-1"><livewire:reactions type="answer" :id="$answer->id" :owner-id="$answer->user_id" :preloaded="$reactionData" :key="'r-a-'.$answer->id" /></div>
            <a href="{{ $answer->url() }}#komentare" class="btn-ghost w-10 px-0" aria-label="Komentáře ({{ $answer->comments_count }})">
                <x-icon name="comment" :size="18" />@if ($answer->comments_count)<span class="text-xs">{{ $answer->comments_count }}</span>@endif
            </a>
            <button type="button" x-data="share(@js($answer->url()), @js('„'.$answer->display_text.'“ – MáToCenu.cz'))" @click="go()" class="btn-ghost w-10 px-0" aria-label="Sdílet">
                <x-icon name="share" :size="18" x-show="!copied" /><x-icon name="check" :size="18" x-show="copied" x-cloak />
            </button>
            @auth
                @if (auth()->id() !== $answer->user_id)
                    <livewire:report-button type="answer" :id="$answer->id" :key="'rp-a-'.$answer->id" />
                @endif
            @endauth
        </footer>
    @endunless
</article>
