<x-layouts::auth title="Potvrď heslo" subtitle="Pro tuto citlivou akci potřebujeme znovu tvé heslo.">
    <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-4">
        @csrf
        <x-form.field name="password" label="Heslo" type="password" autocomplete="current-password" required autofocus />
        <button class="btn-forest w-full">Potvrdit</button>
    </form>
</x-layouts::auth>
