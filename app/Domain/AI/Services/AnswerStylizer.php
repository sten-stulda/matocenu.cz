<?php

namespace App\Domain\AI\Services;

use App\Domain\Administration\Services\Settings;
use App\Domain\AI\Exceptions\AiBudgetExceeded;
use App\Domain\AI\Exceptions\AiInvalidOutput;
use App\Domain\AI\Exceptions\AiStyleNotAllowed;
use App\Domain\AI\Exceptions\AiUnavailable;
use App\Domain\Answers\Exceptions\AnswerException;
use App\Domain\Answers\Services\AnswerLength;
use App\Models\AiGeneration;
use App\Models\AiGenerationCache;
use App\Models\AiModel;
use App\Models\AiPromptVersion;
use App\Models\AnswerVariant;
use App\Models\LanguageStyle;
use App\Models\Question;
use App\Models\User;
use App\Support\Text;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Rewrites a person's real answer into a language style. Flow:
 * validate → style rules (sensitive topics) → cache (HMAC key, per-user or shared for generic answers)
 * → budget → lock (no concurrent duplicate generation) → AI → validate → ≤1 repair → store variants.
 */
class AnswerStylizer
{
    public const OPERATION = 'answer_stylize';

    public function __construct(
        private AiGateway $gateway,
        private AiStatus $status,
        private BudgetGuard $budget,
        private PromptRegistry $prompts,
        private OutputValidator $validator,
        private AnswerLength $length,
        private Settings $settings,
    ) {}

    public function resolveStyle(LanguageStyle $style, Question $q, User $user): LanguageStyle
    {
        if ($style->is_random) {
            $pool = LanguageStyle::active()->filter(fn (LanguageStyle $s) => $s->isGeneratable() && $s->supports('answer') && $this->allowedFor($s, $q, $user));
            if ($pool->isEmpty()) {
                throw new AiStyleNotAllowed('Pro tuto otázku není k dispozici žádný styl.');
            }

            return $pool->random();
        }

        return $style;
    }

    public function allowedFor(LanguageStyle $style, Question $q, User $user): bool
    {
        if (! $style->is_active || ! $style->isGeneratable() || ! $style->supports('answer')) {
            return false;
        }

        return ! $q->is_sensitive || $style->allowsSensitive() || $user->preferences()->humor_on_sensitive;
    }

    public function regenerationsLeft(User $user, Question $q, string $original, LanguageStyle $style): int
    {
        $used = AiGeneration::where('user_id', $user->id)->where('question_id', $q->id)->where('language_style_id', $style->id)
            ->where('input_hash', CacheKey::inputHash($original))->where('is_regeneration', true)->count();

        return max(0, $this->settings->int('ai.regenerations_per_answer') - $used);
    }

    /**
     * @throws AnswerException|AiStyleNotAllowed|AiUnavailable|AiInvalidOutput|AiBudgetExceeded
     */
    public function generate(User $user, Question $q, string $original, LanguageStyle $style, bool $regenerate = false): AiGeneration
    {
        $original = Text::clean($original);
        if ($error = $this->length->validate($original)) {
            throw new AnswerException($error);
        }
        $style = $this->resolveStyle($style, $q, $user);
        if (! $this->allowedFor($style, $q, $user)) {
            throw new AiStyleNotAllowed($q->is_sensitive
                ? 'U citlivých témat humor nepoužíváme. Zvol neutrální styl, nebo zveřejni originál.'
                : 'Tento styl teď není k dispozici.');
        }
        if (! $this->status->availableFor($user)) {
            throw new AiUnavailable($this->status->unavailableReason(), 'not_configured');
        }
        if ($regenerate && $this->regenerationsLeft($user, $q, $original, $style) <= 0) {
            throw new AiStyleNotAllowed('Další varianty už pro tuto odpověď vygenerovat nejde. Vyber si z nabízených nebo ponech originál.');
        }

        $prompt = $this->prompts->production(self::OPERATION);
        $model = $this->status->models()[0];
        $max = $this->length->max();
        $n = max(1, min(5, $this->settings->int('ai.variants_per_request')));
        $shareable = CacheKey::isShareable($original);
        $scope = $shareable ? 'global' : 'user:'.$user->id;
        $key = CacheKey::make([
            'op' => self::OPERATION, 'q' => $q->id, 'qv' => $q->version,
            'text' => $shareable ? Text::fold($original) : $original, 'lang' => 'cs',
            'style' => $style->slug, 'sv' => $style->version, 'pv' => $prompt->id,
            'model' => $model->id, 'max' => $max, 'n' => $n, 'temp' => $model->temperature, 'scope' => $scope,
            'sensitive' => $q->is_sensitive,
        ]);
        $base = [
            'user_id' => $user->id, 'operation' => self::OPERATION, 'question_id' => $q->id,
            'language_style_id' => $style->id, 'style_version' => $style->version, 'ai_prompt_version_id' => $prompt->id,
            'ai_model_id' => $model->id, 'input_hash' => CacheKey::inputHash($original), 'cache_key' => $key, 'is_regeneration' => $regenerate,
        ];

        if (! $regenerate && ($hit = $this->fromCache($key, $user, $base, $prompt))) {
            return $hit;
        }
        $this->budget->assertCanSpend($user);

        // Only one process generates a given key; others wait and then reuse the cached result.
        return Cache::lock('mtc:ai-lock:'.$key, 40)->block(30, function () use ($key, $user, $base, $prompt, $q, $original, $style, $max, $n, $regenerate, $scope) {
            if (! $regenerate && ($hit = $this->fromCache($key, $user, $base, $prompt))) {
                return $hit;
            }
            $generation = AiGeneration::create($base + ['status' => 'pending']);
            $vars = [
                'question' => $q->text,
                'original_answer' => $original,
                'language_style' => $style->name,
                'style_instructions' => (string) $style->instructions,
                'style_examples' => collect($style->examples)->map(fn ($e) => '„'.($e['original'] ?? '').'“ → „'.($e['styled'] ?? '').'“')->implode("\n"),
                'max_characters' => $max,
                'sensitivity_level' => $q->is_sensitive ? 'vysoká – žádné zesměšňování, citlivě a neutrálně' : 'běžná',
                'variants' => $n,
                'repair_note' => '',
            ];
            $cost = '0';
            try {
                [$valid, $cost] = $this->attempt($generation, $user, $prompt, $vars, $original, $max, $regenerate);
                if (! $valid) {
                    // One automatic repair with feedback, then give up.
                    $vars['repair_note'] = 'Předchozí návrh neprošel kontrolou. Dodrž přesně délku, čísla, jména a postoj autora.';
                    [$valid, $cost2] = $this->attempt($generation, $user, $prompt, $vars, $original, $max, true);
                    $cost = bcadd($cost, $cost2, 4);
                }
            } catch (AiUnavailable $e) {
                $generation->update(['status' => 'failed', 'error' => $e->getMessage()]);
                throw $e;
            }
            if (! $valid) {
                $generation->update(['status' => 'rejected', 'error' => 'Výstup neprošel kontrolou ani po opravě.']);
                $this->gateway->logEvent(self::OPERATION, 'rejected', $user, $generation->id, $prompt, '0', 'invalid_output');
                throw new AiInvalidOutput(['výstup neprošel kontrolou']);
            }
            $valid = $this->semanticFilter($valid, $original, $q, $user, $generation->id);
            $valid = array_slice($valid, 0, $n);
            if (! $regenerate) {
                AiGenerationCache::updateOrCreate(['cache_key' => $key], [
                    'scope' => $scope, 'operation' => self::OPERATION, 'variants' => $valid,
                    'ai_model_id' => $base['ai_model_id'], 'ai_prompt_version_id' => $prompt->id, 'cost_czk' => $cost,
                    'expires_at' => now()->addDays(90),
                ]);
            }
            $generation->update(['status' => 'succeeded']);
            $this->storeVariants($generation, $user, $base, $valid);

            return $generation->load('variants', 'style');
        });
    }

