<?php

namespace App\Domain\Questions\Events;

use App\Models\Question;
use Illuminate\Foundation\Events\Dispatchable;

/** A new question version exists – stylised variants of older versions become stale (Etapa C). */
class QuestionTextChanged
{
    use Dispatchable;

    public function __construct(public Question $question) {}
}
