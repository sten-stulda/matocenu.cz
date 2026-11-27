<?php

use App\Domain\Administration\Services\AuditLogger;
use App\Models\AiGenerationCache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] #[Title('AI cache')] class extends Component {
    use WithPagination;

    public function forget(int $id): void
    {
        $this->authorize('ai.manage');
        AiGenerationCache::whereKey($id)->delete();
    }

    public function purge(string $scope): void
    {
        $this->authorize('ai.manage');
        $n = $scope === 'expired' ? AiGenerationCache::where('expires_at', '<', now())->delete() : AiGenerationCache::query()->delete();
        app(AuditLogger::class)->log('ai.cache_purged', null, ['scope' => $scope, 'deleted' => $n]);
        session()->flash('status', "Smazáno {$n} záznamů.");
    }

    public function with(): array
    {
        return [
            'entries' => AiGenerationCache::latest('updated_at')->paginate(40),
            'stats' => ['count' => AiGenerationCache::count(), 'global' => AiGenerationCache::where('scope', 'global')->count(), 'hits' => (int) AiGenerationCache::sum('hits')],
        ];
    }
}; ?>

<div class="space-y-4">
    <p class="max-w-3xl text-sm text-muted">Klíč je HMAC-SHA256 z normalizovaných vstupů (otázka a verze, text, styl a verze, verze promptu, model, délka). Obecné krátké odpovědi („Ano“, „Já“, „Nevím“…) se sdílí v rámci otázky; ostatní jsou v cache jen pro autora (<code>user:ID</code>) – osobní obsah se nikdy nezobrazí jinému uživateli.</p>
    <div class="flex flex-wrap items-center gap-3">
        <span class="badge-outline">{{ $stats['count'] }} záznamů · {{ $stats['global'] }} sdílených · {{ $stats['hits'] }} zásahů</span>
        <button wire:click="purge('expired')" class="btn-outline btn-sm">Smazat expirované</button>
        <button wire:click="purge('all')" wire:confirm="Smazat celou AI cache? Další generování budou stát peníze." class="btn-danger btn-sm">Vyprázdnit cache</button>
    </div>
    <div class="card overflow-x-auto p-0">
        <table class="table-admin">
            <thead><tr><th>Varianty</th><th>Rozsah</th><th class="text-right">Zásahy</th><th class="text-right">Cena vzniku</th><th>Vytvořeno</th><th></th></tr></thead>
            <tbody>
            @forelse ($entries as $e)
                <tr><td class="max-w-md text-sm">@if (str_starts_with($e->scope, 'user:'))<i class="text-muted">soukromý obsah ({{ count($e->variants) }} variant)</i>@else @foreach ($e->variants as $v)„{{ $v }}“<br>@endforeach @endif</td>
                    <td><code class="text-xs">{{ $e->scope }}</code></td><td class="text-right">{{ $e->hits }}</td><td class="text-right">{{ cz_number($e->cost_czk, 4) }} Kč</td>
                    <td class="whitespace-nowrap">{{ local_time($e->created_at)->format('j. n. Y') }}</td><td><button wire:click="forget({{ $e->id }})" class="btn-ghost btn-sm">Smazat</button></td></tr>
            @empty
                <tr><td colspan="6" class="text-muted">Cache je prázdná.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $entries->links() }}
</div>