    /** @param array<string, mixed> $vars
     * @return array{0: list<string>, 1: string} valid variants and cost */
    private function attempt(AiGeneration $generation, User $user, AiPromptVersion $prompt, array $vars, string $original, int $max, bool $hotter): array
    {
        $r = $this->gateway->complete(self::OPERATION, $prompt, $vars, $user, $generation->id, $hotter ? 0.2 : null);
        $data = $this->validator->parse($r['response']->text);
        $candidates = is_array($data['variants'] ?? null) ? $data['variants'] : [];
        $checked = $this->validator->variants($original, $candidates, $max);
        if (! $checked['valid']) {
            $this->gateway->logEvent(self::OPERATION, 'rejected', $user, $generation->id, $prompt, '0', mb_substr(implode('; ', $checked['problems']) ?: 'neplatný JSON', 0, 60));
        }

        return [$checked['valid'], $r['cost_czk']];
    }

    /** Optional (costly) second AI pass that drops variants changing the meaning. Disabled by default. */
    private function semanticFilter(array $variants, string $original, Question $q, User $user, int $generationId): array
    {
        if (! $this->settings->bool('ai.semantic_check')) {
            return $variants;
        }
        try {
            $prompt = $this->prompts->production('semantic_check');
            $r = $this->gateway->complete('semantic_check', $prompt, [
                'question' => $q->text, 'original_answer' => $original,
                'variants' => collect($variants)->map(fn ($v, $i) => $i.': '.$v)->implode("\n"),
            ], $user, $generationId);
            $keep = (array) ($this->validator->parse($r['response']->text)['keep'] ?? []);
            $filtered = array_values(array_filter($variants, fn ($v, $i) => in_array($i, $keep, true), ARRAY_FILTER_USE_BOTH));

            return $filtered ?: $variants;
        } catch (\Throwable) {
            return $variants; // the check is advisory; the author confirms anyway
        }
    }

    /** @param array<string, mixed> $base */
    private function fromCache(string $key, User $user, array $base, AiPromptVersion $prompt): ?AiGeneration
    {
        $entry = AiGenerationCache::where('cache_key', $key)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->first();
        if (! $entry || ! $entry->variants) {
            return null;
        }
        $entry->increment('hits');
        $entry->forceFill(['last_hit_at' => now()])->save();
        $generation = AiGeneration::create($base + ['status' => 'succeeded', 'cache_hit' => true]);
        $this->gateway->logEvent(self::OPERATION, 'cache_hit', $user, $generation->id, $prompt, (string) $entry->cost_czk);
        $this->storeVariants($generation, $user, $base, $entry->variants);

        return $generation->load('variants', 'style');
    }

    /** @param array<string, mixed> $base
     * @param list<string> $texts */
    private function storeVariants(AiGeneration $generation, User $user, array $base, array $texts): void
    {
        DB::transaction(function () use ($generation, $user, $base, $texts) {
            foreach (array_values($texts) as $i => $t) {
                AnswerVariant::create([
                    'ai_generation_id' => $generation->id, 'user_id' => $user->id, 'question_id' => $base['question_id'],
                    'language_style_id' => $base['language_style_id'], 'input_hash' => $base['input_hash'], 'text' => $t, 'position' => $i,
                ]);
            }
        });
    }

    public function primaryModel(): ?AiModel
    {
        return $this->status->models()[0] ?? null;
    }
}
