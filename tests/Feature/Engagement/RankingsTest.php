<?php

use App\Domain\Administration\Services\Settings;
use App\Domain\Answers\Actions\SubmitAnswer;
use App\Domain\Answers\DTO\AnswerData;
use App\Domain\Quotes\Services\QuoteSelector;
use App\Domain\Rankings\Services\Periods;
use App\Domain\Rankings\Services\RankingEngine;
use App\Domain\Reactions\Actions\ToggleReaction;
use App\Models\Answer;
use App\Models\Quote;
use App\Models\User;
use App\Notifications\AchievementEarned;
use App\Notifications\QuoteAwarded;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

function oldMember(): User
{
    return member(null, ['created_at' => now()->subMonths(2)]);
}

function react(Answer $a, int $people, string $type = 'ma-cenu', bool $old = true): void
{
    for ($i = 0; $i < $people; $i++) {
        app(ToggleReaction::class)($old ? oldMember() : member(), $a, $type);
    }
}

it('ranks answers by trusted score and unique people, not raw counts', function () {
    $q = liveQuestion();
    $a = answerOf($q, oldMember(), 'Spánek.');
    $b = answerOf($q, oldMember(), 'Čas.');
    react($a, 3);
    react($b, 5, 'pobavilo', old: false); // young accounts → half weight and flagged
    [$from, $to] = Periods::current('week');
    $items = app(RankingEngine::class)->answers($from, $to);
    expect($items[0]['id'])->toBe($a->id)->and(collect($items)->firstWhere('id', $b->id)['suspicious'])->toBeTrue();
});

it('counts at most N answers per author per day', function () {
    app(Settings::class)->set('rankings.max_answers_per_day', 1);
    $author = oldMember();
    $x = answerOf(liveQuestion(), $author, 'Jedna');
    $y = answerOf(liveQuestion(), $author, 'Druhá');
    react($x, 2);
    react($y, 1);
    [$from, $to] = Periods::current('week');
    $ids = array_column(app(RankingEngine::class)->answers($from, $to), 'id');
    expect($ids)->toContain($x->id)->not->toContain($y->id);
});

it('excludes anonymous answers from the author leaderboard and renders the page', function () {
    $pepa = oldMember();
    $anon = oldMember();
    react(answerOf(liveQuestion(), $pepa, 'Veřejně'), 2);
    $hidden = app(SubmitAnswer::class)($anon, liveQuestion(), new AnswerData('Tajně', isAnonymous: true));
    react($hidden, 4);
    [$from, $to] = Periods::current('week');
    $authors = array_column(app(RankingEngine::class)->authors($from, $to), 'id');
    expect($authors)->toContain($pepa->id)->not->toContain($anon->id);

    $this->get('/zebricky')->assertOk()->assertSee($pepa->name)->assertDontSee($anon->name);
    $this->get('/zebricky?obdobi=den')->assertOk();
    $this->get('/zebricky/jak-pocitame')->assertOk()->assertSee('Kvalita, ne kvantita');
});

it('awards the quote of the day from real activity only', function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-12-03 12:00', 'Europe/Prague'));
    $author = oldMember();
    $a = answerOf(liveQuestion(), $author, 'Pařák jak cíp, aj komáři chodí nazí!');
    react($a, 3, 'pobavilo');
    $this->travelTo(CarbonImmutable::parse('2026-12-04 00:20', 'Europe/Prague'));
    $q = app(QuoteSelector::class)->award('day');
    expect($q->answer_id)->toBe($a->id)->and($q->period_key)->toBe('2026-12-03')
        ->and($q->legal_status)->toBe('unchecked')->and($q->commercial_status)->toBe('not_approved')->and($q->commerciallyUsable())->toBeFalse();
    expect(app(QuoteSelector::class)->award('day'))->toBeNull(); // once per period
    Notification::assertSentTo($author, QuoteAwarded::class);
    $this->get('/hlasky')->assertOk()->assertSee('Pařák jak cíp');
    $this->get('/')->assertSee('Hláška dne');
});

it('does not invent a quote when nobody reacted', function () {
    answerOf(liveQuestion(), oldMember(), 'Bez reakcí');
    expect(app(QuoteSelector::class)->award('day'))->toBeNull()->and(Quote::count())->toBe(0);
});

it('awards achievements for real activity and shows titles', function () {
    Notification::fake();
    $u = oldMember();
    $a = answerOf(liveQuestion(), $u, 'První');
    expect($u->fresh()->achievements->pluck('slug')->all())->toContain('novacek');
    Notification::assertSentTo($u, AchievementEarned::class);
    react($a, 1);
    expect($u->fresh()->achievements->pluck('slug')->all())->toContain('prvni-hlaska');
    expect($u->fresh()->title()->slug)->toBe('novacek');
    $this->get(route('profiles.show', $u))->assertSee('Nováček');
    $this->actingAs($u)->get(route('profiles.show', $u).'?tab=oceneni')->assertOk()->assertSee('Do titulu Mudrlant');
});

it('hides achievements when the user disables them', function () {
    $u = oldMember();
    answerOf(liveQuestion(), $u, 'První');
    $u->preferences()->update(['show_achievements' => false]);
    $this->get(route('profiles.show', $u))->assertDontSee('Nováček');
});
