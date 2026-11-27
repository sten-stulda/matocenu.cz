<?php

use App\Domain\Administration\ReferenceData\CategoriesSyncer;
use App\Domain\Administration\ReferenceData\FeatureFlagsSyncer;
use App\Domain\Administration\ReferenceData\LegalDocumentsSyncer;
use App\Domain\Administration\ReferenceData\ReactionTypesSyncer;
use App\Domain\Administration\ReferenceData\RolesSyncer;
use App\Domain\Answers\Actions\SubmitAnswer;
use App\Domain\Answers\DTO\AnswerData;
use App\Domain\Identity\Actions\RecordConsents;
use App\Models\Answer;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // Every test runs with roles, flags and current legal documents in place.
        app(RolesSyncer::class)->sync();
        app(FeatureFlagsSyncer::class)->sync();
        app(LegalDocumentsSyncer::class)->sync();
        app(CategoriesSyncer::class)->sync();
        app(ReactionTypesSyncer::class)->sync();
    })
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/** A verified, consented user, optionally with an admin role. */
function member(?string $role = null, array $attrs = []): User
{
    $user = User::factory()->create($attrs);
    $user->assignRole('user');
    if ($role) {
        $user->assignRole($role);
    }
    app(RecordConsents::class)->registration($user, false, 'test');

    return $user->fresh();
}

/** A published, approved question. */
function liveQuestion(array $attrs = []): Question
{
    return Question::factory()->live()->create($attrs);
}

/** A public answer by $user (or a new member) to $question. */
function answerOf(Question $question, ?User $user = null, string $text = 'Testovací odpověď'): Answer
{
    return app(SubmitAnswer::class)($user ?? member(), $question, new AnswerData($text));
}
