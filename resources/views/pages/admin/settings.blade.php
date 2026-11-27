<?php

use App\Domain\Administration\Services\Settings;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin')] #[Title('Systémová nastavení')] class extends Component {
    /** @var array<string, mixed> */
    public array $values = [];

    public function mount(Settings $settings): void
    {
        foreach ($this->editable() as $key => [$default, $type]) {
            $v = $settings->get($key);
            $this->values[$this->field($key)] = $type === 'list' ? implode("\n", (array) $v) : $v;
        }
    }

    /** Settings with their own UI (type json) are edited elsewhere. */
    private function editable(): array
    {
        return array_filter(config('matocenu.settings'), fn ($d) => $d[1] !== 'json');
    }

    private function field(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    public function save(Settings $settings): void
    {
        $this->authorize('settings.manage');
        $rules = [];
        foreach ($this->editable() as $key => [$default, $type]) {
            $rules['values.'.$this->field($key)] = match ($type) {
                'int' => ['required', 'integer', 'min:0', 'max:100000'],
                'float' => ['required', 'numeric', 'min:0', 'max:1000'],
                'decimal' => ['required', 'numeric', 'min:0', 'max:10000000', 'decimal:0,4'],
                'bool' => ['boolean'],
                'list' => ['nullable', 'string', 'max:20000'],
                default => ['required', 'string', 'max:500'],
            };
        }
        $this->validate($rules + ['values.answers__max_length' => ['required', 'integer', 'min:10', 'max:500']]);

        foreach ($this->editable() as $key => [$default, $type]) {
            $raw = $this->values[$this->field($key)] ?? null;
            $value = match ($type) {
                'int' => (int) $raw,
                'float' => (float) $raw,
                'decimal' => number_format((float) $raw, 2, '.', ''),
                'bool' => (bool) $raw,
                'list' => array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $raw)))),
                default => trim((string) $raw),
            };
            if ($value !== $settings->get($key)) {
                $settings->set($key, $value, auth()->id());
            }
        }
        session()->flash('status', 'Nastavení uloženo.');
    }

    public function with(): array
    {
        return ['groups' => collect($this->editable())->map(fn ($d, $k) => ['key' => $k, 'type' => $d[1], 'label' => $d[2], 'group' => $d[3]])->groupBy('group')];
    }
}; ?>

<form wire:submit="save" class="max-w-4xl space-y-6">
    @foreach ($groups as $group => $items)
        <section class="card space-y-4">
            <h2 class="font-display text-xl font-bold">{{ $group }}</h2>
            @foreach ($items as $s)
                @php $f = 'values.'.str_replace('.', '__', $s['key']); @endphp
                <div>
                    @if ($s['type'] === 'bool')
                        <label class="flex items-center gap-2 font-semibold"><input type="checkbox" wire:model="{{ $f }}" class="h-4 w-4 accent-forest"> {{ $s['label'] }}</label>
                    @else
                        <label class="label" for="{{ $f }}">{{ $s['label'] }}</label>
                        @if ($s['type'] === 'list')
                            <textarea id="{{ $f }}" wire:model="{{ $f }}" rows="4" class="input"></textarea>
                        @else
                            <input id="{{ $f }}" wire:model="{{ $f }}" class="input" @if (in_array($s['type'], ['int', 'float', 'decimal'])) inputmode="decimal" @endif>
                        @endif
                    @endif
                    <p class="text-xs text-muted">{{ $s['key'] }}</p>
                    @error($f)<p class="error">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </section>
    @endforeach
    <div class="sticky bottom-0 bg-[#EFE9DA]/90 py-3 dark:bg-bg/90"><button class="btn-forest">Uložit nastavení</button></div>
</form>
