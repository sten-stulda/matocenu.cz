<x-layouts::auth title="Zapomenuté heslo" subtitle="Pošleme ti odkaz pro nastavení nového hesla.">
    @if (session('status'))<p class="mb-4 rounded bg-forest-100 px-3 py-2 text-sm font-semibold text-forest">{{ session('status') }}</p>@endif
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <x-form.field name="email" label="E-mail" type="email" autocomplete="email" required autofocus />
        <button class="btn-forest w-full">Poslat odkaz</button>
    </form>
    <p class="mt-6 text-center text-sm"><a href="{{ route('login') }}" class="link">Zpět na přihlášení</a></p>
</x-layouts::auth>
