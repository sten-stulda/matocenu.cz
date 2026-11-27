<?php

use App\Domain\AI\Exceptions\AiUnavailable;
use App\Domain\AI\Services\PromptLifecycle;
use App\Domain\AI\Services\PromptTester;
use App\Models\AiModel;
use App\Models\AiPrompt;
use App\Models\AiPromptTestCase;
use App\Models\AiPromptVersion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::admin')] #[Title('Prompty a verze')] class extends Component {
    #[Url]
    public ?int $prompt = null;

    #[Url]
    public ?int $version = null;

    public string $system = '';
    public string $userTemplate = '';
    public string $note = '';
    public ?int $testModel = null;
    public string $caseName = '';
    public string $caseVars = '';
    public bool $confirmCost = false;

    public function mount(): void
    {
        $this->prompt ??= AiPrompt::orderBy('id')->value('id');
        $this->loadVersion();
    }

    public function updatedPrompt(): void
    {
        $this->version = null;
        $this->loadVersion();
    }

    public function updatedVersion(): void
    {
        $this->loadVersion();
    }

    private function loadVersion(): void
    {
        $p = $this->prompt ? AiPrompt::find($this->prompt) : null;
        $v = $this->version ? AiPromptVersion::find($this->version) : $p?->production() ?? $p?->versions()->first();
        $this->version = $v?->id;
        $this->system = (string) $v?->system_template;
        $this->userTemplate = (string) $v?->user_template;
    }

    public function saveDraft(PromptLifecycle $life): void
    {
        $this->validate(['system' => 'required|string|max:20000', 'userTemplate' => 'required|string|max:5000', 'note' => 'nullable|string|max:500']);
        try {
            $v = $life->createDraft(auth()->user(), AiPrompt::findOrFail($this->prompt), $this->system, $this->userTemplate, $this->note ?: null);
            $this->version = $v->id;
            $this->note = '';
            session()->flash('status', "Uložena verze {$v->version} jako koncept.");
        } catch (InvalidArgumentException|AuthorizationException $e) {
            $this->addError('system', $e->getMessage());
        }
    }

    public function changeStatus(int $id, string $to, PromptLifecycle $life): void
    {
        try {
            $life->transition(auth()->user(), AiPromptVersion::findOrFail($id), $to, $this->note ?: null);
            session()->flash('status', 'Stav změněn.');
        } catch (InvalidArgumentException|AuthorizationException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function rollback(PromptLifecycle $life): void
    {
        try {
            $v = $life->rollback(auth()->user(), AiPrompt::findOrFail($this->prompt));
            $this->version = $v->id;
            $this->loadVersion();
            session()->flash('status', "Vráceno na verzi {$v->version}.");
        } catch (AiUnavailable|AuthorizationException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function addCase(): void
    {
        $this->authorize('prompts.manage');
        $vars = json_decode($this->caseVars, true);
        if (! is_array($vars)) {
            $this->addError('caseVars', 'Proměnné musí být platný JSON objekt, např. {"question": "…", "original_answer": "…"}');

            return;
        }
        $this->validate(['caseName' => 'required|string|max:120']);
        AiPromptTestCase::create(['ai_prompt_id' => $this->prompt, 'name' => $this->caseName, 'variables' => $vars, 'expectations' => ['max_length' => 100]]);
        $this->reset('caseName', 'caseVars');
    }

    public function runTests(PromptTester $tester): void
    {
        $this->authorize('prompts.manage');
        $model = $this->testModel ? AiModel::with('provider')->findOrFail($this->testModel) : null;
        $isFake = $model?->provider->driver === 'fake';
        if (! $isFake && ! $this->confirmCost) {
            $this->addError('confirmCost', 'Test volá skutečný model a stojí peníze. Potvrď prosím.');

            return;
        }
        $results = $tester->run(auth()->user(), AiPromptVersion::with('prompt')->findOrFail($this->version), $model);
        $ok = collect($results)->where('passed', true)->count();
        session()->flash('status', "Testy dokončeny: {$ok}/".count($results).' prošlo.');
        $this->confirmCost = false;
    }

    public function with(): array
    {
        $p = $this->prompt ? AiPrompt::with('versions.author', 'testCases')->find($this->prompt) : null;
        $v = $this->version ? AiPromptVersion::with(['author', 'approver'])->find($this->version) : null;

        return [
            'prompts' => AiPrompt::orderBy('id')->get(),
            'current' => $p,
            'selected' => $v,
            'models' => AiModel::with('provider')->where('is_active', true)->get(),
            'results' => $v ? $v->results()->with('testCase')->latest('created_at')->limit(30)->get() : collect(),
            'history' => $v ? DB::table('ai_prompt_status_history')->leftJoin('users', 'users.id', '=', 'ai_prompt_status_history.user_id')->where('ai_prompt_version_id', $v->id)->orderByDesc('ai_prompt_status_history.created_at')->get(['from_status', 'to_status', 'note', 'users.name', 'ai_prompt_status_history.created_at']) : collect(),
        ];
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap gap-2">
        @foreach ($prompts as $pr)
            <button wire:click="$set('prompt', {{ $pr->id }})" @class(['btn btn-sm', 'bg-forest text-cream' => $prompt === $pr->id, 'text-ink hover:bg-surface-2' => $prompt !== $pr->id])>{{ $pr->name }}</button>
        @endforeach
    </div>
    @if ($current)
        <p class="text-sm text-muted">{{ $current->purpose }} · proměnné: @foreach ($current->variables as $var)<code>{!! e('{'.'{'.$var.'}'.'}') !!}</code> @endforeach</p>
        <div class="grid gap-6 xl:grid-cols-[300px_1fr]">
            <aside class="card h-fit p-0">
                <ul class="divide-y divide-line">
                    @foreach ($current->versions as $v)
                        <li><button wire:click="$set('version', {{ $v->id }})" @class(['flex w-full items-center justify-between px-4 py-3 text-left text-sm', 'bg-surface-2' => $version === $v->id])>
                            <span><b>v{{ $v->version }}</b> <span class="text-muted">{{ local_time($v->created_at)->format('j. n. Y') }} · {{ $v->author?->name ?? 'systém' }}</span></span>
                            <span class="{{ $v->status === 'production' ? 'badge-forest' : ($v->status === 'approved' ? 'badge-mustard' : 'badge-outline') }}">{{ \App\Models\AiPromptVersion::STATUSES[$v->status] }}</span>
                        </button></li>
                    @endforeach
                </ul>
                <div class="p-4"><button wire:click="rollback" wire:confirm="Vrátit produkci na předchozí produkční verzi?" class="btn-danger btn-sm w-full">Rollback produkce</button></div>
            </aside>

            <div class="space-y-6">
                <div class="card space-y-3">
                    @if ($selected)
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <b>Verze {{ $selected->version }}</b> · {{ \App\Models\AiPromptVersion::STATUSES[$selected->status] }}
                            @if ($selected->approver) · schválil {{ $selected->approver->name }} {{ local_time($selected->approved_at)?->format('j. n. Y') }}@endif
                            @if ($selected->change_note) · <i>{{ $selected->change_note }}</i>@endif
                            <span class="ml-auto flex flex-wrap gap-1.5">
                                @foreach (['review' => 'Ke kontrole', 'approved' => 'Schválit', 'production' => 'Nasadit do produkce', 'draft' => 'Vrátit do konceptu', 'archived' => 'Archivovat'] as $to => $l)
                                    @if (in_array($to, ['draft' => ['review', 'archived'], 'review' => ['approved', 'draft', 'archived'], 'approved' => ['production', 'archived'], 'production' => [], 'archived' => ['draft']][$selected->status], true))
                                        <button wire:click="changeStatus({{ $selected->id }}, '{{ $to }}')" class="{{ $to === 'production' ? 'btn-forest' : 'btn-outline' }} btn-sm">{{ $l }}</button>
                                    @endif
                                @endforeach
                            </span>
                        </div>
                    @endif
                    <label class="label" for="sys">Systémový prompt</label>
                    <textarea id="sys" wire:model="system" rows="16" class="input font-mono text-[13px]"></textarea>
                    @error('system')<p class="error">{{ $message }}</p>@enderror
                    <label class="label" for="usr">Uživatelská zpráva (data v tazích – nejsou to instrukce)</label>
                    <textarea id="usr" wire:model="userTemplate" rows="5" class="input font-mono text-[13px]"></textarea>
                    <input wire:model="note" class="input" placeholder="Poznámka ke změně" aria-label="Poznámka">
                    <button wire:click="saveDraft" class="btn-forest">Uložit jako novou verzi (koncept)</button>
                    <p class="text-xs text-muted">Běžní uživatelé používají jen verzi ve stavu „Produkce“. Vlastní verzi musí schválit někdo jiný (kromě superadmina).</p>
                    @if ($history->isNotEmpty())
                        <details class="text-sm"><summary class="cursor-pointer font-semibold">Historie stavů</summary>
                            <ul class="mt-2 space-y-1">@foreach ($history as $h)<li>{{ local_time(\Carbon\Carbon::parse($h->created_at))->format('j. n. Y H:i') }} · {{ $h->name ?? 'systém' }}: {{ $h->from_status ?? '–' }} → {{ $h->to_status }} @if ($h->note) ({{ $h->note }})@endif</li>@endforeach</ul>
                        </details>
                    @endif
                </div>

                <div class="card space-y-3">
                    <h2 class="font-display text-lg font-bold">Testování ({{ $current->testCases->count() }} případů)</h2>
                    <ul class="space-y-1 text-sm">@foreach ($current->testCases as $c)<li><b>{{ $c->name }}</b> <code class="text-xs text-muted">{{ json_encode($c->variables, JSON_UNESCAPED_UNICODE) }}</code></li>@endforeach</ul>
                    <div class="flex flex-wrap items-end gap-2">
                        <select wire:model="testModel" class="input w-auto" aria-label="Model pro test"><option value="">Aktivní primární model</option>@foreach ($models as $m)<option value="{{ $m->id }}">{{ $m->name }} ({{ $m->provider->name }})</option>@endforeach</select>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="confirmCost" class="h-4 w-4 accent-forest"> Rozumím, že test se skutečným modelem stojí peníze</label>
                        <button wire:click="runTests" class="btn-accent" wire:loading.attr="disabled">Spustit testy verze</button>
                    </div>
                    @error('confirmCost')<p class="error">{{ $message }}</p>@enderror
                    <details class="text-sm"><summary class="cursor-pointer font-semibold">Přidat testovací případ</summary>
                        <div class="mt-2 space-y-2"><input wire:model="caseName" class="input" placeholder="Název" aria-label="Název případu">
                            <textarea wire:model="caseVars" rows="3" class="input font-mono text-xs" placeholder='{"question": "…", "original_answer": "…", "language_style": "Moravák"}' aria-label="Proměnné"></textarea>
                            @error('caseVars')<p class="error">{{ $message }}</p>@enderror
                            <button wire:click="addCase" class="btn-outline btn-sm">Přidat</button></div>
                    </details>
                    @if ($results->isNotEmpty())
                        <table class="table-admin"><thead><tr><th>Případ</th><th>Výsledek</th><th>Kontroly</th><th>Cena</th></tr></thead><tbody>
                        @foreach ($results as $r)
                            <tr><td>{{ $r->testCase?->name }}<br><span class="text-xs text-muted">{{ local_time($r->created_at)->format('j. n. H:i') }}</span></td>
                                <td>{!! $r->passed ? '<span class="badge-forest">prošel</span>' : '<span class="badge-terra">neprošel</span>' !!}</td>
                                <td class="text-xs">@foreach ($r->checks as $c)<div>{{ $c[1] ? '✓' : '✗' }} {{ $c[0] }} {{ $c[2] ?? '' }}</div>@endforeach</td>
                                <td class="text-xs">{{ cz_number($r->cost_czk, 4) }} Kč</td></tr>
                        @endforeach
                        </tbody></table>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
