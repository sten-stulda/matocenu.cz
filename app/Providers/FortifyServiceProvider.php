<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::loginView(fn () => view('pages.auth.login'));
        Fortify::registerView(fn () => view('pages.auth.register'));
        Fortify::verifyEmailView(fn () => view('pages.auth.verify-email'));
        Fortify::requestPasswordResetLinkView(fn () => view('pages.auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('pages.auth.reset-password', ['request' => $request]));
        Fortify::twoFactorChallengeView(fn () => view('pages.auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('pages.auth.confirm-password'));

        // Suspended / deleted accounts can't log in (same generic error as wrong password).
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', Str::lower((string) $request->input('email')))->first();
            if ($user && $user->password && Hash::check((string) $request->input('password'), $user->password) && $user->isActive()) {
                return $user;
            }

            return null;
        });
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

    }
}
