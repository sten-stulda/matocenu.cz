<?php

use App\Domain\Identity\Actions\DeleteAccount;
use App\Domain\Identity\Actions\RecordConsents;
use App\Models\ConsentRecord;
use App\Models\PrivacyRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Soukromí a data')] class extends Component {
    public string $requestType = 'other';
    public string $requestMessage = '';
    public string $deletePassword = '';
    public string $deleteConfirm = '';
    public bool $deleteContent = false;

    #[Computed]
    public function consents()
    {
        return ConsentRecord::where('user_id', auth()->id())->with('document:id,title,version')->latest('created_at')->limit(30)->get();
    }

    #[Computed]
    public function marketing(): bool
    {
        return (bool) auth()->user()->preferences()->marketing_emails;
    }

    #[Computed]
    public function requests()
    {
        return PrivacyRequest::where('user_id', auth()->id())->latest()->get();
    }

    public function toggleMarketing(RecordConsents $consents): void
    {
        $user = auth()->user();
        $new = ! $this->marketing;
        $user->preferences()->update(['marketing_emails' => $new]);
        $consents->grant($user, 'marketing', $new);
        unset($this->marketing, $this->consents);
    }

    public function submitRequest(): void
    {
        $this->validate([
            'requestType' => 'required|in:'.implode(',', array_keys(PrivacyRequest::TYPES)),
            'requestMessage' => 'required|string|min:10|max:2000',
        ], [], ['requestMessage' => 'popis žádosti']);
        PrivacyRequest::create(['user_id' => auth()->id(), 'type' => $this->requestType, 'message' => $this->requestMessage]);
        $this->reset('requestMessage');
        unset($this->requests);
        session()->flash('status', 'Žádost jsme přijali. Vyřídíme ji nejpozději do 30 dnů.');
    }

    public function deleteAccount(DeleteAccount $delete): void
    {
        $user = auth()->user();
        $rules = ['deleteConfirm' => 'required|in:SMAZAT'];
        if ($user->password) {
            $rules['deletePassword'] = 'required';
        }
        $this->validate($rules, ['deleteConfirm.in' => 'Napiš prosím SMAZAT velkými písmeny.']);
        if ($user->password && ! Hash::check($this->deletePassword, $user->password)) {
            $this->addError('deletePassword', 'Heslo nesouhlasí.');

            return;
        }
        $delete($user, $this->deleteContent);
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();
        session()->flash('status', 'Účet byl smazán. Díky, že jsi byl/a s námi.');
        $this->redirectRoute('home');
    }
}; ?>

<div class="mx-auto max-w-3xl space-y-6">
    @include('pages.account.nav', ['active' => 'privacy'])

    <section class="card space-y-3">
        <h2 class="font-display text-xl font-bold">Export osobních údajů</h2>
        <p class="text-sm text-muted">Stáhneš JSON se vším, co o tobě evidujeme: účet, nastavení, souhlasy a veškerý tvůj obsah včetně originálů odpovědí.</p>
        <a href="{{ route('account.export') }}" class="btn-outline">Stáhnout export</a>
    </section>

    <section class="card space-y-3">
        <h2 class="font-display text-xl font-bold">Souhlasy</h2>
        <div class="flex items-center justify-between gap-4">
            <span><b class="block">Marketingové e-maily</b><span class="text-[13px] text-muted">Občasné novinky. Bez souhlasu nic neposíláme.</span></span>
            <button type="button" role="switch" class="toggle" aria-label="Marketingové e-maily" aria-checked="{{ $this->marketing ? 'true' : 'false' }}" wire:click="toggleMarketing"><span></span></button>
        </div>
        <details class="text-sm"><summary class="cursor-pointer font-semibold">Historie souhlasů</summary>
            <ul class="mt-2 space-y-1">
                @forelse ($this->consents as $c)
                    <li>{{ local_time($c->created_at)->format('j. n. Y H:i') }} – {{ \App\Models\ConsentRecord::TYPES[$c->consent_type] ?? $c->consent_type }}: <b>{{ $c->granted ? 'udělen' : 'odvolán' }}</b>@if ($c->document) (verze {{ $c->document->version }})@endif</li>
                @empty <li class="text-muted">Žádné záznamy.</li> @endforelse
            </ul>
        </details>
    </section>

    <form wire:submit="submitRequest" class="card space-y-3">
        <h2 class="font-display text-xl font-bold">Žádost o ochranu údajů</h2>
        <p class="text-sm text-muted">Oprava, námitka nebo cokoliv jiného – vyřizuje ji člověk.</p>
        <select wire:model="requestType" class="input" aria-label="Typ žádosti">
            @foreach (\App\Models\PrivacyRequest::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
        </select>
        <textarea wire:model="requestMessage" rows="3" class="input" aria-label="Popis žádosti" placeholder="Popiš, co potřebuješ…"></textarea>
        @error('requestMessage')<p class="error">{{ $message }}</p>@enderror
        <button class="btn-forest">Odeslat žádost</button>
        @if ($this->requests->isNotEmpty())
            <ul class="mt-2 space-y-1 text-sm">
                @foreach ($this->requests as $r)
                    <li>{{ local_time($r->created_at)->format('j. n. Y') }} · {{ \App\Models\PrivacyRequest::TYPES[$r->type] }} · <b>{{ \App\Models\PrivacyRequest::STATUSES[$r->status] }}</b>@if ($r->resolution) – {{ $r->resolution }}@endif</li>
                @endforeach
            </ul>
        @endif
    </form>

    <form wire:submit="deleteAccount" class="card space-y-3 border-terra-ink/40">
        <h2 class="font-display text-xl font-bold text-terra-ink dark:text-terra">Smazat účet</h2>
        <p class="text-sm">Nevratné. E-mail, přezdívka, avatar, nastavení a souhlasy zmizí okamžitě.</p>
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" wire:model="deleteContent" class="mt-0.5 h-4 w-4 accent-forest">
            <span>Smazat i můj obsah (otázky, odpovědi, komentáře). Jinak zůstane zobrazený jako „Anonym“ bez vazby na mě.</span></label>
        @if (auth()->user()->password)
            <div><label class="label" for="dp">Heslo</label><input id="dp" type="password" wire:model="deletePassword" class="input" autocomplete="current-password">@error('deletePassword')<p class="error">{{ $message }}</p>@enderror</div>
        @endif
        <div><label class="label" for="dc">Pro potvrzení napiš SMAZAT</label><input id="dc" wire:model="deleteConfirm" class="input" autocomplete="off">@error('deleteConfirm')<p class="error">{{ $message }}</p>@enderror</div>
        <button class="btn-danger">Trvale smazat účet</button>
    </form>
</div>
