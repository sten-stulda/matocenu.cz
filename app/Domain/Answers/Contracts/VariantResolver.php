<?php

namespace App\Domain\Answers\Contracts;

use App\Models\Question;
use App\Models\User;

interface VariantResolver
{
    /** Display text of the chosen AI variant, or null when it is not valid for this user/answer. */
    public function resolve(User $user, Question $question, string $original, int $variantId): ?string;
}
