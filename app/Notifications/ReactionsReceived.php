<?php

namespace App\Notifications;

use App\Models\Answer;
use Illuminate\Support\Str;

class ReactionsReceived extends MtcNotification
{
    public function __construct(public Answer $answer, public string $reaction) {}

    public function type(): string
    {
        return 'answer_reaction';
    }

    public function title(): string
    {
        return 'Tvá odpověď sbírá reakce';
    }

    public function body(): string
    {
        return '„'.Str::limit($this->answer->display_text, 80).'“ – „'.$this->reaction.'“. Celkem reakcí: '.$this->answer->reactions_count.'.';
    }

    public function url(): string
    {
        return $this->answer->url();
    }
}
