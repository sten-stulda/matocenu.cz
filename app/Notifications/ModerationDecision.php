<?php

namespace App\Notifications;

use App\Models\ModerationAction;
use Illuminate\Support\Str;

class ModerationDecision extends MtcNotification
{
    public function __construct(public ModerationAction $action) {}

    public function type(): string
    {
        return 'moderation';
    }

    public function title(): string
    {
        return match ($this->action->to_status) {
            'approved' => 'Tvůj příspěvek je zveřejněný',
            'hidden' => 'Tvůj příspěvek byl skrytý',
            'rejected' => 'Tvůj příspěvek nebyl schválen',
            default => 'Tvůj příspěvek čeká na kontrolu',
        };
    }

    public function body(): string
    {
        $excerpt = $this->action->content()?->moderationExcerpt();

        return ($excerpt ? '„'.Str::limit($excerpt, 80).'“. ' : '').($this->action->reason ? 'Důvod: '.$this->action->reason.'. ' : '')
            .(in_array($this->action->to_status, ['hidden', 'rejected', 'under_review'], true) ? 'Pokud nesouhlasíš, můžeš se odvolat.' : '');
    }

    public function url(): string
    {
        return route('account.moderation');
    }
}
