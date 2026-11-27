<?php

use App\Domain\Comments\Actions\CommentActions;
use App\Domain\Comments\Exceptions\CommentException;
use App\Domain\Moderation\Actions\AppealDecision;
use App\Domain\Moderation\Actions\ReportContent;
use App\Domain\Moderation\Enums\ReportReason;
use App\Domain\Moderation\Exceptions\ModerationException;
use App\Domain\Social\Actions\SocialActions;
use App\Models\Comment;
use App\Models\ModerationAction;
use App\Notifications\CommentReceived;
use App\Notifications\ModerationDecision;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->q = liveQuestion();
    $this->author = member();
    $this->answer = answerOf($this->q, $this->author, 'Klid a víc času na kluky.');
});

it('adds comments with limited thread depth and notifies the author', function () {
    Notification::fake();
    $c = app(CommentActions::class);
    $a = $c->add(member(), $this->answer, 'Držím palce.');
    $b = $c->add(member(), $this->answer, 'Já taky.', $a);
    $d = $c->add(member(), $this->answer, 'A já!', $b);
    $e = $c->add(member(), $this->answer, 'Hloubka?', $d);
    expect([$a->depth, $b->depth, $d->depth, $e->depth])->toBe([0, 1, 2, 2]);
    expect($this->answer->fresh()->comments_count)->toBe(4);
    Notification::assertSentTo($this->author, CommentReceived::class);
});

it('lets users edit and delete their own comments only', function () {
    $u = member();
    $c = app(CommentActions::class)->add($u, $this->answer, 'Původní');
    app(CommentActions::class)->edit($u, $c, 'Upravený');
    expect($c->fresh()->body)->toBe('Upravený')->and($c->fresh()->edited_at)->not->toBeNull();
    expect(fn () => app(CommentActions::class)->edit(member(), $c, 'Cizí'))->toThrow(CommentException::class);
    app(CommentActions::class)->delete($u, $c);
    expect(Comment::count())->toBe(0);
});

it('prevents blocked users from commenting', function () {
    $troll = member();
    app(SocialActions::class)->toggleBlock($this->author, $troll);
    expect(fn () => app(CommentActions::class)->add($troll, $this->answer, 'Haha'))->toThrow(CommentException::class);
});

it('renders the comments component', function () {
    $u = member();
    Livewire::actingAs($u)->test('comments', ['type' => 'answer', 'id' => $this->answer->id])
        ->set('body', 'Bída na dluh – to si půjčím do hospody.')->call('post')->assertHasNoErrors()->assertSee('Bída na dluh');
});

it('marks reported content and auto-hides after enough distinct reports', function () {
    $report = app(ReportContent::class);
    $report(member(), $this->answer, ReportReason::Spam);
    expect($this->answer->fresh()->moderation_status->value)->toBe('reported');
    expect(fn () => $report(member(), $this->answer, ReportReason::Spam) && $report($this->author, $this->answer, ReportReason::Spam))->toThrow(ModerationException::class);
    $report(member(), $this->answer, ReportReason::Insult);
    expect($this->answer->fresh()->moderation_status->value)->toBe('hidden');
    expect($this->q->fresh()->answers_count)->toBe(0);
});

it('rejects duplicate reports from the same user', function () {
    $u = member();
    app(ReportContent::class)($u, $this->answer, ReportReason::Spam);
    expect(fn () => app(ReportContent::class)($u, $this->answer, ReportReason::Spam))->toThrow(ModerationException::class, 'už jsi nahlásil');
});

it('lets a moderator hide content, notifies the author and supports an appeal decided by another moderator', function () {
    Notification::fake();
    $mod1 = member('moderator');
    $mod2 = member('moderator');
    Livewire::actingAs($mod1)->test('pages::admin.moderation')
        ->set('status', 'open')
        ->call('decide', 'answer', $this->answer->id, 'hidden')->assertHasErrors()
        ->set('reason.answer-'.$this->answer->id, 'Mimo téma')
        ->call('decide', 'answer', $this->answer->id, 'hidden')->assertHasNoErrors();
    expect($this->answer->fresh()->moderation_status->value)->toBe('hidden');
    Notification::assertSentTo($this->author, ModerationDecision::class);

    $action = ModerationAction::where('action', 'hide')->first();
    $appeal = app(AppealDecision::class)->file($this->author, $action, 'Bylo to k tématu, prosím o přezkum.');
    expect(fn () => app(AppealDecision::class)->decide($mod1, $appeal, true, 'ok'))->toThrow(ModerationException::class, 'jiný moderátor');
    app(AppealDecision::class)->decide($mod2, $appeal->fresh(), true, 'Máte pravdu.');
    expect($this->answer->fresh()->moderation_status->value)->toBe('approved')
        ->and(ModerationAction::where('moderatable_id', $this->answer->id)->pluck('action')->all())->toContain('hide', 'appeal_overturned');
});

it('keeps a full moderation history', function () {
    $a = answerOf(liveQuestion(), null, 'Pište na pepa@example.com');
    expect($a->moderation_status->value)->toBe('under_review');
    expect(ModerationAction::where('moderatable_type', 'answer')->where('moderatable_id', $a->id)->first()->action)->toBe('auto_flag');
});
