<?php

namespace App\Providers;

use App\Domain\Administration\Services\FeatureFlags;
use App\Domain\Administration\Services\Settings;
use App\Domain\Identity\Enums\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        $this->app->singleton(FeatureFlags::class);
    }

    public function boot(): void
    {
        Carbon::setLocale('cs');
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Have I Been Pwned check (k-anonymity range API) – skipped in tests to keep CI offline-safe.
        Password::defaults(fn () => $this->app->runningUnitTests()
            ? Password::min(10)->letters()->numbers()
            : Password::min(10)->letters()->numbers()->uncompromised(3));

        // Superadmin can do everything; everyone else goes through permissions/policies.
        Gate::before(fn (User $user) => $user->hasRole(Role::Superadmin->value) ? true : null);

        Blade::if('feature', fn (string $key) => feature($key));
        Blade::directive('cspNonce', fn () => '<?php if ($__n = \\Illuminate\\Support\\Facades\\Vite::cspNonce()) echo \'nonce="\'.e($__n).\'"\'; ?>');

        $this->rateLimiters();
    }

    private function rateLimiters(): void
    {
        $by = fn (Request $r) => $r->user()?->id ?: $r->ip();

        RateLimiter::for('api', fn (Request $r) => Limit::perMinute($r->user() ? 120 : 60)->by($by($r)));
        RateLimiter::for('content-write', fn (Request $r) => [
            Limit::perMinute(10)->by('cw:'.$by($r)),
            Limit::perHour(120)->by('cwh:'.$by($r)),
        ]);
        RateLimiter::for('reactions', fn (Request $r) => Limit::perMinute(60)->by('re:'.$by($r)));
        RateLimiter::for('reports', fn (Request $r) => Limit::perHour(20)->by('rp:'.$by($r)));
        RateLimiter::for('ai', fn (Request $r) => [
            Limit::perMinute(6)->by('ai:'.$by($r)),
            Limit::perDay(max(1, app(Settings::class)->int('ai.user_daily_generations') * 2))->by('aid:'.$by($r)),
        ]);
        RateLimiter::for('register', fn (Request $r) => Limit::perHour(5)->by('reg:'.$r->ip()));
        RateLimiter::for('export', fn (Request $r) => Limit::perDay(3)->by('exp:'.$by($r)));
    }
}
