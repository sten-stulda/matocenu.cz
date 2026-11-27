@if (feature('google_login') && config('services.google.client_id'))
    <div class="my-5 flex items-center gap-3 text-xs text-muted"><span class="h-px flex-1 bg-line"></span>nebo<span class="h-px flex-1 bg-line"></span></div>
    <a href="{{ route('auth.google.redirect') }}" class="btn w-full border-[1.5px] border-line bg-surface text-ink hover:border-primary">
        <x-icon name="google" :size="18" /> Pokračovat přes Google
    </a>
@endif
