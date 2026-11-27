<?php

use App\Domain\Questions\Actions\CreateQuestion;
use App\Domain\Questions\DTO\QuestionData;
use App\Domain\Questions\Enums\PublicationStatus;
use App\Domain\Questions\Enums\QuestionType;
use App\Domain\Questions\Services\QuestionRules;
use App\Models\Category;
use App\Models\Question;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] #[Title('Otázky')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = '';

    public bool $creating = false;
    public string $text = '';
    public ?int $category_id = null;
    public string $type = 'open';
    public bool $is_sensitive = false;

    public function updatedSearch(): void { $this->resetPage(); }

    public function create(CreateQuestion $create): void
    {
        $this->authorize('questions.manage');
        $data = $this->validate(collect(QuestionRules::rules(admin: true))->only(['text', 'category_id', 'type', 'is_sensitive'])->all());
        $q = $create(auth()->user(), QuestionData::fromArray($data), asEditor: true);
        $this->redirectRoute('admin.questions.edit', $q->id);
    }

    public function with(): array
    {
        $like = '%'.addcslashes(trim($this->search), '%_\\').'%';

        return [
            'questions' => Question::withTrashed()->with(['category', 'author'])
                ->when(trim($this->search) !== '', fn ($q) => $q->where('text', 'ilike', $like))
                ->when($this->status !== '', fn ($q) => in_array($this->status, array_column(PublicationStatus::cases(), 'value'), true)
                    ? $q->where('publication_status', $this->status) : $q->where('moderation_status', $this->status))
                ->latest('created_at')->paginate(25),
            'categories' => Category::orderBy('sort_order')->get(),
        ];
    }
}; ?>

<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-3">
        <input type="search" wire:model.live.debounce.300ms="search" class="input max-w-xs" placeholder="Hledat v textu…" aria-label="Hledat">
        <select wire:model.live="status" class="input max-w-[220px]" aria-label="Stav">
            <option value="">Všechny stavy</option>
            @foreach (PublicationStatus::cases() as $s)<option value="{{ $s->value }}">{{ $s->label() }}</option>@endforeach
            @foreach (\App\Domain\Moderation\Enums\ModerationStatus::cases() as $s)<option value="{{ $s->value }}">Moderace: {{ $s->label() }}</option>@endforeach
        </select>
        <button wire:click="$toggle('creating')" class="btn-accent ml-auto"><x-icon name="plus" :size="16" /> Nová otázka</button>
    </div>

    @if ($creating)
        <form wire:submit="create" class="card space-y-3">
            <textarea wire:model="text" rows="2" class="input text-lg" placeholder="Text otázky (s otazníkem)" aria-label="Text otázky"></textarea>
            @error('text')<p class="error">{{ $message }}</p>@enderror
            <div class="flex flex-wrap gap-3">
                <select wire:model="category_id" class="input w-auto" aria-label="Kategorie"><option value="">Kategorie…</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                <select wire:model="type" class="input w-auto" aria-label="Typ">@foreach (QuestionType::cases() as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach</select>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_sensitive" class="h-4 w-4 accent-forest"> Citlivé téma</label>
            </div>
            @error('category_id')<p class="error">{{ $message }}</p>@enderror
            <button class="btn-forest">Vytvořit koncept</button>
            <p class="help">Otázka se vytvoří jako koncept. Zveřejníš ji v detailu, nebo ji naplánuješ jako otázku dne.</p>
        </form>
    @endif

    <div class="card overflow-x-auto p-0">
        <table class="table-admin">
            <thead><tr><th>Otázka</th><th>Kategorie</th><th>Autor</th><th>Publikace</th><th>Moderace</th><th class="text-right">Odp.</th><th>Vytvořeno</th></tr></thead>
            <tbody>
            @forelse ($questions as $q)
                <tr wire:key="q{{ $q->id }}" @class(['opacity-60' => $q->trashed()])>
                    <td class="max-w-md"><a href="{{ route('admin.questions.edit', $q->id) }}" class="font-semibold">{{ $q->text }}</a>@if ($q->trashed()) <span class="badge-terra">smazáno</span>@endif</td>
                    <td>{{ $q->category->name }}</td>
                    <td>{{ $q->author?->name ?? '–' }}@if ($q->is_anonymous) <span class="text-xs text-muted">(anonym.)</span>@endif</td>
                    <td><span class="badge-outline">{{ $q->publication_status->label() }}</span></td>
                    <td><span class="{{ $q->moderation_status->isPublic() ? 'badge-forest' : 'badge-terra' }}">{{ $q->moderation_status->label() }}</span></td>
                    <td class="text-right tabular-nums">{{ $q->answers_count }}</td>
                    <td class="whitespace-nowrap">{{ local_time($q->created_at)->format('j. n. Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-muted">Žádné otázky.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $questions->links() }}
</div>
