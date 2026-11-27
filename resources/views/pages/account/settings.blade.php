<?php

use App\Domain\Identity\Actions\UpdateProfile;
use App\Domain\Notifications\NotificationTypes;
use App\Models\Location;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Nastavení')] class extends Component {
    use WithFileUploads;

    public string $name = '';
    public ?int $locationId = null;
    public ?int $favoriteStyleId = null;
    public $avatar = null;

    /** @var array<string, bool> */
    public array $privacy = [];

    /** @var array<string, array<string, bool>> */
    public array $notifications = [];

    public function mount(): void
    {
        $user = auth()->user();
        $prefs = $user->preferences();
        $this->name = $user->name;
        $this->locationId = $user->location_id;
        $this->favoriteStyleId = $user->favorite_style_id;
        $this->privacy = $prefs->only(['anonymous_by_default', 'show_original_by_default', 'humor_on_sensitive', 'show_achievements', 'show_region']);
        foreach (NotificationTypes::all() as $type => $def) {
            foreach ($def['channels'] as $channel) {
                $this->notifications[$type][$channel] = $prefs->wantsNotification($type, $channel, $def['default'][$channel] ?? true);
            }
        }
    }

    #[Computed]
    public function regions()
    {
        return Location::query()->where('type', 'region')->orderBy('name')
            ->with(['children' => fn ($q) => $q->orderBy('name')])->get();
    }

    public function saveProfile(UpdateProfile $action): void
    {
        $action(auth()->user(), [
            'name' => $this->name,
            'location_id' => $this->locationId,
            'favorite_style_id' => $this->favoriteStyleId,
            'avatar' => $this->avatar,
        ]);
        $this->avatar = null;
        $this->dispatch('saved', section: 'profile');
    }

    public function removeAvatar(UpdateProfile $action): void
    {
        $action->removeAvatar(auth()->user());
    }

    public function savePrivacy(): void
    {
        $data = collect($this->privacy)->only(['anonymous_by_default', 'show_original_by_default', 'humor_on_sensitive', 'show_achievements', 'show_region'])->map(fn ($v) => (bool) $v)->all();
        $notifications = [];
        foreach (NotificationTypes::all() as $type => $def) {
            foreach ($def['channels'] as $channel) {
                $notifications[$type][$channel] = (bool) ($this->notifications[$type][$channel] ?? false);
            }
        }
        auth()->user()->preferences()->update($data + ['notifications' => $notifications]);
        $this->dispatch('saved', section: 'privacy');
    }
}; ?>

