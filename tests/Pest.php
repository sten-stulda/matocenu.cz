<?php

use App\Domain\Administration\ReferenceData\LocationsSyncer;
use App\Domain\AI\Providers\FakeProvider;
use App\Domain\Answers\Actions\SubmitAnswer;
use App\Domain\Answers\DTO\AnswerData;
use App\Domain\Identity\Actions\RecordConsents;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Answer;
use App\Models\LanguageStyle;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // Every test runs with the same reference data as production (except the large location list).
        foreach (config('matocenu.reference_data') as $class) {
            if ($class !== LocationsSyncer::class) {
                app($class)->sync();
            }
        }
        FakeProvider::reset();
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

/** Switch AI to the offline FakeProvider as the only (primary) model. */
function useFakeAi(): AiModel
{
    AiModel::query()->update(['is_primary' => false, 'is_fallback' => false]);
    AiProvider::where('slug', 'fake')->update(['is_active' => true]);
    $m = AiModel::where('model', 'fake-stylist')->firstOrFail();
    $m->forceFill(['is_primary' => true, 'input_price_per_mtok' => '1.000000', 'output_price_per_mtok' => '2.000000'])->save();

    return $m;
}

function style(string $slug): LanguageStyle
{
    return LanguageStyle::where('slug', $slug)->firstOrFail();
}
