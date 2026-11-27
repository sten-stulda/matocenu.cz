<?php

use App\Domain\AI\Services\AiStatus;
use App\Domain\AI\Services\ModelCatalog;
use App\Domain\AI\Services\ProviderFactory;
use App\Domain\AI\Services\UrlGuard;
use App\Domain\Administration\Services\AuditLogger;
use App\Models\AiModel;
use App\Models\AiProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin')] #[Title('AI poskytovatelé a modely')] class extends Component {
    /** @var array<int, string> */
    public array $keys = [];
    /** @var array<int, string> */
    public array $urls = [];

    public string $newDriver = 'openai';
    public ?int $editModel = null;
    public array $m = [];

    public function mount(): void
    {
        foreach (AiProvider::all() as $p) {
            $this->urls[$p->id] = (string) $p->base_url;
        }
    }

    public function saveKey(int $id): void
    {
        $this->authorize('ai.manage');
        $p = AiProvider::findOrFail($id);
        $key = trim($this->keys[$id] ?? '');
        $url = trim($this->urls[$id] ?? '');
        if ($url !== '') {
            try {
                UrlGuard::assertAllowed($url, $p->driver);
            } catch (\InvalidArgumentException $e) {
                $this->addError("urls.$id", $e->getMessage());

                return;
            }
        }
        $p->base_url = $url ?: null;
        if ($key !== '') {
            $p->api_key = $key; // encrypted cast
        }
        $p->save();
        $this->keys[$id] = '';
        app(AuditLogger::class)->log('ai.provider_updated', $p, ['key_changed' => $key !== '', 'base_url' => $p->base_url]);
        session()->flash('status', 'Uloženo. Klíč je šifrovaný a nikdy se nezobrazí celý.');
    }

    public function verify(int $id, ProviderFactory $factory): void
    {
        $this->authorize('ai.manage');
        $p = AiProvider::findOrFail($id);
        $r = $factory->make($p)->verify();
        $p->forceFill(['verified_at' => $r['ok'] ? now() : null, 'verify_message' => mb_substr($r['message'], 0, 300)])->save();
        session()->flash($r['ok'] ? 'status' : 'error', $r['message']);
    }

    public function toggle(int $id): void
    {
        $this->authorize('ai.manage');
        $p = AiProvider::findOrFail($id);
        if (! $p->is_active && $p->driver !== 'fake' && ! $p->resolvedApiKey() && ! in_array($p->driver, ['ollama', 'openai_compatible'], true)) {
            session()->flash('error', 'Nejdřív zadej API klíč.');

            return;
        }
        $p->update(['is_active' => ! $p->is_active]);
        app(AuditLogger::class)->log('ai.provider_toggled', $p, ['active' => $p->is_active]);
    }

    public function addProvider(): void
    {
        $this->authorize('ai.manage');
        $this->validate(['newDriver' => ['required', Rule::in(array_keys(AiProvider::DRIVERS))]]);
        $slug = $this->newDriver;
        $i = 2;
        while (AiProvider::where('slug', $slug)->exists()) {
            $slug = $this->newDriver.'-'.$i++;
        }
        $p = AiProvider::create(['slug' => $slug, 'name' => AiProvider::DRIVERS[$this->newDriver][0], 'driver' => $this->newDriver, 'is_active' => false]);
        $this->urls[$p->id] = '';
    }

    public function editModel(?int $id = null, ?int $providerId = null): void
    {
        $model = $id ? AiModel::findOrFail($id) : new AiModel(['ai_provider_id' => $providerId, 'max_output_tokens' => 400, 'temperature' => '0.90', 'is_active' => true]);
        $this->editModel = $id ?? 0;
        $this->m = [
            'ai_provider_id' => $model->ai_provider_id, 'model' => (string) $model->model, 'name' => (string) $model->name,
            'input_price_per_mtok' => (string) $model->input_price_per_mtok, 'output_price_per_mtok' => (string) $model->output_price_per_mtok,
            'max_output_tokens' => $model->max_output_tokens, 'temperature' => (string) $model->temperature, 'is_active' => (bool) $model->is_active,
        ];
    }

    public function saveModel(): void
    {
        $this->authorize('ai.manage');
        $data = $this->validate([
            'm.ai_provider_id' => 'required|exists:ai_providers,id',
            'm.model' => 'required|string|max:120',
            'm.name' => 'required|string|max:120',
            'm.input_price_per_mtok' => 'nullable|numeric|min:0|max:1000',
            'm.output_price_per_mtok' => 'nullable|numeric|min:0|max:1000',
            'm.max_output_tokens' => 'required|integer|min:50|max:4000',
            'm.temperature' => 'nullable|numeric|min:0|max:2',
            'm.is_active' => 'boolean',
        ])['m'];
        foreach (['input_price_per_mtok', 'output_price_per_mtok', 'temperature'] as $k) {
            $data[$k] = $data[$k] === '' || $data[$k] === null ? null : $data[$k];
        }
        $model = $this->editModel ? AiModel::findOrFail($this->editModel) : new AiModel;
        $model->fill($data)->save();
        app(AuditLogger::class)->log('ai.model_saved', $model, $data);
        $this->editModel = null;
    }

    public function setRole(int $id, string $role): void
    {
        $this->authorize('ai.manage');
        abort_unless(in_array($role, ['is_primary', 'is_fallback'], true), 422);
        DB::transaction(function () use ($id, $role) {
            AiModel::where($role, true)->update([$role => false]);
            $m = AiModel::findOrFail($id);
            $m->forceFill([$role => true, ($role === 'is_primary' ? 'is_fallback' : 'is_primary') => false])->save();
            app(AuditLogger::class)->log('ai.model_role', $m, ['role' => $role]);
        });
    }

    public function syncPrices(ModelCatalog $catalog): void
    {
        $this->authorize('ai.manage');
        try {
            $r = $catalog->syncOpenRouterPrices();
            session()->flash('status', "Ceny aktualizovány u {$r['updated']} modelů.".($r['missing'] ? ' Nenalezeno v katalogu: '.implode(', ', $r['missing']) : ''));
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function with(AiStatus $status): array
    {
        return ['providers' => AiProvider::with('models')->orderBy('id')->get(), 'status' => $status, 'models' => $status->models()];
    }
}; ?>

<div class="space-y-6">
    <div class="card {{ $models ? '' : 'border-terra-ink' }}">
        @if ($models)
            <p><b>Aktivní:</b> primární {{ $models[0]->name }} ({{ $models[0]->provider->name }})@if (isset($models[1])), záložní {{ $models[1]->name }} ({{ $models[1]->provider->name }})@endif.
                @if ($status->isTestMode())<span class="badge-terra">Testovací režim – výstupy nejsou skutečná AI a v produkci je uvidí jen správci AI.</span>@endif</p>
        @else
            <p class="font-semibold text-terra-ink">AI je vypnutá: není aktivní poskytovatel s primárním modelem. Uživatelé zveřejňují originály. Zadej klíč OpenRouter a poskytovatele zapni.</p>
        @endif
        <p class="mt-1 text-sm text-muted">API klíče se ukládají šifrovaně (APP_KEY) a do prohlížeče se nikdy neposílají. Klíč lze zadat i proměnnou prostředí <code>OPENROUTER_API_KEY</code>.</p>
    </div>

    @foreach ($providers as $p)
        <section class="card space-y-4" wire:key="p{{ $p->id }}">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="font-display text-xl font-bold">{{ $p->name }}</h2>
                <code class="text-xs text-muted">{{ $p->driver }}</code>
                <span class="{{ $p->is_active ? 'badge-forest' : 'badge-outline' }}">{{ $p->is_active ? 'aktivní' : 'vypnuto' }}</span>
                @if ($p->verified_at)<span class="text-xs text-muted">ověřeno {{ local_time($p->verified_at)->format('j. n. H:i') }}</span>@endif
                <div class="ml-auto flex gap-2">
                    @if ($p->driver !== 'fake')<button wire:click="verify({{ $p->id }})" class="btn-outline btn-sm">Ověřit spojení</button>@endif
                    <button wire:click="toggle({{ $p->id }})" class="{{ $p->is_active ? 'btn-danger' : 'btn-forest' }} btn-sm">{{ $p->is_active ? 'Vypnout' : 'Zapnout' }}</button>
                </div>
            </div>
            @if ($p->verify_message)<p class="text-sm text-muted">{{ $p->verify_message }}</p>@endif
            @if ($p->driver !== 'fake')
                <div class="grid gap-3 md:grid-cols-[1fr_1fr_auto] md:items-end">
                    <div><label class="label" for="k{{ $p->id }}">API klíč @if ($p->maskedKey())<span class="font-normal text-muted">(nastaven: {{ $p->maskedKey() }})</span>@endif</label>
                        <input id="k{{ $p->id }}" type="password" wire:model="keys.{{ $p->id }}" class="input" autocomplete="off" placeholder="{{ $p->maskedKey() ? 'Ponechat stávající' : 'sk-…' }}"></div>
                    <div><label class="label" for="u{{ $p->id }}">Adresa API (prázdné = výchozí)</label>
                        <input id="u{{ $p->id }}" wire:model="urls.{{ $p->id }}" class="input" placeholder="{{ \App\Models\AiProvider::DRIVERS[$p->driver][1] ?? 'https://…/v1' }}">@error("urls.$p->id")<p class="error">{{ $message }}</p>@enderror</div>
                    <button wire:click="saveKey({{ $p->id }})" class="btn-forest">Uložit</button>
                </div>
            @endif
            <table class="table-admin">
                <thead><tr><th>Model</th><th>Vstup USD/1M</th><th>Výstup USD/1M</th><th>Max. tokenů</th><th>Temperature</th><th>Role</th><th></th></tr></thead>
                <tbody>
                @foreach ($p->models as $m)
                    <tr>
                        <td><b>{{ $m->name }}</b><br><code class="text-xs">{{ $m->model }}</code>@unless ($m->is_active) <span class="badge-outline">vypnut</span>@endunless</td>
                        <td>{{ $m->input_price_per_mtok ?? '–' }}</td><td>{{ $m->output_price_per_mtok ?? '–' }}</td>
                        <td>{{ $m->max_output_tokens }}</td><td>{{ $m->supports_temperature ? ($m->temperature ?? 'výchozí') : 'nepodporuje' }}</td>
                        <td>@if ($m->is_primary)<span class="badge-forest">primární</span>@elseif ($m->is_fallback)<span class="badge-mustard">záložní</span>@else
                            <button wire:click="setRole({{ $m->id }}, 'is_primary')" class="btn-ghost btn-sm">primární</button><button wire:click="setRole({{ $m->id }}, 'is_fallback')" class="btn-ghost btn-sm">záložní</button>@endif</td>
                        <td><button wire:click="editModel({{ $m->id }})" class="btn-ghost btn-sm">Upravit</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="flex flex-wrap gap-2">
                <button wire:click="editModel(null, {{ $p->id }})" class="btn-outline btn-sm">+ Model</button>
                @if ($p->driver === 'openrouter')<button wire:click="syncPrices" class="btn-outline btn-sm">Načíst ceny z katalogu OpenRouter</button>@endif
            </div>
        </section>
    @endforeach

    @if ($editModel !== null)
        <form wire:submit="saveModel" class="card grid gap-3 md:grid-cols-2">
            <h2 class="font-display text-lg font-bold md:col-span-2">{{ $editModel ? 'Upravit model' : 'Nový model' }}</h2>
            <div><label class="label" for="mm">ID modelu u poskytovatele</label><input id="mm" wire:model="m.model" class="input" placeholder="google/gemini-2.5-flash-lite">@error('m.model')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="mn">Název</label><input id="mn" wire:model="m.name" class="input">@error('m.name')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="mi">Cena vstupu (USD / 1M tokenů)</label><input id="mi" wire:model="m.input_price_per_mtok" class="input" inputmode="decimal"></div>
            <div><label class="label" for="mo">Cena výstupu (USD / 1M tokenů)</label><input id="mo" wire:model="m.output_price_per_mtok" class="input" inputmode="decimal"></div>
            <div><label class="label" for="mx">Max. výstupních tokenů</label><input id="mx" type="number" wire:model="m.max_output_tokens" class="input"></div>
            <div><label class="label" for="mt">Temperature (prázdné = výchozí)</label><input id="mt" wire:model="m.temperature" class="input" inputmode="decimal"></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="m.is_active" class="h-4 w-4 accent-forest"> Aktivní</label>
            <div class="flex gap-2 md:col-span-2"><button class="btn-forest">Uložit model</button><button type="button" wire:click="$set('editModel', null)" class="btn-ghost">Zrušit</button></div>
            <p class="text-xs text-muted md:col-span-2">Preferuj levné modely bez „reasoning“ – na krátké české texty stačí. Kurz USD→CZK a rozpočty nastavíš v Nastavení → AI.</p>
        </form>
    @endif

    <div class="card flex flex-wrap items-end gap-3">
        <div><label class="label" for="nd">Přidat poskytovatele</label>
            <select id="nd" wire:model="newDriver" class="input">@foreach (\App\Models\AiProvider::DRIVERS as $k => [$l])<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
        <button wire:click="addProvider" class="btn-outline">Přidat</button>
    </div>
</div>
