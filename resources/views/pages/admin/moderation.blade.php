<?php

use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Moderation\Services\Moderation;
use App\Models\Answer;
use App\Models\Comment;
use App\Models\ModerationAction;
use App\Models\Question;
use App\Models\Report;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::admin')] #[Title('Moderační fronta')] class extends Component {
    #[Url]
    public string $status = 'open';

    /** @var array<string, string> */
    public array $reason = [];

    private function model(string $type, int $id)
    {
        return match ($type) {
            'question' => Question::withTrashed()->findOrFail($id),
            'answer' => Answer::withTrashed()->findOrFail($id),
            'comment' => Comment::withTrashed()->findOrFail($id),
            'need' => \App\Models\Need::withTrashed()->findOrFail($id),
            'need_offer' => \App\Models\NeedOffer::withTrashed()->findOrFail($id),
            default => abort(404),
        };
    }

    public function decide(string $type, int $id, string $to, Moderation $moderation): void
    {
        $this->authorize('moderation.manage');
        $this->resetErrorBag();
        $status = ModerationStatus::from($to);
        $action = match ($status) { ModerationStatus::Approved => 'approve', ModerationStatus::Rejected => 'reject', default => 'hide' };
        $reason = trim($this->reason["$type-$id"] ?? '') ?: null;
        if ($status !== ModerationStatus::Approved && ! $reason) {
            $this->addError("reason.$type-$id", 'Uveď důvod – autor ho uvidí a může se odvolat.');

            return;
        }
        $moderation->transition($this->model($type, $id), $action, $status, auth()->user(), $reason);
    }

    public function with(): array
    {
        $statuses = $this->status === 'open' ? ['pending', 'under_review', 'reported'] : [$this->status];
        $items = collect();
        foreach (['question' => Question::class, 'answer' => Answer::class, 'comment' => Comment::class, 'need' => \App\Models\Need::class, 'need_offer' => \App\Models\NeedOffer::class] as $type => $class) {
            $q = $class::query()->whereIn('moderation_status', $statuses)->latest('updated_at')->limit(50);
            if ($type === 'answer') {
                $q->with(['user', 'question']);
            } elseif (in_array($type, ['comment', 'need', 'need_offer'], true)) {
                $q->with('user');
            } else {
                $q->with('author');
            }
            foreach ($q->get() as $m) {
                $items->push(['type' => $type, 'model' => $m]);
            }
        }
        $keys = $items->map(fn ($i) => [$i['type'], $i['model']->id]);
        $reports = Report::query()->where('status', 'open')->get()->groupBy(fn ($r) => $r->reportable_type.'-'.$r->reportable_id);
        $flags = ModerationAction::query()->where('action', 'auto_flag')->latest('created_at')->get()->unique(fn ($a) => $a->moderatable_type.'-'.$a->moderatable_id)->keyBy(fn ($a) => $a->moderatable_type.'-'.$a->moderatable_id);

        return ['items' => $items->sortByDesc(fn ($i) => $i['model']->updated_at)->values(), 'reports' => $reports, 'flags' => $flags];
    }
}; ?>

<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-2">
        @foreach (['open' => 'Ke zpracování', 'hidden' => 'Skryté', 'rejected' => 'Zamítnuté'] as $k => $l)
            <a href="?status={{ $k }}" @class(['btn btn-sm', 'bg-forest text-cream' => $status === $k, 'text-ink hover:bg-surface-2' => $status !== $k])>{{ $l }}</a>
        @endforeach
        <p class="ml-auto text-sm text-muted">Automatika jen předtřídí, rozhoduje člověk. U skrytí a zamítnutí je důvod povinný.</p>
    </div>
    <div class="card overflow-x-auto p-0">
        <table class="table-admin">
            <thead><tr><th>Obsah</th><th>Typ</th><th>Důvod</th><th>Riziko</th><th>Akce</th></tr></thead>
            <tbody>
            @forelse ($items as $i)
                @php
                    $m = $i['model']; $key = $i['type'].'-'.$m->id;
                    $rep = $reports->get($key, collect()); $flag = $flags->get($key);
                    $risk = $flag?->risk ?? ($rep->isNotEmpty() ? $rep->map(fn ($r) => $r->reason->risk())->sortBy(fn ($r) => ['high' => 0, 'medium' => 1, 'low' => 2][$r])->first() : 'low');
                @endphp
                <tr wire:key="m{{ $key }}">
                    <td class="max-w-md">
                        <p class="font-semibold">„{{ \Illuminate\Support\Str::limit($m->moderationExcerpt(), 160) }}“</p>
                        @if ($i['type'] === 'answer' && $m->is_stylized)<p class="text-xs text-muted">Originál (jen pro moderaci): „{{ $m->original_text }}“</p>@endif
                        <p class="text-xs text-muted">{{ $i['type'] === 'question' ? ($m->author?->name ?? 'redakce') : $m->user?->name }} · {{ local_time($m->created_at)->diffForHumans() }}
                            @if ($i['type'] === 'answer') · <a href="{{ $m->question?->url() }}" target="_blank">otázka</a>@endif</p>
                    </td>
                    <td>{{ ['question' => 'Otázka', 'answer' => 'Odpověď', 'comment' => 'Komentář', 'need' => 'Potřeba', 'need_offer' => 'Nabídka'][$i['type']] }}<br><span class="text-xs text-muted">{{ $m->moderation_status->label() }}</span></td>
                    <td class="text-sm">
                        @if ($rep->isNotEmpty())Nahlášeno {{ $rep->count() }}× ({{ $rep->map(fn ($r) => $r->reason->label())->unique()->implode(', ') }})@endif
                        @if ($flag)<br>{{ $flag->reason }}@endif
                        @foreach ($rep->whereNotNull('note')->take(2) as $r)<br><i class="text-muted">„{{ $r->note }}“</i>@endforeach
                    </td>
                    <td><span class="{{ ['high' => 'badge-terra', 'medium' => 'badge-mustard', 'low' => 'badge-forest'][$risk] ?? 'badge-outline' }}">{{ ['high' => 'vysoké', 'medium' => 'střední', 'low' => 'nízké'][$risk] ?? $risk }}</span></td>
                    <td class="min-w-[220px] space-y-2">
                        <input wire:model="reason.{{ $key }}" class="input py-1.5 text-sm" placeholder="Důvod (pro autora)" aria-label="Důvod">
                        @error("reason.$key")<p class="error">{{ $message }}</p>@enderror
                        <div class="flex flex-wrap gap-1.5">
                            <button wire:click="decide('{{ $i['type'] }}', {{ $m->id }}, 'approved')" class="btn-forest btn-sm">Ponechat</button>
                            <button wire:click="decide('{{ $i['type'] }}', {{ $m->id }}, 'hidden')" class="btn-danger btn-sm">Skrýt</button>
                            @if ($i['type'] === 'question')<button wire:click="decide('question', {{ $m->id }}, 'rejected')" class="btn-danger btn-sm">Zamítnout</button>@endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty title="Fronta je prázdná. Komunita se chová slušně." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
