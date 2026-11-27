<?php

namespace App\Domain\Social\Actions;

use App\Domain\Administration\Services\AuditLogger;
use App\Domain\Moderation\Services\CounterSync;
use App\Models\Bookmark;
use App\Models\Follow;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Bookmarks, follows and user blocks – idempotent toggles protected by unique keys. */
class SocialActions
{
    public function toggleBookmark(User $user, Model $target): bool
    {
        $key = ['user_id' => $user->id, 'bookmarkable_type' => $target->getMorphClass(), 'bookmarkable_id' => $target->getKey()];
        if (Bookmark::where($key)->delete()) {
            return false;
        }
        DB::table('bookmarks')->insertOrIgnore($key + ['created_at' => now()]);

        return true;
    }

    public function toggleFollow(User $user, Model $target): bool
    {
        $key = ['user_id' => $user->id, 'followable_type' => $target->getMorphClass(), 'followable_id' => $target->getKey()];
        $following = ! Follow::where($key)->delete();
        if ($following) {
            DB::table('follows')->insertOrIgnore($key + ['created_at' => now()]);
        }
        if ($target instanceof Question) {
            app(CounterSync::class)->question($target->id);
        }

        return $following;
    }

    public function toggleBlock(User $user, User $other): bool
    {
        if ($user->is($other)) {
            throw new InvalidArgumentException('Sám sebe zablokovat nejde.');
        }
        $key = ['blocker_id' => $user->id, 'blocked_id' => $other->id];
        if (DB::table('user_blocks')->where($key)->delete()) {
            return false;
        }
        DB::table('user_blocks')->insertOrIgnore($key + ['created_at' => now()]);
        app(AuditLogger::class)->log('user.blocked', $other);

        return true;
    }
}
