@props(['question', 'meta' => 'answers'])
<li class="flex items-start justify-between gap-3 border-b border-line py-3 last:border-0">
    <a href="{{ $question->url() }}" class="font-semibold text-ink no-underline hover:underline">{{ $question->text }}</a>
    <span class="shrink-0 text-[12px] font-bold whitespace-nowrap text-terra-ink dark:text-terra">
        @if ($meta === 'time'){{ local_time($question->published_at)?->diffForHumans(short: true) }}
        @elseif ($meta === 'answer')<span class="text-forest dark:text-primary">Odpovědět</span>
        @else{{ cz_number($question->answers_count) }} odp.@endif
    </span>
</li>
