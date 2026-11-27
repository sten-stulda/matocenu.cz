@php
    $variants = \App\Domain\AI\Services\QuestionStylizer::approved($question);
    $onDark = $onDark ?? false;
    $default = isset($daily) && $daily?->style && $variants->has($daily->style->slug) ? $daily->style->slug : 'original';
@endphp
@if ($variants->isNotEmpty())
    <div x-data="{ s: @js($default), texts: @js($variants->map->text->put('original', $question->text)),
                   pick() { const k = Object.keys(this.texts).filter(x => x !== 'original' && x !== this.s); this.s = k[Math.floor(Math.random() * k.length)]; },
                   apply(v) { const h = this.$root.closest('section, div.min-w-0')?.querySelector('[data-question-text]'); if (h) h.textContent = this.texts[v]; } }"
         x-init="apply(s); $watch('s', v => apply(v))"
         class="flex flex-col gap-2.5">
        <span class="text-[13px] {{ $onDark ? 'text-forest-100' : 'text-muted' }}">Přečti si otázku jinak – význam zůstává, mění se jen forma{{ $default !== 'original' ? ' · jazyková osobnost dne: '.$variants[$default]->style->name : '' }}:</span>
        <div role="group" aria-label="Jazykový styl otázky" class="flex flex-wrap gap-2">
            <button type="button" @click="s = 'original'" :aria-pressed="(s === 'original').toString()"
                    class="min-h-10 rounded-full border-[1.5px] px-3.5 text-[13px] font-semibold"
                    :class="s === 'original' ? 'border-mustard bg-mustard text-charcoal' : '{{ $onDark ? 'border-forest-300 text-cream' : 'border-line text-ink' }}'">Originál</button>
            @foreach ($variants as $slug => $v)
                <button type="button" @click="s = @js($slug)" :aria-pressed="(s === @js($slug)).toString()"
                        class="min-h-10 rounded-full border-[1.5px] px-3.5 text-[13px] font-semibold"
                        :class="s === @js($slug) ? 'border-mustard bg-mustard text-charcoal' : '{{ $onDark ? 'border-forest-300 text-cream' : 'border-line text-ink' }}'">{{ $v->style->name }}</button>
            @endforeach
            @if ($variants->count() > 1)
                <button type="button" @click="pick()" class="min-h-10 rounded-full border-[1.5px] px-3.5 text-[13px] font-semibold {{ $onDark ? 'border-forest-300 text-cream' : 'border-line text-ink' }}">Náhodný</button>
            @endif
        </div>
        <p class="text-[12px] {{ $onDark ? 'text-forest-100' : 'text-muted' }}" x-show="s !== 'original'" x-cloak><x-icon name="sparkle" :size="12" class="inline" /> Stylizováno AI, schválila redakce. Odpovídáš na stejnou otázku.</p>
    </div>
@endif
