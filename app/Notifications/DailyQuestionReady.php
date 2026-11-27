<?php

namespace App\Notifications;

use App\Models\DailyQuestion;

class DailyQuestionReady extends MtcNotification
{
    public function __construct(public DailyQuestion $entry) {}

    public function type(): string
    {
        return 'daily_question';
    }

    public function title(): string
    {
        return 'Nová otázka dne';
    }

    public function body(): string
    {
        return '„'.$this->entry->question->text.'“ Odpověz po svém – stačí sto znaků.';
    }

    public function url(): string
    {
        return $this->entry->question->url();
    }
}
