<x-layouts::auth title="Registrace" subtitle="Přezdívka stačí. E-mail nikdy nezveřejníme.">
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <x-form.field name="name" label="Přezdívka" autocomplete="nickname" required maxlength="40" help="3–40 znaků. Uvidí ji ostatní (pokud neodpovíš anonymně)." />
        <x-form.field name="email" label="E-mail" type="email" autocomplete="email" required />
        <x-form.field name="password" label="Heslo" type="password" autocomplete="new-password" required help="Alespoň 10 znaků, písmena i číslice." />
        <x-form.field name="password_confirmation" label="Heslo znovu" type="password" autocomplete="new-password" required />
        {{-- Honeypot against simple bots --}}
        <div class="hidden" aria-hidden="true"><label>Web <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="space-y-2 text-sm">
            <label class="flex items-start gap-2">
                <input type="checkbox" name="terms" value="1" class="mt-0.5 h-4 w-4 accent-forest" @checked(old('terms')) required>
                <span>Souhlasím s <a href="{{ route('legal.show', 'podminky') }}" class="link" target="_blank">podmínkami používání</a> a <a href="{{ route('legal.show', 'pravidla') }}" class="link" target="_blank">pravidly komunity</a>.</span>
            </label>
            @error('terms')<p class="error">{{ $message }}</p>@enderror
            <label class="flex items-start gap-2">
                <input type="checkbox" name="privacy" value="1" class="mt-0.5 h-4 w-4 accent-forest" @checked(old('privacy')) required>
                <span>Beru na vědomí <a href="{{ route('legal.show', 'soukromi') }}" class="link" target="_blank">zásady ochrany osobních údajů</a>.</span>
            </label>
            @error('privacy')<p class="error">{{ $message }}</p>@enderror
            <label class="flex items-start gap-2">
                <input type="checkbox" name="marketing" value="1" class="mt-0.5 h-4 w-4 accent-forest" @checked(old('marketing'))>
                <span>Chci občas dostat e-mail s novinkami (nepovinné, kdykoliv vypnu).</span>
            </label>
        </div>
        <button class="btn-forest w-full">Vytvořit účet</button>
    </form>
    @include('pages.auth.partials.google')
    <p class="mt-6 text-center text-sm">Už účet máš? <a href="{{ route('login') }}" class="link">Přihlas se</a></p>
</x-layouts::auth>
