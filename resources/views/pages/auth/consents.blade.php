<x-layouts::auth title="Ještě jedna věc" subtitle="Aktualizovali jsme podmínky, nebo je vidíš poprvé. Potvrď je prosím.">
    <form method="POST" action="{{ route('consents.store') }}" class="space-y-3 text-sm">
        @csrf
        <label class="flex items-start gap-2"><input type="checkbox" name="terms" value="1" class="mt-0.5 h-4 w-4 accent-forest" required>
            <span>Souhlasím s <a class="link" target="_blank" href="{{ route('legal.show', 'podminky') }}">podmínkami používání</a>@if ($terms) (verze {{ $terms->version }})@endif a pravidly komunity.</span></label>
        @error('terms')<p class="error">{{ $message }}</p>@enderror
        <label class="flex items-start gap-2"><input type="checkbox" name="privacy" value="1" class="mt-0.5 h-4 w-4 accent-forest" required>
            <span>Beru na vědomí <a class="link" target="_blank" href="{{ route('legal.show', 'soukromi') }}">zásady ochrany osobních údajů</a>@if ($privacy) (verze {{ $privacy->version }})@endif.</span></label>
        @error('privacy')<p class="error">{{ $message }}</p>@enderror
        <label class="flex items-start gap-2"><input type="checkbox" name="marketing" value="1" class="mt-0.5 h-4 w-4 accent-forest">
            <span>Chci občas e-mail s novinkami (nepovinné).</span></label>
        <button class="btn-forest mt-2 w-full">Potvrdit a pokračovat</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf<button class="btn-ghost w-full">Odhlásit se</button></form>
</x-layouts::auth>
