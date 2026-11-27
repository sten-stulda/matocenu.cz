<?php

namespace App\Domain\Questions\Events;

use App\Models\DailyQuestion;
use Illuminate\Foundation\Events\Dispatchable;

class DailyQuestionPublished
{
    use Dispatchable;

    public function __construct(public DailyQuestion $entry) {}
}
