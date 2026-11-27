<?php

namespace App\Domain\Answers\Events;

use App\Models\Answer;
use Illuminate\Foundation\Events\Dispatchable;

class AnswerPublished
{
    use Dispatchable;

    public function __construct(public Answer $answer) {}
}
