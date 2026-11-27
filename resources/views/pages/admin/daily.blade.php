<?php

use App\Domain\Questions\Actions\CreateQuestion;
use App\Domain\Questions\DTO\QuestionData;
use App\Domain\Questions\Exceptions\QuestionException;
use App\Domain\Questions\Services\DailyQuestions;
use App\Domain\Questions\Services\QuestionRules;
use App\Models\Category;
use App\Models\DailyQuestion;
use App\Models\Question;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin')] #[Title('Plán otázky dne')] class extends Component {
    public ?string $date = null;
    public ?int $questionId = null;
    public string $search = '';
    public string $newText = '';
    public ?int $newCategory = null;
    public bool $approveNow = true;

    public function select(string $date): void
    {
        $this->date = $date;
        $this->reset('questionId', 'search', 'newText');
    }

    public function plan(DailyQuestions $daily, CreateQuestion $create): void
    {
        $this->authorize('daily.manage');
        $this->validate(['date' => 'required|date']);
        if ($this->newText !== '') {
            $rules = collect(QuestionRules::rules(admin: true))->only(['text', 'category_id'])->all();
            $data = validator(['text' => $this->newText, 'category_id' => $this->newCategory], $rules)->validate();
            $q = $create(auth()->user(), QuestionData::fromArray($data + ['type' => 'daily']), asEditor: true);
        } else {
            $this->validate(['questionId' => 'required|exists:questions,id']);
            $q = Question::findOrFail($this->questionId);
        }
        try {
            $daily->plan(auth()->user(), $q, CarbonImmutable::parse($this->date, config('matocenu.display_timezone')), $this->approveNow);
            $this->reset('date', 'questionId', 'search', 'newText');
            session()->flash('status', 'Naplánováno.');
        } catch (QuestionException $e) {
            $this->addError('date', $e->getMessage());
        }
    }

    public function approve(int $id, DailyQuestions $daily): void
    {
        $this->authorize('daily.manage');
        $daily->approve(auth()->user(), DailyQuestion::findOrFail($id));
    }

    public function remove(int $id, DailyQuestions $daily): void
    {
        $this->authorize('daily.manage');
        try {
            $daily->unplan(auth()->user(), DailyQuestion::findOrFail($id));
        } catch (QuestionException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function publishNow(DailyQuestions $daily): void
    {
        $this->authorize('daily.manage');
        $entry = $daily->publishDue();
        session()->flash($entry ? 'status' : 'error', $entry ? 'Otázka dne zveřejněna.' : 'Na dnešek není schválená otázka, nebo ještě nenastal čas zveřejnění.');
    }

    public function with(DailyQuestions $daily): array
    {
        $today = $daily->localNow()->startOfDay();
        $entries = DailyQuestion::with(['question', 'style'])->whereBetween('date', [$today->subDays(7)->toDateString(), $today->addDays(30)->toDateString()])->get()->keyBy(fn ($e) => $e->date->toDateString());
        $days = collect(range(-7, 30))->map(fn ($i) => $today->addDays($i));
        $like = '%'.addcslashes(trim($this->search), '%_\\').'%';

        return [
            'days' => $days,
            'entries' => $entries,
            'today' => $today,
            'publishAt' => $daily->publishTimeFor($today),
            'candidates' => $this->date ? Question::query()->whereIn('moderation_status', ['approved'])->where('answers_count', 0)
                ->whereDoesntHave('dailyEntries')->when(trim($this->search) !== '', fn ($q) => $q->where('text', 'ilike', $like))
                ->orderByDesc('is_daily_candidate')->latest()->limit(15)->get() : collect(),
            'categories' => Category::orderBy('sort_order')->get(),
        ];
    }
}; ?>

<div class="grid gap-6 xl:grid-cols-[1fr_400px]">
    <section class="card p-0">
        <div class="flex flex-wrap items-center justify-between gap-2 px-5 pt-5">
            <p class="text-sm text-muted">Zveřejnění denně v {{ $publishAt->format('H:i') }} (Europe/Prague). Automaticky vyjdou jen <b>schválené</b> otázky.</p>
            <button wire:click="publishNow" class="btn-outline btn-sm">Zveřejnit dnešní hned</button>
        </div>
        <ul class="mt-3 divide-y divide-line">
            @foreach ($days as $d)
                @php $e = $entries->get($d->toDateString()); $isToday = $d->equalTo($today); @endphp
                <li class="flex flex-wrap items-center gap-3 px-5 py-3 {{ $isToday ? 'bg-mustard-100/50 dark:bg-surface-2' : '' }} {{ $d->lt($today) ? 'opacity-70' : '' }}">
                    <span class="w-24 font-display font-bold">{{ $d->translatedFormat('D j. n.') }}</span>
                    <span class="min-w-0 flex-1">
                        @if ($e)<a href="{{ route('admin.questions.edit', $e->question_id) }}" class="font-semibold">{{ $e->question->text }}</a>@else<span class="text-muted">—</span>@endif
                    </span>
                    @if ($e)
                        @if ($e->style)<span class="badge-outline">{{ $e->style->name }}</span>@endif
                        <span class="{{ ['planned' => 'badge-mustard', 'approved' => 'badge-forest', 'published' => 'badge-outline', 'skipped' => 'badge-terra'][$e->status] }}">{{ \App\Models\DailyQuestion::STATUSES[$e->status] }}</span>
                        @if ($e->status === 'planned')<button wire:click="approve({{ $e->id }})" class="btn-forest btn-sm">Schválit</button>@endif
                        @if ($e->status !== 'published')<button wire:click="remove({{ $e->id }})" wire:confirm="Vyřadit z kalendáře?" class="btn-ghost btn-sm">Vyřadit</button>@endif
                        @if ($e->evaluation)<span class="text-[12px] text-muted">{{ $e->evaluation['answers'] ?? 0 }} odp. · {{ $e->evaluation['reactions'] ?? 0 }} reakcí</span>@endif
                    @elseif ($d->gte($today))
                        <span class="badge-outline">volno</span>
                        <button wire:click="select('{{ $d->toDateString() }}')" class="btn-accent btn-sm">Naplánovat</button>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>

    <aside class="space-y-4">
        @if ($date)
            <form wire:submit="plan" class="card space-y-3">
                <h2 class="font-display text-lg font-bold">Naplánovat na {{ \Carbon\Carbon::parse($date)->translatedFormat('l j. n.') }}</h2>
                <input type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="Hledat schválenou otázku…" aria-label="Hledat otázku">
                <div class="max-h-64 space-y-1 overflow-y-auto">
                    @forelse ($candidates as $c)
                        <label class="flex items-start gap-2 rounded p-2 text-sm hover:bg-surface-2"><input type="radio" wire:model="questionId" value="{{ $c->id }}" class="mt-1 accent-forest"> {{ $c->text }}</label>
                    @empty
                        <p class="text-sm text-muted">Žádná nepoužitá schválená otázka. Napiš novou:</p>
                    @endforelse
                </div>
                <p class="text-sm font-semibold">…nebo nová otázka</p>
                <textarea wire:model="newText" rows="2" class="input" placeholder="Text nové otázky dne" aria-label="Nová otázka"></textarea>
                <select wire:model="newCategory" class="input" aria-label="Kategorie"><option value="">Kategorie…</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                @error('text')<p class="error">{{ $message }}</p>@enderror
                @error('category_id')<p class="error">{{ $message }}</p>@enderror
                @error('questionId')<p class="error">Vyber otázku.</p>@enderror
                @error('date')<p class="error">{{ $message }}</p>@enderror
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="approveNow" class="h-4 w-4 accent-forest"> Rovnou schválit (jinak zůstane jako návrh a sama nevyjde)</label>
                <div class="flex gap-2"><button class="btn-forest">Naplánovat</button><button type="button" wire:click="$set('date', null)" class="btn-ghost">Zrušit</button></div>
            </form>
        @else
            <div class="card text-sm text-muted">Vyber v kalendáři volný den a klikni na <b>Naplánovat</b>.</div>
        @endif
        @includeIf('pages.admin.partials.daily-style-schedule')
    </aside>
</div>
