<?php

use App\Domain\Administration\Services\Settings;
use App\Domain\AI\Exceptions\AiBudgetExceeded;
use App\Domain\AI\Exceptions\AiInvalidOutput;
use App\Domain\AI\Exceptions\AiStyleNotAllowed;
use App\Domain\AI\Exceptions\AiUnavailable;
use App\Domain\AI\Providers\FakeProvider;
use App\Domain\AI\Services\AnswerStylizer;
use App\Domain\Answers\Actions\SubmitAnswer;
use App\Domain\Answers\DTO\AnswerData;
use App\Domain\Answers\Exceptions\AnswerException;
use App\Models\AiGeneration;
use App\Models\AiProvider;
use App\Models\AiUsageLog;
use App\Models\Answer;
use Livewire\Livewire;

beforeEach(function () {
    $this->model = useFakeAi();
    $this->q = liveQuestion(['text' => 'Kdo v tvém okolí potřebuje finančně pomoct?']);
    $this->user = member();
    $this->ai = app(AnswerStylizer::class);
});

it('generates validated variants and logs the cost', function () {
    $g = $this->ai->generate($this->user, $this->q, 'Moje sestra Jana.', style('moravak'));
    expect($g->status)->toBe('succeeded')->and($g->cache_hit)->toBeFalse()->and($g->variants)->toHaveCount(3);
    expect($g->variants->first()->text)->toContain('Moje sestra Jana.');
    $log = AiUsageLog::where('status', 'ok')->first();
    // 120 in × 1 USD/M + 40 out × 2 USD/M = 0.0002 USD × 23.50 = 0.0047 CZK
    expect($log->cost_usd)->toBe('0.000200')->and($log->cost_czk)->toBe('0.0047')->and($log->input_tokens)->toBe(120);
});

it('serves the second identical request from cache without calling the provider', function () {
    $this->ai->generate($this->user, $this->q, 'Moje sestra Jana.', style('moravak'));
    $calls = FakeProvider::$calls;
    $g2 = $this->ai->generate($this->user, $this->q, ' Moje  sestra Jana. ', style('moravak'));
    expect(FakeProvider::$calls)->toBe($calls)->and($g2->cache_hit)->toBeTrue();
    expect(AiUsageLog::where('status', 'cache_hit')->first()->saved_czk)->toBe('0.0047');
});

it('never shares personal answers with other users through the cache', function () {
    $this->ai->generate($this->user, $this->q, 'Moje sestra Jana.', style('moravak'));
    $calls = FakeProvider::$calls;
    $this->ai->generate(member(), $this->q, 'Moje sestra Jana.', style('moravak'));
    expect(FakeProvider::$calls)->toBe($calls + 1);
});

it('shares cache for generic answers like "Já" within the same question', function () {
    $this->ai->generate($this->user, $this->q, 'Já.', style('moravak'));
    $calls = FakeProvider::$calls;
    $g = $this->ai->generate(member(), $this->q, 'já', style('moravak'));
    expect($g->cache_hit)->toBeTrue()->and(FakeProvider::$calls)->toBe($calls);
    // …but not across questions or styles
    $this->ai->generate(member(), liveQuestion(), 'Já.', style('moravak'));
    $this->ai->generate(member(), $this->q, 'Já.', style('hospodsky'));
    expect(FakeProvider::$calls)->toBe($calls + 2);
});

it('repairs once when the output changes numbers and then succeeds', function () {
    FakeProvider::$script = [
        json_encode(['variants' => ['Tož 300 Kč, ne?']]),
        json_encode(['variants' => ['Tož 200 Kč měsíčně, víc ne.']]),
    ];
    $g = $this->ai->generate($this->user, $this->q, '200 Kč měsíčně.', style('moravak'));
    expect($g->variants->pluck('text')->all())->toBe(['Tož 200 Kč měsíčně, víc ne.']);
    expect(AiUsageLog::where('status', 'rejected')->count())->toBe(1);
});

it('gives up after one repair and records a rejected generation', function () {
    FakeProvider::$script = ['nejsem JSON', json_encode(['variants' => [str_repeat('x', 150)]])];
    expect(fn () => $this->ai->generate($this->user, $this->q, 'Ano.', style('moravak')))->toThrow(AiInvalidOutput::class);
    expect(AiGeneration::latest('id')->first()->status)->toBe('rejected');
});

it('rejects variants that flip a "no" into a yes or drop a name', function () {
    FakeProvider::$script = [json_encode(['variants' => ['Jasně, beru!']]), json_encode(['variants' => ['Kdepak, ani omylem.']])];
    $g = $this->ai->generate($this->user, $this->q, 'Ne.', style('hospodsky'));
    expect($g->variants->first()->text)->toBe('Kdepak, ani omylem.');

    FakeProvider::$script = [json_encode(['variants' => ['Babička to byla.']]), json_encode(['variants' => ['Babička Marie, kdo jiný.']])];
    $g = $this->ai->generate(member(), $this->q, 'Babička Marie.', style('starocech'));
    expect($g->variants->first()->text)->toBe('Babička Marie, kdo jiný.');
});

