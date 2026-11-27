<x-layouts::auth title="Ověř svůj e-mail" subtitle="Poslali jsme ti odkaz. Po kliknutí můžeš odpovídat a reagovat.">
    @if (session('status') === 'verification-link-sent')
        <p class="mb-4 rounded bg-forest-100 px-3 py-2 text-sm font-semibold text-forest">Nový ověřovací odkaz je na cestě.</p>
    @endif
    <form method="POST" action="{{ route('verification.send') }}">@csrf<button class="btn-forest w-full">Poslat odkaz znovu</button></form>
    <form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf<button class="btn-ghost w-full">Odhlásit se</button></form>
</x-layouts::auth>
