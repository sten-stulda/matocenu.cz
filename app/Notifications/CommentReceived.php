<?php

namespace App\Notifications;

use App\Models\Answer;
use App\Models\Comment;
use App\Models\Need;
use App\Models\Question;
use Illuminate\Support\Str;

class CommentReceived extends MtcNotification
{
    public function __construct(public Comment $comment, public bool $isReply = false) {}

    public function type(): string
    {
        return 'comment';
    }

    public function title(): string
    {
        return $this->isReply ? 'Někdo odpověděl na tvůj komentář' : 'Nový komentář k tvému příspěvku';
    }

    public function body(): string
    {
        return $this->comment->user->name.': „'.Str::limit($this->comment->body, 120).'“';
    }

    public function url(): string
    {
        $target = $this->comment->target();

        return match (true) {
            $target instanceof Answer => $target->url().'#komentare',
            $target instanceof Question => $target->url().'#komentare',
            $target instanceof Need => $target->url().'#komentare',
            default => route('home'),
        };
    }
}
