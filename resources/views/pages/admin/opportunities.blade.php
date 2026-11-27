<?php

use App\Domain\Needs\Services\NeedStats;
use App\Models\Need;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin')] #[Title('Příležitosti a poptávka')] class extends Component {
    public function with(NeedStats $stats): array
    {
        $rows = Need::query()->visible()->with(['category', 'location'])->where('interested_count', '>', 0)->orderByDesc('interested_count')->limit(50)->get()
            ->map(fn ($n) => ['need' => $n, 's' => $stats->forNeed($n)]);

        return [
            'rows' => $rows,
            'demand' => $stats->regionDemand(),
            'totals' => [
                'needs' => Need::query()->visible()->count(),
                'interested' => DB::table('need_interests')->distinct()->count('user_id'),
                'payers' => DB::table('need_interests')->where('kind', 'would_pay')->distinct()->count('user_id'),
                'consenting' => DB::table('need_interests')->where('contact_consent', true)->distinct()->count('user_id'),
                'solved' => Need::where('status', 'solved')->count(),
            ],
        ];
    }
}; ?>

<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach (['needs' => 'Zveřejněné potřeby', 'interested' => 'Unikátní zájemci', 'payers' => 'Deklarovali platbu', 'consenting' => 'Souhlasí s kontaktem', 'solved' => 'Splněno'] as $k => $l)
            <div class="card"><p class="text-sm text-muted">{{ $l }}</p><p class="mt-1 font-display text-3xl font-extrabold">{{ cz_number($totals[$k]) }}</p></div>
        @endforeach
    </div>
    <div class="card overflow-x-auto p-0">
        <table class="table-admin"><thead><tr><th>Potřeba</th><th class="text-right">Zájemci</th><th class="text-right">Platit</th><th>Medián (vzorek)</th><th class="text-right">Odhad/měs.</th><th>Kraje (≥{{ NeedStats::K_REGION }})</th><th>Růst 14 d</th></tr></thead><tbody>
        @forelse ($rows as $r)
            <tr><td><a href="{{ $r['need']->url() }}" target="_blank">{{ $r['need']->title }}</a></td><td class="text-right">{{ $r['need']->interested_count }}</td><td class="text-right">{{ $r['s']['payers'] }}</td>
                <td>{{ $r['s']['amounts_shown'] ? cz_number($r['s']['median']).' Kč' : '–' }} ({{ $r['s']['amount_samples'] }})</td><td class="text-right">{{ $r['s']['monthly_estimate'] ? cz_number($r['s']['monthly_estimate']) : '–' }}</td>
                <td class="text-xs">{{ collect($r['s']['regions'])->map(fn ($x) => $x['name'].' '.$x['count'])->implode(', ') ?: '–' }}</td>
                <td>{{ $r['s']['growth']['recent'] }} / {{ $r['s']['growth']['previous'] }}</td></tr>
        @empty<tr><td colspan="7" class="text-muted">Zatím žádný zájem.</td></tr>@endforelse
        </tbody></table>
    </div>
    <p class="text-xs text-muted">I administrace vidí jen agregované údaje a k-anonymní rozdělení. Jednotlivé zájemce nelze exportovat – kontakt probíhá jen přes zprávy v platformě se souhlasem.</p>
</div>
