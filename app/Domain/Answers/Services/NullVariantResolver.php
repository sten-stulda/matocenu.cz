<?php

namespace App\Domain\Answers\Services;

use App\Domain\Answers\Contracts\VariantResolver;
use App\Models\Question;
use App\Models\User;

/** Used while the AI module is not available – no variant is ever accepted. */
class NullVariantResolver implements VariantResolver
{
    public function resolve(User $user, Question $question, string $original, int $variantId): ?array
    {
        return null;
    }
}
