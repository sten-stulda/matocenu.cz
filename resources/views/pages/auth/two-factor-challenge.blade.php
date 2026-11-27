<x-layouts::auth title="Dvoufázové ověření">
    <div x-data="{ recovery: false }">
        <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-4">
            @csrf
            <div x-show="!recovery">
                <x-form.field name="code" label="Kód z ověřovací aplikace" inputmode="numeric" autocomplete="one-time-code" autofocus />
            </div>
            <div x-show="recovery" x-cloak>
                <x-form.field name="recovery_code" label="Záložní kód" autocomplete="one-time-code" />
            </div>
            <button class="btn-forest w-full">Ověřit</button>
        </form>
        <button type="button" class="link mt-4 text-sm" @click="recovery = !recovery" x-text="recovery ? 'Použít kód z aplikace' : 'Použít záložní kód'"></button>
    </div>
</x-layouts::auth>
