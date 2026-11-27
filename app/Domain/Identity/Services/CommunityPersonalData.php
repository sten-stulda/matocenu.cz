<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\PersonalDataContributor;
use App\Domain\Moderation\Services\CounterSync;
use App\Domain\Reactions\Actions\ToggleReaction;
use App\Models\Answer;
use App\Models\Bookmark;
use App\Models\Comment;
use App\Models\Follow;
use App\Models\ModerationAppeal;
use App\Models\Question;
use App\Models\Quote;
use App\Models\Reaction;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Questions, answers (incl. private originals), comments, reactions, reports… for GDPR export/erasure. */
class CommunityPersonalData implements PersonalDataContributor
{
    public function key(): string
    {
        return 'komunita';
    }

    public function export(User $user): array
    {
        return [
            'otazky' => Question::withTrashed()->where('author_id', $user->id)->get()->map(fn ($q) => [
                'text' => $q->text, 'vytvoreno' => $q->created_at?->toIso8601String(), 'anonymne' => $q->is_anonymous, 'stav' => $q->moderation_status->value, 'smazano' => (bool) $q->deleted_at,
            ])->all(),
            'odpovedi' => Answer::withTrashed()->with('question:id,text')->where('user_id', $user->id)->get()->map(fn ($a) => [
                'otazka' => $a->question?->text, 'original' => $a->original_text, 'zverejneny_text' => $a->display_text, 'stylizovano_ai' => $a->is_stylized,
                'zobrazit_original' => $a->show_original, 'anonymne' => $a->is_anonymous, 'stav' => $a->moderation_status->value,
                'vytvoreno' => $a->created_at?->toIso8601String(), 'smazano' => (bool) $a->deleted_at,
            ])->all(),
            'komentare' => Comment::withTrashed()->where('user_id', $user->id)->get()->map(fn ($c) => ['text' => $c->body, 'vytvoreno' => $c->created_at?->toIso8601String()])->all(),
            'reakce' => Reaction::with('type:id,label')->where('user_id', $user->id)->get()->map(fn ($r) => ['reakce' => $r->type?->label, 'na' => $r->reactable_type.' #'.$r->reactable_id, 'kdy' => $r->created_at?->toIso8601String()])->all(),
            'nahlaseni' => Report::where('reporter_id', $user->id)->get()->map(fn ($r) => ['duvod' => $r->reason->label(), 'kdy' => $r->created_at?->toIso8601String()])->all(),
            'odvolani' => ModerationAppeal::where('user_id', $user->id)->get(['message', 'status', 'created_at'])->toArray(),
            'oceneni' => DB::table('user_achievements')->join('achievements', 'achievements.id', '=', 'user_achievements.achievement_id')
                ->where('user_achievements.user_id', $user->id)->get(['achievements.name as oceneni', 'user_achievements.awarded_at as kdy'])->map(fn ($r) => (array) $r)->all(),
            'hlasky' => Quote::where('author_id', $user->id)->get(['period', 'period_key', 'display_text'])->toArray(),
            'zalozky' => Bookmark::where('user_id', $user->id)->count(),
            'sledovane' => Follow::where('user_id', $user->id)->count(),
        ];
    }

    public function erase(User $user, bool $deleteContent): void
    {
        $questionIds = Answer::withTrashed()->where('user_id', $user->id)->pluck('question_id')->unique();
        if ($deleteContent) {
            Answer::where('user_id', $user->id)->get()->each->delete();
            Comment::where('user_id', $user->id)->get()->each->delete();
            Question::where('author_id', $user->id)->where('answers_count', 0)->get()->each->delete();
        } else {
            Answer::withTrashed()->where('user_id', $user->id)->update(['is_anonymous' => true]);
            Question::withTrashed()->where('author_id', $user->id)->update(['is_anonymous' => true]);
        }
        // Reactions of a deleted person no longer count.
        $reactedAnswers = Reaction::where('user_id', $user->id)->where('reactable_type', 'answer')->pluck('reactable_id');
        Reaction::where('user_id', $user->id)->delete();
        Bookmark::where('user_id', $user->id)->delete();
        Follow::where('user_id', $user->id)->delete();
        Report::where('reporter_id', $user->id)->update(['note' => null]);
        foreach (Answer::whereIn('id', $reactedAnswers)->get() as $a) {
            app(ToggleReaction::class)->recount($a);
        }
        foreach ($questionIds as $qid) {
            app(CounterSync::class)->question((int) $qid);
        }
        DB::table('moderation_appeals')->where('user_id', $user->id)->update(['message' => '[smazáno]']);
    }
}
