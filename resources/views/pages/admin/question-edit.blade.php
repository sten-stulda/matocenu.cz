<?php

use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Moderation\Services\Moderation;
use App\Domain\Questions\Actions\ChangePublication;
use App\Domain\Questions\Actions\UpdateQuestion;
use App\Domain\Questions\DTO\QuestionData;
use App\Domain\Questions\Enums\PublicationStatus;
use App\Domain\Questions\Enums\QuestionType;
use App\Domain\Questions\Exceptions\QuestionException;
use App\Domain\Questions\Services\DailyQuestions;
use App\Domain\Questions\Services\QuestionRules;
use App\Models\Category;
use App\Models\Location;
use App\Models\Question;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin')] #[Title('Úprava otázky')] class extends Component {
    #[Locked]
    public int $questionId;

    public string $text = '';
    public ?int $category_id = null;
    public string $type = 'open';
    public ?int $location_id = null;
    public bool $is_sensitive = false;
    public bool $is_anonymous = false;
    public array $options = [];
    public ?string $closes_at = null;
    public string $reason = '';
    public ?string $dailyDate = null;

    public function mount(int $question): void
    {
        $this->questionId = $question;
        $this->fillFrom($this->q());
    }

    private function q(): Question
    {
        return Question::withTrashed()->with(['options', 'versions.editor', 'category', 'author', 'dailyEntries'])->findOrFail($this->questionId);
    }

    private function fillFrom(Question $q): void
    {
        $this->text = $q->text;
        $this->category_id = $q->category_id;
        $this->type = $q->type->value;
        $this->location_id = $q->location_id;
        $this->is_sensitive = $q->is_sensitive;
        $this->is_anonymous = $q->is_anonymous;
        $this->options = $q->options()->pluck('label')->all() ?: ['', ''];
        $this->closes_at = $q->closes_at ? local_time($q->closes_at)->format('Y-m-d\TH:i') : null;
    }

    public function addOption(): void
    {
        if (count($this->options) < 8) {
            $this->options[] = '';
        }
    }

    public function save(UpdateQuestion $update): void
    {
        $rules = QuestionRules::rules(admin: true);
        $rules['closes_at'] = ['nullable', 'date'];
        $data = $this->validate($rules + ['reason' => 'nullable|string|max:300']);
        $update(auth()->user(), $this->q(), QuestionData::fromArray($data + ['options' => $this->options]), $this->reason ?: null);
        $this->reason = '';
        session()->flash('status', 'Uloženo.');
    }

    public function publication(string $status, ChangePublication $change): void
    {
        $change(auth()->user(), $this->q(), PublicationStatus::from($status));
        session()->flash('status', 'Stav publikace změněn.');
    }

    public function moderate(string $to, Moderation $moderation): void
    {
        $this->authorize('moderation.manage');
        $status = ModerationStatus::from($to);
        $moderation->transition($this->q(), match ($status) { ModerationStatus::Approved => 'approve', ModerationStatus::Rejected => 'reject', default => 'hide' }, $status, auth()->user());
    }

    public function planDaily(DailyQuestions $daily): void
    {
        $this->authorize('daily.manage');
        $this->validate(['dailyDate' => 'required|date']);
        try {
            $daily->plan(auth()->user(), $this->q(), CarbonImmutable::parse($this->dailyDate, config('matocenu.display_timezone')), approve: true);
            session()->flash('status', 'Naplánováno jako otázka dne.');
        } catch (QuestionException $e) {
            $this->addError('dailyDate', $e->getMessage());
        }
    }

    public function delete(): void
    {
        $q = $this->q();
        $this->authorize('delete', $q);
        $q->delete();
        app(\App\Domain\Administration\Services\AuditLogger::class)->log('question.deleted', $q);
        $this->redirectRoute('admin.questions');
    }

    public function restore(): void
    {
        $this->authorize('questions.manage');
        $this->q()->restore();
    }

    public function with(): array
    {
        return [
            'question' => $this->q(),
            'categories' => Category::orderBy('sort_order')->get(),
            'regions' => Location::where('type', 'region')->orderBy('name')->with('children')->get(),
        ];
    }
}; ?>

