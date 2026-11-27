<?php

use App\Domain\Administration\Services\AuditLogger;
use App\Models\LanguageStyle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin')] #[Title('Jazykové styly')] class extends Component {
    public ?int $editId = null;
    public array $f = [];
    public string $examples = '';

    public function edit(?int $id = null): void
    {
        $s = $id ? LanguageStyle::findOrFail($id) : new LanguageStyle(['color' => 'mustard', 'is_active' => true, 'is_humorous' => true, 'sensitive_policy' => 'disabled', 'content_types' => ['question', 'answer'], 'examples' => []]);
        $this->editId = $id ?? 0;
        $this->f = ['name' => (string) $s->name, 'description' => (string) $s->description, 'icon' => (string) $s->icon, 'color' => $s->color, 'instructions' => (string) $s->instructions,
            'sensitive_policy' => $s->sensitive_policy, 'is_humorous' => (bool) $s->is_humorous, 'is_active' => (bool) $s->is_active, 'sort_order' => (int) $s->sort_order,
            'question' => in_array('question', (array) $s->content_types, true), 'answer' => in_array('answer', (array) $s->content_types, true)];
        $this->examples = collect($s->examples)->map(fn ($e) => ($e['original'] ?? '').' => '.($e['styled'] ?? ''))->implode("\n");
    }

    public function save(): void
    {
        $this->authorize('styles.manage');
        $data = $this->validate([
            'f.name' => 'required|string|max:60', 'f.description' => 'nullable|string|max:200', 'f.icon' => 'required|string|max:4',
            'f.color' => ['required', Rule::in(array_keys(LanguageStyle::COLORS))], 'f.instructions' => 'nullable|string|max:3000',
            'f.sensitive_policy' => 'required|in:allowed,disabled', 'f.is_humorous' => 'boolean', 'f.is_active' => 'boolean', 'f.sort_order' => 'integer|min:0|max:999',
            'f.question' => 'boolean', 'f.answer' => 'boolean', 'examples' => 'nullable|string|max:5000',
        ])['f'];
        $examples = collect(preg_split('/\R/u', $this->examples))->filter(fn ($l) => str_contains($l, '=>'))
            ->map(fn ($l) => array_combine(['original', 'styled'], array_map('trim', explode('=>', $l, 2))))->values()->all();
        DB::transaction(function () use ($data, $examples) {
            $s = $this->editId ? LanguageStyle::findOrFail($this->editId) : new LanguageStyle;
            $before = $s->only(['instructions', 'examples']);
            $s->fill([
                'name' => $data['name'], 'description' => $data['description'], 'icon' => $data['icon'], 'color' => $data['color'], 'instructions' => $data['instructions'],
                'sensitive_policy' => $data['sensitive_policy'], 'is_humorous' => $data['is_humorous'], 'is_active' => $data['is_active'], 'sort_order' => $data['sort_order'],
                'content_types' => array_values(array_keys(array_filter(['question' => $data['question'], 'answer' => $data['answer']]))), 'examples' => $examples,
            ]);
            if (! $s->exists) {
                $s->slug = Str::slug($data['name']) ?: 'styl-'.Str::random(4);
            }
            // AI-relevant change → new style version (invalidates cached variants by key).
            $changed = ! $s->exists || $before['instructions'] !== $s->instructions || $before['examples'] !== $s->examples;
            if ($s->exists && $changed) {
                $s->version++;
            }
            $s->save();
            if ($changed) {
                $s->versions()->create(['version' => $s->version, 'instructions' => $s->instructions, 'examples' => $s->examples, 'created_by' => auth()->id()]);
            }
            app(AuditLogger::class)->log('style.saved', $s, ['version' => $s->version]);
        });
        $this->editId = null;
    }

    public function with(): array
    {
        return ['styles' => LanguageStyle::orderBy('sort_order')->get()];
    }
}; ?>

<div class="grid gap-6 xl:grid-cols-[1fr_460px]">
    <div class="space-y-3">
        <p class="text-sm text-muted">Nářeční styly jsou humorná stylizace, ne jazykově autentický přepis. Změna instrukcí vytvoří novou verzi stylu – staré varianty v cache se už nepoužijí.</p>
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($styles as $s)
                <button wire:click="edit({{ $s->id }})" class="card flex items-start gap-3 text-left hover:border-primary">
                    <span class="avatar h-11 w-11 shrink-0 rounded-[10px] text-sm {{ $s->badgeClass() }}">{{ $s->icon }}</span>
                    <span class="min-w-0"><b>{{ $s->name }}</b> <span class="text-xs text-muted">v{{ $s->version }}</span>@unless ($s->is_active) <span class="badge-outline">vypnuto</span>@endunless
                        <span class="block text-[13px] text-muted">{{ $s->description }}</span>
                        @if ($ex = collect($s->examples)->last())<span class="mt-1 block text-[13px] italic">„{{ $ex['styled'] }}“</span>@endif
                        <span class="mt-1 block text-[11px] text-muted">{{ $s->sensitive_policy === 'allowed' ? 'i pro citlivá témata' : 'u citlivých témat vypnuto' }}</span></span>
                </button>
            @endforeach
        </div>
        <button wire:click="edit" class="btn-accent btn-sm">+ Nový styl</button>
    </div>
    @if ($editId !== null)
        <form wire:submit="save" class="card h-fit space-y-3">
            <div class="grid grid-cols-[1fr_80px] gap-2"><div><label class="label" for="sn">Název</label><input id="sn" wire:model="f.name" class="input">@error('f.name')<p class="error">{{ $message }}</p>@enderror</div><div><label class="label" for="si">Ikona</label><input id="si" wire:model="f.icon" maxlength="4" class="input"></div></div>
            <div><label class="label" for="sd">Popis</label><input id="sd" wire:model="f.description" class="input"></div>
            <div><label class="label" for="sx">Instrukce pro AI</label><textarea id="sx" wire:model="f.instructions" rows="5" class="input text-sm"></textarea></div>
            <div><label class="label" for="se">Ukázky (jedna na řádek: originál => stylizace)</label><textarea id="se" wire:model="examples" rows="4" class="input text-sm"></textarea></div>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="label" for="sc">Barva</label><select id="sc" wire:model="f.color" class="input">@foreach (array_keys(LanguageStyle::COLORS) as $c)<option>{{ $c }}</option>@endforeach</select></div>
                <div><label class="label" for="so">Pořadí</label><input id="so" type="number" wire:model="f.sort_order" class="input"></div>
            </div>
            <div><label class="label" for="sp">Citlivá témata</label><select id="sp" wire:model="f.sensitive_policy" class="input"><option value="disabled">Vypnout (humor nesmí zesměšňovat)</option><option value="allowed">Povolit (neutrální / citlivý styl)</option></select></div>
            <div class="flex flex-wrap gap-4 text-sm">
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="f.question" class="h-4 w-4 accent-forest"> Otázky</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="f.answer" class="h-4 w-4 accent-forest"> Odpovědi</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="f.is_humorous" class="h-4 w-4 accent-forest"> Humorný</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="f.is_active" class="h-4 w-4 accent-forest"> Aktivní</label>
            </div>
            <div class="flex gap-2"><button class="btn-forest">Uložit</button><button type="button" wire:click="$set('editId', null)" class="btn-ghost">Zrušit</button></div>
        </form>
    @endif
</div>
