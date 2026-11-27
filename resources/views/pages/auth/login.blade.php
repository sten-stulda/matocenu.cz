<x-layouts::auth title="Přihlášení" subtitle="Vítej zpátky. Otázka dne už čeká.">
    @if (session('status'))<p class="mb-4 rounded bg-forest-100 px-3 py-2 text-sm font-semibold text-forest">{{ session('status') }}</p>@endif
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <x-form.field name="email" label="E-mail" type="email" autocomplete="username" required autofocus />
        <x-form.field name="password" label="Heslo" type="password" autocomplete="current-password" required />
        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="remember" class="h-4 w-4 accent-forest"> Zapamatovat si mě</label>
            <a href="{{ route('password.request') }}" class="link">Zapomenuté heslo</a>
        </div>
        <button class="btn-forest w-full">Přihlásit se</button>
    </form>
    @include('pages.auth.partials.google')
    <p class="mt-6 text-center text-sm">Ještě tu nemáš účet? <a href="{{ route('register') }}" class="link">Zaregistruj se</a></p>
</x-layouts::auth>
