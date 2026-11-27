<?php

use App\Domain\Administration\Services\Settings;
use App\Models\LanguageStyle;
use Livewire\Component;

new class extends Component {
    /** @var array<string, string> */
    public array $map = [];

    public function mount(Settings $settings): void
    {
        $this->map = array_map('strval', (array) $settings->get('daily.style_schedule'));
    }

    public function save(Settings $settings): void
    {
        $this->authorize('daily.manage');
        $valid = LanguageStyle::where('is_active', true)->pluck('slug')->all();
        $clean = [];
        foreach (range(1, 7) as $d) {
            $v = $this->map[(string) $d] ?? '';
            $clean[(string) $d] = in_array($v, $valid, true) ? $v : '';
        }
        $settings->set('daily.style_schedule', $clean, auth()->id());
        session()->flash('status', 'Týdenní rozvrh osobností uložen (platí pro nově plánované dny).');
    }

    public function with(): array
    {
        return ['styles' => LanguageStyle::where('is_active', true)->where('is_original', false)->where('is_random', false)->orderBy('sort_order')->get()];
    }
}; ?>

<form wire:submit="save" class="card space-y-2">
    <h2 class="font-display text-lg font-bold">Jazyková osobnost dne</h2>
    @foreach (['1' => 'Pondělí', '2' => 'Úterý', '3' => 'Středa', '4' => 'Čtvrtek', '5' => 'Pátek', '6' => 'Sobota', '7' => 'Neděle'] as $d => $label)
        <div class="flex items-center gap-2 text-sm"><span class="w-20">{{ $label }}</span>
            <select wire:model="map.{{ $d }}" class="input py-1.5" aria-label="{{ $label }}"><option value="">bez osobnosti</option>@foreach ($styles as $s)<option value="{{ $s->slug }}">{{ $s->name }}</option>@endforeach</select></div>
    @endforeach
    <button class="btn-forest btn-sm">Uložit rozvrh</button>
    <p class="text-xs text-muted">Otázka dne zobrazí variantu v osobnosti dne, pokud ji redakce vygenerovala a schválila v detailu otázky.</p>
</form>