<div class="mx-auto max-w-3xl space-y-6">
    @include('pages.account.nav', ['active' => 'settings'])

    <form wire:submit="saveProfile" class="card space-y-5">
        <h2 class="font-display text-xl font-bold">Profil</h2>
        <div class="flex flex-wrap items-center gap-4">
            @if ($avatar && method_exists($avatar, 'temporaryUrl'))
                <img src="{{ $avatar->temporaryUrl() }}" alt="" class="h-20 w-20 rounded-full object-cover">
            @else
                <x-avatar :user="auth()->user()" :size="80" />
            @endif
            <div class="space-y-2 text-sm">
                <label class="btn-outline btn-sm cursor-pointer">Nahrát avatar<input type="file" wire:model="avatar" accept="image/png,image/jpeg,image/webp" class="sr-only"></label>
                @if (auth()->user()->avatar_path)<button type="button" wire:click="removeAvatar" class="btn-ghost btn-sm">Odebrat</button>@endif
                <p class="help">JPG, PNG nebo WebP do 2 MB. Ukládáme zmenšený čtverec bez metadat.</p>
                @error('avatar')<p class="error">{{ $message }}</p>@enderror
            </div>
        </div>
        <div>
            <label for="name" class="label">Přezdívka</label>
            <input id="name" wire:model="name" class="input @error('name') input-error @enderror" maxlength="40" autocomplete="nickname">
            @error('name')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="loc" class="label">Lokalita (nepovinné)</label>
            <select id="loc" wire:model="locationId" class="input">
                <option value="">Nechci uvádět</option>
                @foreach ($this->regions as $region)
                    <optgroup label="{{ $region->name }}">
                        <option value="{{ $region->id }}">{{ $region->name }} – celý kraj</option>
                        @foreach ($region->children as $d)
                            <option value="{{ $d->id }}">okres {{ $d->name }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <p class="help">Jen kraj nebo okres – přesnou polohu nikdy nezjišťujeme. Slouží pro „Otázky z okolí“ a krajské žebříčky.</p>
            @error('location_id')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="style" class="label">Oblíbený jazykový styl</label>
            <select id="style" wire:model="favoriteStyleId" class="input">
                <option value="">Bez preference</option>
                @foreach (\App\Models\LanguageStyle::active()->filter(fn ($s) => $s->isGeneratable()) as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
            </select>
            <p class="help">Předvybere se při odpovídání.</p>
        </div>
        <div class="flex items-center gap-3">
            <button class="btn-forest" wire:loading.attr="disabled">Uložit profil</button>
            <span x-data="{ s: false }" x-on:saved.window="if ($event.detail.section === 'profile') { s = true; setTimeout(() => s = false, 2500) }" x-show="s" x-cloak class="text-sm font-semibold text-ok">Uloženo.</span>
        </div>
    </form>

    <form wire:submit="savePrivacy" class="card space-y-5">
        <h2 class="font-display text-xl font-bold">Soukromí a upozornění</h2>
        @php
            $privacyLabels = [
                'anonymous_by_default' => ['Vystupovat anonymně', 'Přezdívka se nezobrazí u nových odpovědí (moderace autora vidí).'],
                'show_original_by_default' => ['Povolit zobrazení originálu', 'Výchozí volba u stylizovaných odpovědí.'],
                'humor_on_sensitive' => ['Humor u citlivých témat', 'Vypnuto = u citlivých otázek vždy jen originál nebo neutrální styl.'],
                'show_achievements' => ['Zobrazovat moje ocenění', 'Tituly a odznaky na veřejném profilu.'],
                'show_region' => ['Zobrazit můj kraj', 'Pro krajské žebříčky a „z mého okolí“.'],
            ];
        @endphp
        <ul class="divide-y divide-line">
            @foreach ($privacyLabels as $key => [$label, $help])
                <li class="flex items-center justify-between gap-4 py-3">
                    <span><span class="block font-semibold">{{ $label }}</span><span class="text-[13px] text-muted">{{ $help }}</span></span>
                    <button type="button" role="switch" class="toggle" aria-label="{{ $label }}"
                            aria-checked="{{ ($privacy[$key] ?? false) ? 'true' : 'false' }}" wire:click="$toggle('privacy.{{ $key }}')"><span></span></button>
                </li>
            @endforeach
        </ul>

        <h3 class="font-display text-lg font-bold">Upozornění</h3>
        <div class="overflow-x-auto">
            <table class="table-admin">
                <thead><tr><th>Typ</th><th class="text-center">Na webu</th><th class="text-center">E-mailem</th></tr></thead>
                <tbody>
                @foreach (\App\Domain\Notifications\NotificationTypes::all() as $type => $def)
                    <tr>
                        <td>{{ $def['label'] }}</td>
                        @foreach (['database', 'mail'] as $ch)
                            <td class="text-center">
                                @if (in_array($ch, $def['channels'], true))
                                    <input type="checkbox" wire:model="notifications.{{ $type }}.{{ $ch }}" class="h-5 w-5 accent-forest" aria-label="{{ $def['label'] }} – {{ $ch === 'mail' ? 'e-mail' : 'web' }}">
                                @else <span class="text-muted">–</span> @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="flex items-center gap-3">
            <button class="btn-forest">Uložit</button>
            <span x-data="{ s: false }" x-on:saved.window="if ($event.detail.section === 'privacy') { s = true; setTimeout(() => s = false, 2500) }" x-show="s" x-cloak class="text-sm font-semibold text-ok">Uloženo.</span>
        </div>
    </form>
</div>