it('falls back when the provider is unavailable and reports it', function () {
    FakeProvider::$script = [new AiUnavailable('timeout', 'timeout'), new AiUnavailable('timeout', 'timeout')];
    expect(fn () => $this->ai->generate($this->user, $this->q, 'Ano.', style('moravak')))->toThrow(AiUnavailable::class);
    expect(AiUsageLog::where('status', 'error')->count())->toBe(2); // 1 try + 1 retry
});

it('is unavailable when no provider is configured', function () {
    AiProvider::query()->update(['is_active' => false]);
    expect(fn () => $this->ai->generate($this->user, $this->q, 'Ano.', style('moravak')))->toThrow(AiUnavailable::class);
});

it('pauses generation when the daily budget is spent', function () {
    app(Settings::class)->set('ai.daily_budget_czk', '0.00');
    expect(fn () => $this->ai->generate($this->user, $this->q, 'Ano, já.', style('moravak')))->toThrow(AiBudgetExceeded::class);
});

it('enforces the per-user daily quota', function () {
    app(Settings::class)->set('ai.user_daily_generations', 1);
    $this->ai->generate($this->user, $this->q, 'První', style('moravak'));
    expect(fn () => $this->ai->generate($this->user, $this->q, 'Druhá', style('moravak')))->toThrow(AiBudgetExceeded::class);
});

it('limits regenerations of one answer', function () {
    app(Settings::class)->set('ai.regenerations_per_answer', 1);
    $this->ai->generate($this->user, $this->q, 'Já.', style('moravak'));
    $this->ai->generate($this->user, $this->q, 'Já.', style('moravak'), regenerate: true);
    expect(fn () => $this->ai->generate($this->user, $this->q, 'Já.', style('moravak'), regenerate: true))->toThrow(AiStyleNotAllowed::class);
});

it('does not mock people on sensitive topics', function () {
    $q = liveQuestion(['is_sensitive' => true]);
    expect(fn () => $this->ai->generate($this->user, $q, 'Táta, zemřel loni.', style('hospodsky')))->toThrow(AiStyleNotAllowed::class);
    expect($this->ai->generate($this->user, $q, 'Táta, zemřel loni.', style('filozof'))->status)->toBe('succeeded');
});

it('validates the 100 character limit before calling AI', function () {
    expect(fn () => $this->ai->generate($this->user, $this->q, str_repeat('a', 101), style('moravak')))->toThrow(AnswerException::class);
    expect(FakeProvider::$calls)->toBe(0);
});

it('publishes a chosen variant, keeps the original separate and records the style', function () {
    $g = $this->ai->generate($this->user, $this->q, 'Já.', style('moravak'));
    $a = app(SubmitAnswer::class)($this->user, $this->q, new AnswerData('Já.', showOriginal: false, variantId: $g->variants->first()->id));
    expect($a->is_stylized)->toBeTrue()->and($a->original_text)->toBe('Já.')->and($a->display_text)->toBe($g->variants->first()->text)
        ->and($a->language_style_id)->toBe(style('moravak')->id);
});

it('refuses variants of other users or of a different original', function () {
    $g = $this->ai->generate($this->user, $this->q, 'Já.', style('moravak'));
    expect(fn () => app(SubmitAnswer::class)(member(), $this->q, new AnswerData('Já.', variantId: $g->variants->first()->id)))->toThrow(AnswerException::class);
    expect(fn () => app(SubmitAnswer::class)($this->user, $this->q, new AnswerData('Ty.', variantId: $g->variants->first()->id)))->toThrow(AnswerException::class);
    expect(Answer::count())->toBe(0);
});

it('runs the full 6-step flow in the composer', function () {
    Livewire::actingAs($this->user)->test('answer-composer', ['questionId' => $this->q->id])
        ->set('text', 'Já.')
        ->call('chooseStyle', 'moravak')
        ->call('stylize')->assertSet('step', 'choose')->assertSee('VYBER VARIANTU')
        ->call('toConfirm')->assertSee('Náhled tvé karty')
        ->set('showOriginal', true)
        ->call('publish')->assertSee('Zveřejněno!');
    $a = Answer::first();
    expect($a->is_stylized)->toBeTrue()->and($a->show_original)->toBeTrue();
    $this->get($this->q->url())->assertSee('Moravák')->assertSee('Stylizováno AI');
});

it('lets the author publish the original when AI is down', function () {
    FakeProvider::$script = [new AiUnavailable('x', 'timeout'), new AiUnavailable('x', 'timeout')];
    Livewire::actingAs($this->user)->test('answer-composer', ['questionId' => $this->q->id])
        ->set('text', 'Já.')->call('chooseStyle', 'moravak')->call('stylize')
        ->assertSet('step', 'write')->assertSee('x')
        ->call('publish')->assertSee('Zveřejněno!');
    expect(Answer::first()->is_stylized)->toBeFalse();
});