<div class="grid gap-6 xl:grid-cols-[1fr_360px]">
    <form wire:submit="save" class="card space-y-4">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="badge-outline">{{ $question->publication_status->label() }}</span>
            <span class="{{ $question->moderation_status->isPublic() ? 'badge-forest' : 'badge-terra' }}">{{ $question->moderation_status->label() }}</span>
            <span class="text-muted">verze {{ $question->version }} · {{ cz_plural($question->answers_count, 'odpověď', 'odpovědi', 'odpovědí') }} · autor {{ $question->author?->name ?? 'redakce' }}</span>
            @if ($question->isVisible())<a href="{{ $question->url() }}" target="_blank" class="link ml-auto">Zobrazit na webu</a>@endif
        </div>
        <div><label class="label" for="t">Text</label><textarea id="t" wire:model="text" rows="2" class="input text-lg"></textarea>@error('text')<p class="error">{{ $message }}</p>@enderror</div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div><label class="label" for="c">Kategorie</label><select id="c" wire:model="category_id" class="input">@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
            <div><label class="label" for="ty">Typ</label><select id="ty" wire:model.live="type" class="input" @disabled($question->answers_count > 0)>@foreach (QuestionType::cases() as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach</select></div>
            <div><label class="label" for="l">Lokalita</label><select id="l" wire:model="location_id" class="input"><option value="">Celá ČR</option>@foreach ($regions as $r)<optgroup label="{{ $r->name }}"><option value="{{ $r->id }}">{{ $r->name }}</option>@foreach ($r->children as $d)<option value="{{ $d->id }}">okres {{ $d->name }}</option>@endforeach</optgroup>@endforeach</select>@error('location_id')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="ca">Uzavřít pro odpovědi</label><input id="ca" type="datetime-local" wire:model="closes_at" class="input"></div>
        </div>
        @if (in_array($type, ['choice', 'poll'], true))
            <div class="space-y-2"><p class="label">Možnosti</p>@foreach ($options as $i => $o)<input wire:model="options.{{ $i }}" class="input" aria-label="Možnost {{ $i + 1 }}" @disabled($question->answers_count > 0)>@endforeach
                <button type="button" wire:click="addOption" class="btn-ghost btn-sm">+ možnost</button></div>
        @endif
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_sensitive" class="h-4 w-4 accent-forest"> Citlivé téma (humorné stylizace vypnuté)</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_anonymous" class="h-4 w-4 accent-forest"> Zobrazit autora anonymně</label>
        <div><label class="label" for="r">Důvod změny (do historie)</label><input id="r" wire:model="reason" class="input" maxlength="300"></div>
        <button class="btn-forest">Uložit (změna textu = nová verze)</button>
    </form>

    <aside class="space-y-4">
        <div class="card space-y-2">
            <h2 class="font-display text-lg font-bold">Publikace</h2>
            <div class="flex flex-wrap gap-2">
                @foreach (['published' => 'Zveřejnit', 'closed' => 'Uzavřít', 'draft' => 'Stáhnout do konceptu', 'archived' => 'Archivovat'] as $s => $l)
                    @if ($question->publication_status->value !== $s)<button wire:click="publication('{{ $s }}')" class="{{ $s === 'published' ? 'btn-forest' : 'btn-outline' }} btn-sm">{{ $l }}</button>@endif
                @endforeach
            </div>
        </div>
        @can('moderation.manage')
            <div class="card space-y-2">
                <h2 class="font-display text-lg font-bold">Moderace</h2>
                <div class="flex flex-wrap gap-2">
                    <button wire:click="moderate('approved')" class="btn-forest btn-sm">Schválit</button>
                    <button wire:click="moderate('hidden')" class="btn-danger btn-sm">Skrýt</button>
                    <button wire:click="moderate('rejected')" class="btn-danger btn-sm">Zamítnout</button>
                </div>
            </div>
        @endcan
        @can('daily.manage')
            <div class="card space-y-2">
                <h2 class="font-display text-lg font-bold">Otázka dne</h2>
                @foreach ($question->dailyEntries as $d)<p class="text-sm">{{ $d->date->format('j. n. Y') }} – {{ \App\Models\DailyQuestion::STATUSES[$d->status] }}</p>@endforeach
                <div class="flex gap-2"><input type="date" wire:model="dailyDate" class="input" aria-label="Datum"><button wire:click="planDaily" class="btn-accent btn-sm whitespace-nowrap">Naplánovat</button></div>
                @error('dailyDate')<p class="error">{{ $message }}</p>@enderror
            </div>
        @endcan
        @includeIf('pages.admin.partials.question-variants', ['question' => $question])
        <div class="card text-sm">
            <h2 class="font-display text-lg font-bold">Historie verzí</h2>
            <ul class="mt-2 space-y-2">@foreach ($question->versions as $v)<li><span class="text-muted">v{{ $v->version }} · {{ local_time($v->created_at)->format('j. n. Y H:i') }} · {{ $v->editor?->name ?? 'systém' }}</span><br>{{ $v->text }}@if ($v->reason)<br><i class="text-muted">{{ $v->reason }}</i>@endif</li>@endforeach</ul>
        </div>
        <div class="card">
            @if ($question->trashed())<button wire:click="restore" class="btn-outline btn-sm">Obnovit smazanou otázku</button>
            @else<button wire:click="delete" wire:confirm="Smazat otázku? Odpovědi zůstanou v databázi, ale nebudou vidět." class="btn-danger btn-sm">Smazat otázku</button>@endif
        </div>
    </aside>
</div>
