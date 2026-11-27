<?php

use App\Domain\Administration\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Zabezpečení')] class extends Component {
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $code = '';
    public bool $showRecovery = false;

    public function changePassword(): void
    {
        $user = auth()->user();
        $this->validate([
            'current_password' => $user->password ? ['required', 'current_password'] : ['nullable'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $user->forceFill(['password' => $this->password])->save();
        // Other devices lose their sessions after a password change.
        DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', session()->getId())->delete();
        app(AuditLogger::class)->log('account.password_changed', $user);
        $this->reset('current_password', 'password', 'password_confirmation');
        session()->flash('status', 'Heslo bylo změněno. Ostatní zařízení jsme odhlásili.');
        $this->redirectRoute('account.security');
    }

    public function enable2fa(EnableTwoFactorAuthentication $enable): void
    {
        $enable(auth()->user());
    }

    public function confirm2fa(ConfirmTwoFactorAuthentication $confirm): void
    {
        $this->validate(['code' => 'required|digits:6']);
        $confirm(auth()->user(), $this->code);
        $this->code = '';
        $this->showRecovery = true;
        app(AuditLogger::class)->log('account.2fa_enabled', auth()->user());
    }

    public function disable2fa(DisableTwoFactorAuthentication $disable): void
    {
        $disable(auth()->user());
        app(AuditLogger::class)->log('account.2fa_disabled', auth()->user());
    }

    public function regenerateCodes(GenerateNewRecoveryCodes $generate): void
    {
        $generate(auth()->user());
        $this->showRecovery = true;
    }

    public function logoutSession(string $id): void
    {
        DB::table('sessions')->where('user_id', auth()->id())->where('id', $id)->where('id', '!=', session()->getId())->delete();
    }

    public function logoutOthers(): void
    {
        DB::table('sessions')->where('user_id', auth()->id())->where('id', '!=', session()->getId())->delete();
        app(AuditLogger::class)->log('account.sessions_revoked', auth()->user());
    }

    #[Computed]
    public function sessions()
    {
        return DB::table('sessions')->where('user_id', auth()->id())->orderByDesc('last_activity')->get()
            ->map(fn ($s) => (object) [
                'id' => $s->id,
                'current' => $s->id === session()->getId(),
                'agent' => $this->describeAgent((string) $s->user_agent),
                // Only a coarse IP prefix is shown.
                'ip' => $s->ip_address ? preg_replace('/(\d+\.\d+)\.\d+\.\d+/', '$1.*.*', $s->ip_address) : '–',
                'last' => \Carbon\Carbon::createFromTimestamp($s->last_activity),
            ]);
    }

    private function describeAgent(string $ua): string
    {
        $browser = collect(['Edg' => 'Edge', 'Firefox' => 'Firefox', 'Chrome' => 'Chrome', 'Safari' => 'Safari'])->first(fn ($v, $k) => str_contains($ua, $k)) ?? 'Prohlížeč';
        $os = collect(['Android' => 'Android', 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Windows' => 'Windows', 'Mac OS' => 'macOS', 'Linux' => 'Linux'])->first(fn ($v, $k) => str_contains($ua, $k)) ?? 'neznámý systém';

        return "$browser · $os";
    }
}; ?>

<div class="mx-auto max-w-3xl space-y-6">
    @include('pages.account.nav', ['active' => 'security'])

    <form wire:submit="changePassword" class="card space-y-4">
        <h2 class="font-display text-xl font-bold">Heslo</h2>
        @if (auth()->user()->password)
            <div><label class="label" for="cp">Současné heslo</label><input id="cp" type="password" wire:model="current_password" class="input" autocomplete="current-password">@error('current_password')<p class="error">{{ $message }}</p>@enderror</div>
        @else
            <p class="text-sm text-muted">Přihlašuješ se přes Google. Můžeš si nastavit i heslo.</p>
        @endif
        <div><label class="label" for="np">Nové heslo</label><input id="np" type="password" wire:model="password" class="input" autocomplete="new-password">@error('password')<p class="error">{{ $message }}</p>@enderror</div>
        <div><label class="label" for="npc">Nové heslo znovu</label><input id="npc" type="password" wire:model="password_confirmation" class="input" autocomplete="new-password"></div>
        <button class="btn-forest">Změnit heslo</button>
    </form>

    <section class="card space-y-4">
        <h2 class="font-display text-xl font-bold">Dvoufázové ověření</h2>
        @php $u = auth()->user(); @endphp
        @if ($u->two_factor_confirmed_at)
            <p class="text-sm"><span class="badge-forest">Zapnuto</span> Při přihlášení zadáš i kód z aplikace.</p>
            @if ($showRecovery)
                <div class="rounded-md bg-surface-2 p-4">
                    <p class="text-sm font-semibold">Záložní kódy – ulož si je na bezpečné místo:</p>
                    <ul class="mt-2 grid grid-cols-2 gap-1 font-mono text-sm">@foreach ($u->recoveryCodes() as $c)<li>{{ $c }}</li>@endforeach</ul>
                </div>
            @endif
            <div class="flex flex-wrap gap-2">
                <button wire:click="regenerateCodes" class="btn-outline btn-sm">Nové záložní kódy</button>
                <button wire:click="disable2fa" wire:confirm="Opravdu vypnout dvoufázové ověření?" class="btn-danger btn-sm">Vypnout</button>
            </div>
        @elseif ($u->two_factor_secret)
            <p class="text-sm">Naskenuj kód v aplikaci (Google Authenticator, Aegis, 1Password…) a zadej šestimístný kód.</p>
            <div class="inline-block rounded-md bg-white p-3">{!! $u->twoFactorQrCodeSvg() !!}</div>
            <p class="text-xs text-muted">Ruční klíč: <code class="font-mono">{{ decrypt($u->two_factor_secret) }}</code></p>
            <form wire:submit="confirm2fa" class="flex flex-wrap items-end gap-2">
                <div><label for="code" class="label">Kód</label><input id="code" wire:model="code" inputmode="numeric" autocomplete="one-time-code" class="input w-40"></div>
                <button class="btn-forest">Potvrdit</button>
            </form>
            @error('code')<p class="error">{{ $message }}</p>@enderror
        @else
            <p class="text-sm text-muted">Doporučujeme hlavně moderátorům a autorům, kteří chtějí účet chránit navíc.</p>
            <button wire:click="enable2fa" class="btn-outline">Zapnout dvoufázové ověření</button>
        @endif
    </section>

    <section class="card space-y-3">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-display text-xl font-bold">Aktivní relace</h2>
            @if ($this->sessions->count() > 1)<button wire:click="logoutOthers" class="btn-outline btn-sm">Odhlásit ostatní zařízení</button>@endif
        </div>
        <ul class="divide-y divide-line">
            @foreach ($this->sessions as $s)
                <li class="flex items-center justify-between gap-3 py-3 text-sm">
                    <span><b>{{ $s->agent }}</b> <span class="text-muted">· {{ $s->ip }} · {{ local_time($s->last)->diffForHumans() }}</span></span>
                    @if ($s->current)<span class="badge-forest">Toto zařízení</span>@else<button wire:click="logoutSession('{{ $s->id }}')" class="btn-ghost btn-sm">Odhlásit</button>@endif
                </li>
            @endforeach
        </ul>
    </section>
</div>
