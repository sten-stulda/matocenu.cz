<?php

use App\Domain\Needs\Actions\NeedActions;
use App\Domain\Needs\Enums\NeedType;
use App\Domain\Needs\Exceptions\NeedException;
use App\Models\Category;
use App\Models\Location;
use App\Models\Need;
use App\Support\Text;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Přidat potřebu')] class extends Component {
    public string $title = '';
    public string $description = '';
    public ?int $category_id = null;
    public string $type = 'missing_service';
    public ?int $location_id = null;
    public string $urgency = 'medium';
    public bool $is_anonymous = false;

    #[Computed]
    public function similar()
    {
        $t = Text::clean($this->title);
        if (Text::length($t) < 6) {
            return collect();
        }

        return Need::query()->visible()->whereRaw('title % ?', [$t])->orderByRaw('similarity(title, ?) desc', [$t])->limit(5)->get();
    }

    public function save(NeedActions $actions): void
    {
        if (RateLimiter::tooManyAttempts('need:'.auth()->id(), 5)) {
            $this->addError('title', 'Moc potřeb za hodinu. Zkus to později.');

            return;
        }
        $data = $this->validate(NeedActions::rules());
        RateLimiter::hit('need:'.auth()->id(), 3600);
        try {
            $need = $actions->create(auth()->user(), $data);
            session()->flash('status', $need->isPubliclyVisible() ? 'Potřeba je zveřejněná. Teď se můžou přidat další.' : 'Potřeba čeká na kontrolu moderátora.');
            $this->redirect($need->url());
        } catch (NeedException $e) {
            $this->addError('title', $e->getMessage());
        }
    }

    public function with(): array
    {
        return [
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->get(),
            'regions' => Location::where('type', 'region')->orderBy('name')->with(['children' => fn ($q) => $q->orderBy('name')])->get(),
        ];
    }
}; ?>

<div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[1fr_300px]">
    <form wire:submit="save" class="card space-y-4">
        <h1 class="font-display text-3xl font-extrabold">Co ti chybí?</h1>
        <div><label class="label" for="t">Stručně</label><input id="t" wire:model.live.debounce.700ms="title" maxlength="140" class="input text-lg" placeholder="např. Spolehlivý řemeslník do 14 dnů">@error('title')<p class="error">{{ $message }}</p>@enderror</div>
        @if ($this->similar->isNotEmpty())
            <div class="rounded-md border border-mustard bg-mustard-100/60 p-3 text-sm dark:bg-surface-2"><p class="font-semibold">Podobné potřeby už tu jsou – možná se stačí přidat:</p>
                <ul class="mt-1 list-disc pl-5">@foreach ($this->similar as $s)<li><a href="{{ $s->url() }}" class="link" target="_blank">{{ $s->title }}</a> ({{ $s->interested_count }})</li>@endforeach</ul></div>
        @endif
        <div><label class="label" for="d">Popis</label><textarea id="d" wire:model="description" rows="4" maxlength="1500" class="input" placeholder="Co přesně chybí, proč, jak by mělo řešení vypadat…"></textarea>@error('description')<p class="error">{{ $message }}</p>@enderror</div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div><label class="label" for="ty">Typ</label><select id="ty" wire:model="type" class="input">@foreach (NeedType::cases() as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach</select></div>
            <div><label class="label" for="c">Kategorie</label><select id="c" wire:model="category_id" class="input"><option value="">Vyber…</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>@error('category_id')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="l">Kde</label><select id="l" wire:model="location_id" class="input"><option value="">Celá ČR</option>@foreach ($regions as $r)<optgroup label="{{ $r->name }}"><option value="{{ $r->id }}">{{ $r->name }}</option>@foreach ($r->children as $d)<option value="{{ $d->id }}">okres {{ $d->name }}</option>@endforeach</optgroup>@endforeach</select></div>
            <div><label class="label" for="u">Naléhavost</label><select id="u" wire:model="urgency" class="input">@foreach (Need::URGENCY as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_anonymous" class="h-4 w-4 accent-forest"> Zveřejnit anonymně</label>
        <button class="btn-forest" wire:loading.attr="disabled">Zveřejnit potřebu</button>
    </form>
    <aside class="card h-fit text-sm"><h2 class="font-display text-lg font-bold">Jak to funguje</h2>
        <ul class="mt-2 list-disc space-y-1 pl-5"><li>Ostatní se přidají a mohou uvést, kolik by zaplatili (nezávazně).</li><li>Realizátoři mohou nabídnout řešení.</li><li>Kontakty se nikdy neprozrazují – vše jde přes platformu a jen se souhlasem.</li><li>Humor tu je vypnutý.</li></ul></aside>
</div>
