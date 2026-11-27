<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\FindOrCreateSocialUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class GoogleController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        abort_unless(feature('google_login') && config('services.google.client_id'), 404);

        return Socialite::driver('google')->redirect(); // default scopes: openid, profile, email
    }

    public function callback(FindOrCreateSocialUser $action): RedirectResponse
    {
        abort_unless(feature('google_login') && config('services.google.client_id'), 404);

        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()->route('login')->with('error', 'Přihlášení přes Google se nepovedlo. Zkus to prosím znovu.');
        }

        $user = $action->google($google);
        if (! $user || ! $user->isActive()) {
            return redirect()->route('login')->with('error', 'Tento účet nelze použít k přihlášení.');
        }

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->intended(route('home'));
    }
}
