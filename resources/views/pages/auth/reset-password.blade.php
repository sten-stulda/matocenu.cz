<x-layouts::auth title="Nové heslo">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-form.field name="email" label="E-mail" type="email" :value="$request->email" autocomplete="email" required />
        <x-form.field name="password" label="Nové heslo" type="password" autocomplete="new-password" required />
        <x-form.field name="password_confirmation" label="Heslo znovu" type="password" autocomplete="new-password" required />
        <button class="btn-forest w-full">Uložit heslo</button>
    </form>
</x-layouts::auth>
