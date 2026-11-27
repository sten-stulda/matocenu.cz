<?php

namespace App\Notifications;

use App\Models\Achievement;

class AchievementEarned extends MtcNotification
{
    public function __construct(public Achievement $achievement) {}

    public function type(): string
    {
        return 'achievement';
    }

    public function title(): string
    {
        return 'Nové ocenění: '.$this->achievement->name;
    }

    public function body(): string
    {
        return $this->achievement->description;
    }

    public function url(): string
    {
        return route('account.profile').'?tab=oceneni';
    }
}
