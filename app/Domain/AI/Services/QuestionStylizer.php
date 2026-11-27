<?php

namespace App\Domain\AI\Services;

use App\Domain\Administration\Services\AuditLogger;
use App\Domain\AI\Exceptions\AiInvalidOutput;
use App\Domain\AI\Exceptions\AiUnavailable;
use App\Models\AiGeneration;
use App\Models\LanguageStyle;
use App\Models\Question;
use App\Models\QuestionVariant;
use App\Models\User;
use App\Support\Text;
use Illuminate\Support\Collection;

/**
 * Generates stylised question variants once per (question version, style version, prompt version)
 * and stores them for review. Pages only ever read approved variants – no AI on page load.
 */
class QuestionStylizer
{
    public const OPERATION = 'question_stylize';

    public function __construct(private AiGateway $gateway, private AiStatus $status, private BudgetGuard $budget, private PromptRegistry $prompts, private OutputValidator $validator) {}

    /** @return Collection<int, LanguageStyle> styles usable for this question */
    public function stylesFor(Question $q): Collection
    {
        return LanguageStyle::active()->filter(fn (LanguageStyle $s) => $s->isGeneratable() && $s->supports('question') && (! $q->is_sensitive || $s->allowsSensitive()))->values();
    }

    public function generate(User $editor, Question $q, LanguageStyle $style): QuestionVariant
    {
        if (! $this->status->availableFor($editor)) {
            throw new AiUnavailable($this->status->unavailableReason(), 'not_configured');
        }
        $prompt = $this->prompts->production(self::OPERATION);
        $existing = QuestionVariant::where(['question_id' => $q->id, 'question_version' => $q->version, 'language_style_id' => $style->id, 'style_version' => $style->version, 'ai_prompt_version_id' => $prompt->id])->first();
        if ($existing) {
            return $existing; // generated once per combination
        }
        $this->budget->assertCanSpend(null);

        $key = CacheKey::make(['op' => self::OPERATION, 'q' => $q->id, 'qv' => $q->version, 'style' => $style->slug, 'sv' => $style->version, 'pv' => $prompt->id]);
        $generation = AiGeneration::create([
            'user_id' => $editor->id, 'operation' => self::OPERATION, 'question_id' => $q->id, 'language_style_id' => $style->id,
            'style_version' => $style->version, 'ai_prompt_version_id' => $prompt->id, 'input_hash' => CacheKey::inputHash($q->text),
            'cache_key' => $key, 'status' => 'pending',
        ]);
        $vars = [
            'question' => $q->text, 'language_style' => $style->name, 'style_instructions' => (string) $style->instructions,
            'style_examples' => collect($style->examples)->map(fn ($e) => '„'.($e['original'] ?? '').'“ → „'.($e['styled'] ?? '').'“')->implode("\n"),
            'sensitivity_level' => $q->is_sensitive ? 'vysoká' : 'běžná', 'repair_note' => '',
        ];

        $text = null;
        foreach ([null, 'Předchozí návrh neprošel kontrolou: zachovej význam, čísla, jména a otazník na konci.'] as $repair) {
            $vars['repair_note'] = (string) $repair;
            try {
                $r = $this->gateway->complete(self::OPERATION, $prompt, $vars, $editor, $generation->id);
            } catch (AiUnavailable $e) {
                $generation->update(['status' => 'failed', 'error' => $e->getMessage()]);
                throw $e;
            }
            $candidate = Text::clean((string) ($this->validator->parse($r['response']->text)['question'] ?? ''));
            $problem = $this->validator->check($q->text, $candidate, 300) ?? (str_ends_with($candidate, '?') ? null : 'není to otázka');
            if (! $problem) {
                $text = $candidate;
                break;
            }
            $this->gateway->logEvent(self::OPERATION, 'rejected', $editor, $generation->id, $prompt, '0', mb_substr($problem, 0, 60));
        }
        if (! $text) {
            $generation->update(['status' => 'rejected']);
            throw new AiInvalidOutput(['varianta otázky neprošla kontrolou']);
        }
        $generation->update(['status' => 'succeeded', 'ai_model_id' => $r['model']->id]);

        return QuestionVariant::create([
            'question_id' => $q->id, 'question_version' => $q->version, 'language_style_id' => $style->id, 'style_version' => $style->version,
            'ai_prompt_version_id' => $prompt->id, 'ai_generation_id' => $generation->id, 'text' => $text, 'status' => 'pending',
        ]);
    }

    public function review(User $editor, QuestionVariant $variant, bool $approve, ?string $editedText = null): void
    {
        if ($editedText !== null && ($t = Text::clean($editedText)) !== $variant->text) {
            if (! str_ends_with($t, '?') || Text::length($t) > 300) {
                throw new AiInvalidOutput(['upravená varianta musí být otázka do 300 znaků']);
            }
            $variant->text = $t;
        }
        $variant->status = $approve ? 'approved' : 'rejected';
        $variant->reviewed_by = $editor->id;
        $variant->reviewed_at = now();
        $variant->save();
        app(AuditLogger::class)->log('question_variant.'.($approve ? 'approved' : 'rejected'), $variant);
    }

    /** Approved variants of the current question version, keyed by style slug. @return Collection<string, QuestionVariant> */
    public static function approved(Question $q): Collection
    {
        return QuestionVariant::with('style')->where('question_id', $q->id)->where('question_version', $q->version)
            ->where('status', 'approved')->get()->filter(fn ($v) => $v->style?->is_active)->keyBy(fn ($v) => $v->style->slug);
    }
}
