<?php

namespace App\Notifications;

class NeedsDigestNotification extends MtcNotification
{
    /** @param list<string> $titles */
    public function __construct(public array $titles, public int $count) {}

    public function type(): string
    {
        return 'followed_topic';
    }

    public function title(): string
    {
        return 'Nové potřeby ve sledovaných tématech';
    }

    public function body(): string
    {
        return cz_plural($this->count, 'nová potřeba', 'nové potřeby', 'nových potřeb').': '.implode(' · ', array_slice($this->titles, 0, 5));
    }

    public function url(): string
    {
        return route('opportunities.index');
    }
}
