<?php

namespace App\Domain\AI\Services;

use App\Domain\Answers\Contracts\VariantResolver;
use App\Models\AnswerVariant;
use App\Models\Question;
use App\Models\User;

/**
 * A variant is valid only for its owner, its question and the exact original it was generated
 * from (input HMAC) – the client can never smuggle an arbitrary "stylised" text in.
 */
class AiVariantResolver implements VariantResolver
{
    public function resolve(User $user, Question $question, string $original, int $variantId): ?array
    {
        $v = AnswerVariant::where('id', $variantId)->where('user_id', $user->id)->where('question_id', $question->id)
            ->where('input_hash', CacheKey::inputHash($original))->where('created_at', '>=', now()->subDay())->first();
        if (! $v) {
            return null;
        }
        $v->forceFill(['used_at' => now()])->save();

        return ['text' => $v->text, 'style_id' => $v->language_style_id];
    }
}
