<?php

use App\Domain\Administration\Services\AuditLogger;
use App\Models\LegalDocument;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin')] #[Title('Právní dokumenty')] class extends Component {
    public string $type = 'terms';
    public string $title = '';
    public string $body = '';
    public bool $draft = true;
    public bool $publishNow = false;

    public function mount(): void
    {
        $this->load();
    }

    public function updatedType(): void
    {
        $this->load();
    }

    private function load(): void
    {
        abort_unless(array_key_exists($this->type, LegalDocument::TYPES), 404);
        $doc = LegalDocument::where('type', $this->type)->orderByDesc('version')->first();
        $this->title = $doc?->title ?? LegalDocument::TYPES[$this->type][0];
        $this->body = $doc?->body ?? '';
        $this->draft = $doc?->is_draft_for_review ?? true;
    }

    /** Every save creates a new version; terms/privacy versions require fresh consent from users. */
    public function saveVersion(): void
    {
        $this->authorize('legal.manage');
        $this->validate(['title' => 'required|string|max:160', 'body' => 'required|string|max:100000', 'type' => 'in:'.implode(',', array_keys(LegalDocument::TYPES))]);
        $next = (int) LegalDocument::where('type', $this->type)->max('version') + 1;
        $doc = LegalDocument::create([
            'type' => $this->type, 'version' => $next, 'title' => $this->title, 'body' => $this->body,
            'requires_consent' => in_array($this->type, ['terms', 'privacy'], true),
            'is_draft_for_review' => $this->draft,
            'published_at' => $this->publishNow ? now() : null,
            'created_by' => auth()->id(),
        ]);
        app(AuditLogger::class)->log('legal.document_version', $doc, ['type' => $this->type, 'version' => $next, 'published' => $this->publishNow]);
        session()->flash('status', "Uložena verze $next".($this->publishNow ? ' a zveřejněna.' : ' (nezveřejněná).'));
    }

    public function publish(int $id): void
    {
        $this->authorize('legal.manage');
        $doc = LegalDocument::findOrFail($id);
        $doc->update(['published_at' => now()]);
        app(AuditLogger::class)->log('legal.document_published', $doc);
    }

    public function with(): array
    {
        return ['versions' => LegalDocument::where('type', $this->type)->orderByDesc('version')->get()];
    }
}; ?>

<div class="grid gap-6 xl:grid-cols-[1fr_320px]">
    <form wire:submit="saveVersion" class="card space-y-4">
        <select wire:model.live="type" class="input max-w-sm" aria-label="Dokument">
            @foreach (LegalDocument::TYPES as $k => [$l])<option value="{{ $k }}">{{ $l }}</option>@endforeach
        </select>
        <div><label class="label" for="t">Název</label><input id="t" wire:model="title" class="input">@error('title')<p class="error">{{ $message }}</p>@enderror</div>
        <div><label class="label" for="b">Text (Markdown)</label><textarea id="b" wire:model="body" rows="22" class="input font-mono text-sm"></textarea>@error('body')<p class="error">{{ $message }}</p>@enderror</div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="draft" class="h-4 w-4 accent-forest"> Označit jako pracovní návrh k odborné kontrole</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="publishNow" class="h-4 w-4 accent-forest"> Zveřejnit ihned @if (in_array($type, ['terms', 'privacy']))<span class="text-terra-ink">(uživatelé budou muset znovu souhlasit)</span>@endif</label>
        <button class="btn-forest">Uložit jako novou verzi</button>
    </form>
    <aside class="card h-fit">
        <h2 class="font-display text-lg font-bold">Verze</h2>
        <ul class="mt-3 space-y-2 text-sm">
            @foreach ($versions as $v)
                <li class="flex items-center justify-between gap-2">
                    <span>v{{ $v->version }} · {{ local_time($v->created_at)->format('j. n. Y') }} @if ($v->is_draft_for_review)<span class="badge-terra">návrh</span>@endif</span>
                    @if ($v->published_at)<span class="badge-forest">zveřejněno</span>@else<button wire:click="publish({{ $v->id }})" class="btn-outline btn-sm">Zveřejnit</button>@endif
                </li>
            @endforeach
        </ul>
        <a href="{{ route('legal.show', LegalDocument::TYPES[$type][1]) }}" target="_blank" class="link mt-4 inline-block text-sm">Zobrazit na webu</a>
    </aside>
</div>
