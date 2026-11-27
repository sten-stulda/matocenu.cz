<?php

use App\Domain\Questions\Actions\CreateQuestion;
use App\Domain\Questions\DTO\QuestionData;
use App\Domain\Questions\Enums\QuestionType;
use App\Domain\Questions\Services\QuestionRules;
use App\Domain\Questions\Services\SimilarQuestions;
use App\Models\Category;
use App\Models\Location;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Položit otázku')] class extends Component {
    public string $text = '';
    public ?int $category_id = null;
    public string $type = 'open';
    public ?int $location_id = null;
    public bool $is_sensitive = false;
    public bool $is_anonymous = false;
    public ?string $closes_at = null;
    /** @var list<string> */
    public array $options = ['', ''];

    #[Computed]
    public function similar()
    {
        return app(SimilarQuestions::class)->find($this->text);
    }

    #[Computed]
    public function categories()
    {
        return Category::where('is_active', true)->orderBy('sort_order')->get();
    }

    #[Computed]
    public function regions()
    {
        return Location::where('type', 'region')->orderBy('name')->with(['children' => fn ($q) => $q->orderBy('name')])->get();
    }

    public function addOption(): void
    {
        if (count($this->options) < 8) {
            $this->options[] = '';
        }
    }

    public function save(CreateQuestion $create): void
    {
        $this->authorize('create', \App\Models\Question::class);
        if (RateLimiter::tooManyAttempts('ask:'.auth()->id(), 5)) {
            $this->addError('text', 'Moc otázek za hodinu. Zkus to později.');

            return;
        }
        $data = $this->validate(QuestionRules::rules());
        RateLimiter::hit('ask:'.auth()->id(), 3600);
        $q = $create(auth()->user(), QuestionData::fromArray($data + ['options' => $this->options]));
        session()->flash('status', $q->isVisible() ? 'Otázka je venku! Teď počkej na odpovědi.' : 'Díky! Otázka čeká na schválení moderátorem – dáme ti vědět.');
        $this->redirect($q->url());
    }
}; ?>

<div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[1fr_300px]">
    <form wire:submit="save" class="card space-y-5">
        <h1 class="font-display text-3xl font-extrabold">Polož otázku</h1>
        <div>
            <label for="text" class="label">Otázka</label>
            <textarea id="text" wire:model.live.debounce.700ms="text" rows="2" maxlength="220" class="input text-lg" placeholder="Co by tě zajímalo od ostatních?"></textarea>
            <p class="help">Krátce a jasně, 10–200 znaků, s otazníkem. Žádné osobní údaje.</p>
            @error('text')<p class="error">{{ $message }}</p>@enderror
        </div>
        @if ($this->similar->isNotEmpty())
            <div class="rounded-md border border-mustard bg-mustard-100/60 p-3 text-sm dark:bg-surface-2">
                <p class="font-semibold">Podobné otázky už existují – nechceš odpovědět tam?</p>
                <ul class="mt-1 list-disc pl-5">@foreach ($this->similar as $s)<li><a href="{{ $s->url() }}" class="link" target="_blank">{{ $s->text }}</a></li>@endforeach</ul>
                <p class="mt-1 text-muted">Nic se automaticky neslučuje – klidně pokračuj.</p>
            </div>
        @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="cat" class="label">Kategorie</label>
                <select id="cat" wire:model="category_id" class="input"><option value="">Vyber…</option>@foreach ($this->categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                @error('category_id')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="type" class="label">Typ</label>
                <select id="type" wire:model.live="type" class="input">
                    @foreach (QuestionType::cases() as $t)@if ($t !== QuestionType::Daily)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endif @endforeach
                </select>
            </div>
        </div>
        @if (in_array($type, ['choice', 'poll'], true))
            <div class="space-y-2">
                <p class="label">Možnosti</p>
                @foreach ($options as $i => $o)<input wire:model="options.{{ $i }}" class="input" maxlength="100" aria-label="Možnost {{ $i + 1 }}">@endforeach
                <button type="button" wire:click="addOption" class="btn-ghost btn-sm">+ další možnost</button>
                @error('options')<p class="error">{{ $message }}</p>@enderror
            </div>
        @endif
        <div>
            <label for="loc" class="label">Lokalita {{ $type === 'local' ? '' : '(nepovinné)' }}</label>
            <select id="loc" wire:model="location_id" class="input">
                <option value="">Celá ČR</option>
                @foreach ($this->regions as $r)<optgroup label="{{ $r->name }}"><option value="{{ $r->id }}">{{ $r->name }}</option>@foreach ($r->children as $d)<option value="{{ $d->id }}">okres {{ $d->name }}</option>@endforeach</optgroup>@endforeach
            </select>
            @error('location_id')<p class="error">{{ $message }}</p>@enderror
        </div>
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" wire:model="is_sensitive" class="mt-0.5 h-4 w-4 accent-forest"><span><b>Citlivé téma</b> (zdraví, dluhy, úmrtí, násilí…) – humorné styly se vypnou.</span></label>
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" wire:model="is_anonymous" class="mt-0.5 h-4 w-4 accent-forest"><span>Položit anonymně</span></label>
        <button class="btn-forest" wire:loading.attr="disabled">Položit otázku</button>
    </form>
    <aside class="card h-fit text-sm">
        <h2 class="font-display text-lg font-bold">Dobrá otázka</h2>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            <li>má odpověď do 100 znaků,</li>
            <li>zajímá víc lidí než jen tebe,</li>
            <li>nikoho nezesměšňuje,</li>
            <li>neobsahuje jména, telefony ani adresy.</li>
        </ul>
        @if (setting('questions.user_questions_need_approval'))<p class="mt-3 text-muted">Nové otázky před zveřejněním zkontroluje moderátor.</p>@endif
    </aside>
</div>
