<?php

namespace App\Notifications;

use App\Models\Answer;
use Illuminate\Support\Str;

class QuestionAnswered extends MtcNotification
{
    public function __construct(public Answer $answer) {}

    public function type(): string
    {
        return 'question_answered';
    }

    public function title(): string
    {
        return 'Nová odpověď na tvou otázku';
    }

    public function body(): string
    {
        return '„'.Str::limit($this->answer->question->text, 80).'“ – „'.$this->answer->display_text.'“';
    }

    public function url(): string
    {
        return $this->answer->url();
    }
}
