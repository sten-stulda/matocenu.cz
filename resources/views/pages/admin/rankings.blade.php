<?php

use App\Domain\Administration\Services\AuditLogger;
use App\Domain\Rankings\Services\RankingEngine;
use App\Models\Achievement;
use App\Models\RankingSnapshot;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin')] #[Title('Žebříčky a ocenění')] class extends Component {
    /** @var array<int, int> */
    public array $thresholds = [];

    public function mount(): void
    {
        $this->thresholds = Achievement::pluck('threshold', 'id')->all();
    }

    public function recompute(RankingEngine $engine): void
    {
        $this->authorize('rankings.manage');
        foreach (['day', 'week', 'month'] as $p) {
            $engine->snapshot($p);
        }
        session()->flash('status', 'Žebříčky přepočítány.');
    }

    public function toggle(int $id): void
    {
        $this->authorize('rankings.manage');
        $a = Achievement::findOrFail($id);
        $a->update(['is_active' => ! $a->is_active]);
        app(AuditLogger::class)->log('achievement.toggled', $a, ['active' => $a->is_active]);
    }

    public function saveThreshold(int $id): void
    {
        $this->authorize('rankings.manage');
        $this->validate(["thresholds.$id" => 'required|integer|min:1|max:1000000']);
        $a = Achievement::findOrFail($id);
        $a->update(['threshold' => $this->thresholds[$id]]);
        app(AuditLogger::class)->log('achievement.threshold', $a, ['threshold' => $a->threshold]);
    }

    public function with(): array
    {
        return [
            'achievements' => Achievement::withCount(['users' => fn ($q) => $q])->orderBy('sort_order')->get(),
            'snapshots' => RankingSnapshot::orderByDesc('computed_at')->limit(15)->get(),
        ];
    }
}; ?>

<div class="grid gap-6 xl:grid-cols-2">
    <section class="card space-y-3">
        <div class="flex items-center justify-between"><h2 class="font-display text-xl font-bold">Výpočet žebříčků</h2><button wire:click="recompute" class="btn-accent btn-sm">Přepočítat teď</button></div>
        <p class="text-sm text-muted">Parametry (gravitace, váha unikátních lidí, max. odpovědí autora za den) nastavíš v <a class="link" href="{{ route('admin.settings') }}">Nastavení → Žebříčky</a>, váhy reakcí v <a class="link" href="{{ route('admin.reactions') }}">Reakce</a>. Veřejně je vzorec popsán na <a class="link" href="{{ route('rankings.method') }}" target="_blank">/zebricky/jak-pocitame</a>.</p>
        <table class="table-admin"><thead><tr><th>Typ</th><th>Období</th><th class="text-right">Položek</th><th>Spočítáno</th></tr></thead><tbody>
            @foreach ($snapshots as $s)<tr><td>{{ $s->type }}</td><td>{{ $s->period_key }}</td><td class="text-right">{{ count($s->items) }}</td><td>{{ local_time($s->computed_at)->format('j. n. H:i') }}</td></tr>@endforeach
        </tbody></table>
    </section>
    <section class="card space-y-3">
        <h2 class="font-display text-xl font-bold">Ocenění</h2>
        <table class="table-admin"><thead><tr><th>Ocenění</th><th>Pravidlo</th><th>Práh</th><th class="text-right">Má</th><th></th></tr></thead><tbody>
            @foreach ($achievements as $a)
                <tr><td><span class="badge {{ $a->badgeClass() }}">{{ $a->name }}</span>@if ($a->is_title) <span class="text-xs text-muted">titul</span>@endif<br><span class="text-xs text-muted">{{ $a->description }}</span></td>
                    <td><code class="text-xs">{{ $a->rule }}</code></td>
                    <td><div class="flex gap-1"><input type="number" wire:model="thresholds.{{ $a->id }}" class="input w-24 py-1" aria-label="Práh"><button wire:click="saveThreshold({{ $a->id }})" class="btn-ghost btn-sm">OK</button></div></td>
                    <td class="text-right">{{ $a->users_count }}</td>
                    <td><button wire:click="toggle({{ $a->id }})" class="btn-ghost btn-sm">{{ $a->is_active ? 'Vypnout' : 'Zapnout' }}</button></td></tr>
            @endforeach
        </tbody></table>
        <p class="text-xs text-muted">Ocenění se udělují jen za veřejný obsah a reakce jiných lidí. Žádné připomínky „nepřerušte sérii“ – nechceme závislostní mechaniky.</p>
    </section>
</div>
