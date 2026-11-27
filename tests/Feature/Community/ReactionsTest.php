<?php

use App\Domain\Reactions\Actions\ToggleReaction;
use App\Domain\Reactions\Exceptions\ReactionException;
use App\Domain\Social\Actions\SocialActions;
use App\Models\Reaction;
use App\Models\ReactionType;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->q = liveQuestion();
    $this->author = member();
    $this->answer = answerOf($this->q, $this->author, 'Spaní.');
});

it('adds, changes and removes a reaction', function () {
    $u = member(null, ['created_at' => now()->subMonth()]);
    $toggle = app(ToggleReaction::class);
    expect($toggle($u, $this->answer, 'ma-cenu'))->toBe('ma-cenu');
    expect($this->answer->fresh()->reactions_count)->toBe(1)->and((float) $this->answer->fresh()->score)->toBe(1.5);
    expect($toggle($u, $this->answer, 'pobavilo'))->toBe('pobavilo');
    expect(Reaction::count())->toBe(1)->and((float) $this->answer->fresh()->score)->toBe(1.0);
    expect($toggle($u, $this->answer, 'pobavilo'))->toBeNull();
    expect($this->answer->fresh()->reactions_count)->toBe(0);
});

it('counts a duplicate reaction only once', function () {
    $u = member();
    app(ToggleReaction::class)($u, $this->answer, 'ma-cenu');
    // A racing second insert of the same reaction is ignored by the unique key.
    $inserted = DB::table('reactions')->insertOrIgnore(['user_id' => $u->id, 'reactable_type' => 'answer', 'reactable_id' => $this->answer->id, 'reaction_type_id' => ReactionType::first()->id, 'created_at' => now(), 'updated_at' => now()]);
    expect($inserted)->toBe(0)->and(Reaction::count())->toBe(1);
});

it('rejects duplicate rows at the database level', function () {
    $u = member();
    $row = ['user_id' => $u->id, 'reactable_type' => 'answer', 'reactable_id' => $this->answer->id, 'reaction_type_id' => ReactionType::first()->id];
    Reaction::create($row);
    expect(fn () => Reaction::create($row))->toThrow(UniqueConstraintViolationException::class);
});

it('handles many users reacting at once with exact counts', function () {
    $users = collect(range(1, 5))->map(fn () => member());
    foreach ($users as $u) {
        app(ToggleReaction::class)($u, $this->answer, 'ma-cenu');
    }
    expect($this->answer->fresh())->reactions_count->toBe(5)->unique_reactors_count->toBe(5);
    expect($this->q->fresh()->reactions_count)->toBe(5);
});

it('forbids reacting to your own answer', function () {
    expect(fn () => app(ToggleReaction::class)($this->author, $this->answer, 'ma-cenu'))->toThrow(ReactionException::class, 'Vlastní');
});

it('requires a verified account', function () {
    expect(fn () => app(ToggleReaction::class)(member(null, ['email_verified_at' => null]), $this->answer, 'ma-cenu'))->toThrow(ReactionException::class);
});

it('gives new accounts half weight in the score', function () {
    $fresh = member(null, ['created_at' => now()]);
    $old = member(null, ['created_at' => now()->subMonth()]);
    app(ToggleReaction::class)($fresh, $this->answer, 'pobavilo');
    app(ToggleReaction::class)($old, $this->answer, 'pobavilo');
    expect((float) $this->answer->fresh()->score)->toBe(1.5);
});

it('blocks reactions from users the author blocked', function () {
    $u = member();
    app(SocialActions::class)->toggleBlock($this->author, $u);
    expect(fn () => app(ToggleReaction::class)($u, $this->answer, 'ma-cenu'))->toThrow(ReactionException::class);
});

it('works through the Livewire component', function () {
    Livewire::actingAs(member())->test('reactions', ['type' => 'answer', 'id' => $this->answer->id, 'ownerId' => $this->author->id])
        ->call('react', 'zlata-slova')->assertSet('mine', 'zlata-slova')->assertSet('counts.zlata-slova', 1);
});
