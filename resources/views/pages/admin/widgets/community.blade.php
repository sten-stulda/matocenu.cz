<section class="space-y-4 xl:col-span-2">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card"><p class="text-sm text-muted">Odpovědi dnes</p><p class="mt-1 font-display text-4xl font-extrabold">{{ cz_number($c['answers_today']) }}</p><p class="mt-1 text-sm text-muted">@if ($c['stylized_share'] !== null){{ $c['stylized_share'] }} % se stylizací · @endif celkem {{ cz_number($c['answers']) }}</p></div>
        <div class="card"><p class="text-sm text-muted">Otázky</p><p class="mt-1 font-display text-4xl font-extrabold">{{ cz_number($c['questions']) }}</p><p class="mt-1 text-sm text-muted">dnes {{ $c['questions_today'] }}</p></div>
        <div class="card"><p class="text-sm text-muted">Reakce dnes</p><p class="mt-1 font-display text-4xl font-extrabold">{{ cz_number($c['reactions_today']) }}</p><p class="mt-1 text-sm text-muted">celkem {{ cz_number($c['reactions']) }}</p></div>
        <a href="{{ route('admin.moderation') }}" class="card block no-underline {{ $c['queue'] ? 'bg-terra-100 dark:bg-surface' : '' }}">
            <p class="text-sm font-semibold text-terra-ink dark:text-terra">Ke kontrole / nahlášeno</p>
            <p class="mt-1 font-display text-4xl font-extrabold text-terra-ink dark:text-terra">{{ $c['queue'] }}</p>
            <p class="mt-1 text-sm text-terra-ink dark:text-terra">{{ $c['reports_open'] }} otevřených nahlášení@if ($c['reports_old']) · {{ $c['reports_old'] }} čeká déle než 1 h @endif</p>
        </a>
    </div>
    <div class="grid gap-4 xl:grid-cols-2">
        <div class="card">
            <div class="flex items-center justify-between"><h2 class="font-display text-xl font-bold">Plán otázky dne</h2>@can('daily.manage')<a href="{{ route('admin.daily') }}" class="btn-accent btn-sm">Naplánovat</a>@endcan</div>
            <ul class="mt-3 divide-y divide-line text-sm">
                @forelse ($plan as $p)
                    <li class="flex items-center gap-3 py-2.5"><b class="w-16">{{ $p->date->translatedFormat('D j.') }}</b><span class="flex-1">{{ $p->question->text }}</span><span class="badge-outline">{{ \App\Models\DailyQuestion::STATUSES[$p->status] }}</span></li>
                @empty
                    <li class="py-3 text-muted">Nic naplánováno – bez otázky dne homepage ukáže prázdný stav.</li>
                @endforelse
            </ul>
        </div>
        <div class="card">
            <h2 class="font-display text-xl font-bold">Nejaktivnější kategorie (7 dní)</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @php $max = max(1, (int) ($topCategories->max('n') ?? 1)); @endphp
                @forelse ($topCategories as $t)
                    <li><div class="flex justify-between"><span>{{ $t->name }}</span><b>{{ $t->n }}</b></div><div class="mt-1 h-2 rounded bg-surface-2"><div class="h-2 rounded bg-forest dark:bg-primary" style="width: {{ round($t->n / $max * 100) }}%"></div></div></li>
                @empty
                    <li class="text-muted">Zatím žádné odpovědi.</li>
                @endforelse
            </ul>
        </div>
    </div>
</section>
