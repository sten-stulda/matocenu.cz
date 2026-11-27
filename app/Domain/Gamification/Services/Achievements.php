<?php

namespace App\Domain\Gamification\Services;

use App\Domain\Moderation\Enums\ModerationStatus;
use App\Models\Achievement;
use App\Models\Answer;
use App\Models\NeedOffer;
use App\Models\Quote;
use App\Models\User;
use App\Notifications\AchievementEarned;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Non-addictive awards for real community activity. Counts only public, non-deleted content
 * and reactions from other people; nothing rewards sheer volume of posting.
 */
class Achievements
{
    /** @return array<string, int> */
    public function metrics(User $user): array
    {
        $public = Answer::where('user_id', $user->id)->whereIn('moderation_status', ModerationStatus::publicValues());

        return [
            'answers' => (clone $public)->count(),
            'rated_answers' => (clone $public)->where('unique_reactors_count', '>', 0)->count(),
            'reactions_received' => (int) (clone $public)->sum('reactions_count'),
            'primary_reactions' => DB::table('reactions')->join('answers', 'answers.id', '=', 'reactions.reactable_id')->join('reaction_types', 'reaction_types.id', '=', 'reactions.reaction_type_id')
                ->where('reactions.reactable_type', 'answer')->where('answers.user_id', $user->id)->where('reaction_types.slug', 'ma-cenu')->count(),
            'daily_answers' => (clone $public)->whereIn('question_id', DB::table('daily_questions')->where('status', 'published')->select('question_id'))->count(),
            'quotes' => Quote::where('author_id', $user->id)->count(),
            'quote_month' => Quote::where('author_id', $user->id)->where('period', 'month')->count(),
            'need_helper' => class_exists(NeedOffer::class) && Schema::hasTable('need_offers')
                ? DB::table('need_offers')->where('user_id', $user->id)->where('status', 'accepted')->count() : 0,
            'first_answer' => (clone $public)->exists() ? 1 : 0,
        ];
    }

    /** @return list<Achievement> newly awarded */
    public function evaluate(User $user): array
    {
        if (! $user->isActive()) {
            return [];
        }
        $metrics = $this->metrics($user);
        $owned = DB::table('user_achievements')->where('user_id', $user->id)->pluck('achievement_id')->all();
        $new = [];
        foreach (Achievement::where('is_active', true)->whereNotIn('id', $owned)->get() as $a) {
            if (($metrics[$a->rule] ?? 0) >= $a->threshold) {
                $inserted = DB::table('user_achievements')->insertOrIgnore(['user_id' => $user->id, 'achievement_id' => $a->id, 'awarded_at' => now()]);
                if ($inserted) {
                    $new[] = $a;
                    $user->notify(new AchievementEarned($a));
                }
            }
        }

        return $new;
    }

    /** Progress to the next title (for the profile bar). @return array{name: string, current: int, target: int}|null */
    public function nextTitle(User $user): ?array
    {
        $metrics = $this->metrics($user);
        $owned = DB::table('user_achievements')->where('user_id', $user->id)->pluck('achievement_id')->all();
        $next = Achievement::where('is_active', true)->where('is_title', true)->whereNotIn('id', $owned)->orderBy('tier')->first();

        return $next ? ['name' => $next->name, 'current' => min($metrics[$next->rule] ?? 0, $next->threshold), 'target' => $next->threshold] : null;
    }
}
