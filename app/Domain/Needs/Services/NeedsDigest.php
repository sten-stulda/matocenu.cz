<?php

namespace App\Domain\Needs\Services;

use App\Models\Follow;
use App\Models\Location;
use App\Models\Need;
use App\Models\User;
use App\Notifications\NeedsDigestNotification;
use Illuminate\Support\Facades\Cache;

/** Weekly summary of new needs for people following a category or region (opportunity watchers). */
class NeedsDigest
{
    public function send(): int
    {
        $sent = 0;
        $since = now()->subWeek();
        Follow::whereIn('followable_type', ['category', 'location'])->get()->groupBy('user_id')->each(function ($follows, $userId) use ($since, &$sent) {
            $user = User::find($userId);
            if (! $user || ! $user->isActive()) {
                return;
            }
            $q = Need::query()->visible()->where('published_at', '>=', $since)->where(function ($w) use ($follows) {
                foreach ($follows as $f) {
                    if ($f->followable_type === 'category') {
                        $w->orWhere('category_id', $f->followable_id);
                    } elseif ($region = Location::find($f->followable_id)) {
                        $w->orWhereIn('location_id', $region->descendantIds());
                    }
                }
            });
            $needs = $q->orderByDesc('interested_count')->limit(10)->get();
            if ($needs->isNotEmpty()) {
                $user->notify(new NeedsDigestNotification($needs->pluck('title')->all(), $needs->count()));
                $sent++;
            }
        });
        Cache::forever('mtc:task:needs_digest', now()->toIso8601String());

        return $sent;
    }
}
