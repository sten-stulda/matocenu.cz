<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Actions\RecordConsents;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logged-in users must (1) be active and (2) have accepted the current terms & privacy version.
 * Also refreshes last_seen_at at most every 5 minutes.
 */
class EnsureCurrentConsents
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if (! $user->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Účet je pozastavený nebo smazaný.');
        }

        if (! $user->last_seen_at || $user->last_seen_at->lt(now()->subMinutes(5))) {
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        $exempt = $request->routeIs('consents.*', 'logout', 'legal.show', 'verification.*') || $request->is('livewire*', 'api/*');
        if (! $exempt && $request->isMethod('GET') && ! app(RecordConsents::class)->upToDate($user)) {
            return redirect()->route('consents.show');
        }

        return $next($request);
    }
}
