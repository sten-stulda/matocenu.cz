<?php

use App\Domain\AI\Services\AiCostReport;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin')] #[Title('Náklady AI')] class extends Component {
    public function with(AiCostReport $report): array
    {
        return ['r' => $report->summary()];
    }
}; ?>

<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card"><p class="text-sm text-muted">Náklady dnes</p><p class="mt-1 font-display text-4xl font-extrabold">{{ cz_number($r['today'], 2) }} Kč</p>
            <div class="mt-2 h-2 rounded bg-surface-2"><div class="h-2 rounded bg-mustard" style="width: {{ min(100, $r['daily_budget'] > 0 ? $r['today'] / $r['daily_budget'] * 100 : 0) }}%"></div></div>
            <p class="mt-1 text-sm text-muted">limit {{ cz_number($r['daily_budget'], 0) }} Kč@if ($r['per_generation'] !== null) · {{ cz_number($r['per_generation'], 3) }} Kč / generování @endif</p></div>
        <div class="card"><p class="text-sm text-muted">Tento měsíc</p><p class="mt-1 font-display text-4xl font-extrabold">{{ cz_number($r['month'], 2) }} Kč</p><p class="mt-1 text-sm text-muted">limit {{ cz_number($r['monthly_budget'], 0) }} Kč</p></div>
        <div class="card"><p class="text-sm text-muted">Úspěšnost generování (30 dní)</p><p class="mt-1 font-display text-4xl font-extrabold">{{ $r['success_rate'] === null ? '–' : cz_number($r['success_rate'], 1).' %' }}</p><p class="mt-1 text-sm text-muted">odmítnutých výstupů: {{ $r['rejected'] }} · prům. latence {{ $r['avg_latency'] ?? '–' }} ms</p></div>
        <div class="card"><p class="text-sm text-muted">Úspora díky cache (30 dní)</p><p class="mt-1 font-display text-4xl font-extrabold">{{ cz_number($r['saved'], 2) }} Kč</p><p class="mt-1 text-sm text-muted">{{ $r['cache_hits'] }} zásahů cache · {{ $r['hit_rate'] === null ? '–' : $r['hit_rate'].' %' }} požadavků</p></div>
    </div>
    @include('pages.admin.widgets.ai-chart', ['days' => $r['days']])
    <div class="grid gap-6 xl:grid-cols-2">
        <section class="card"><h2 class="font-display text-xl font-bold">Podle modelu (30 dní)</h2>
            <table class="table-admin mt-3"><thead><tr><th>Model</th><th class="text-right">Volání</th><th class="text-right">Tokeny vstup/výstup</th><th class="text-right">Kč</th></tr></thead><tbody>
            @forelse ($r['by_model'] as $m)<tr><td><code class="text-xs">{{ $m->model }}</code></td><td class="text-right">{{ $m->calls }}</td><td class="text-right">{{ cz_number($m->input) }} / {{ cz_number($m->output) }}</td><td class="text-right">{{ cz_number($m->czk, 2) }}</td></tr>@empty<tr><td colspan="4" class="text-muted">Zatím žádná volání.</td></tr>@endforelse
            </tbody></table></section>
        <section class="card"><h2 class="font-display text-xl font-bold">Podle funkce (30 dní)</h2>
            <table class="table-admin mt-3"><thead><tr><th>Funkce</th><th class="text-right">Volání</th><th class="text-right">Chyby</th><th class="text-right">Kč</th></tr></thead><tbody>
            @forelse ($r['by_operation'] as $o)<tr><td>{{ ['answer_stylize' => 'Stylizace odpovědí', 'question_stylize' => 'Stylizace otázek', 'semantic_check' => 'Kontrola významu', 'prompt_test' => 'Testy promptů'][$o->operation] ?? $o->operation }}</td><td class="text-right">{{ $o->calls }}</td><td class="text-right">{{ $o->errors }}</td><td class="text-right">{{ cz_number($o->czk, 2) }}</td></tr>@empty<tr><td colspan="4" class="text-muted">Zatím žádná volání.</td></tr>@endforelse
            </tbody></table>
            <p class="mt-3 text-xs text-muted">Ceny: skutečné od OpenRouteru (usage accounting), jinak odhad z ceníku modelu. Logy neobsahují texty promptů ani odpovědí.</p></section>
    </div>
</div>
