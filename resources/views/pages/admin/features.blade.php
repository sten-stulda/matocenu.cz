<?php

use App\Domain\Administration\Services\FeatureFlags;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin')] #[Title('Feature flags')] class extends Component {
    public function toggle(string $key, FeatureFlags $flags): void
    {
        $this->authorize('features.manage');
        $flags->set($key, ! $flags->enabled($key), auth()->id());
    }

    public function with(FeatureFlags $flags): array
    {
        return ['features' => collect(config('matocenu.features'))->map(fn ($def, $key) => [
            'key' => $key, 'name' => $def[0], 'description' => $def[1], 'ready' => $def[3], 'enabled' => $flags->enabled($key),
        ])];
    }
}; ?>

<div class="card max-w-4xl p-0">
    <ul class="divide-y divide-line">
        @foreach ($features as $f)
            <li class="flex items-center justify-between gap-4 px-5 py-4">
                <div>
                    <p class="font-semibold">{{ $f['name'] }} <code class="text-xs text-muted">{{ $f['key'] }}</code></p>
                    <p class="text-sm text-muted">{{ $f['description'] }}</p>
                    @unless ($f['ready'])<p class="mt-1 badge-dashed">Modul není dokončen – nelze zapnout veřejně</p>@endunless
                </div>
                <button type="button" role="switch" class="toggle" aria-label="{{ $f['name'] }}" aria-checked="{{ $f['enabled'] ? 'true' : 'false' }}"
                        wire:click="toggle('{{ $f['key'] }}')" @disabled(! $f['ready'])><span></span></button>
            </li>
        @endforeach
    </ul>
</div>
