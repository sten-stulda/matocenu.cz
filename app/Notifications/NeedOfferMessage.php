<?php

namespace App\Notifications;

use App\Models\NeedOffer;
use Illuminate\Support\Str;

/** Relayed message from a provider – sent only to people who consented to be contacted. */
class NeedOfferMessage extends MtcNotification
{
    public function __construct(public NeedOffer $offer, public string $message) {}

    public function type(): string
    {
        return 'need_offer';
    }

    public function title(): string
    {
        return 'Realizátor ti píše k potřebě „'.Str::limit($this->offer->need->title, 50).'“';
    }

    public function body(): string
    {
        return Str::limit($this->message, 300).' (Dostáváš to, protože jsi souhlasil/a s kontaktem. Souhlas odvoláš u potřeby.)';
    }

    public function url(): string
    {
        return $this->offer->need->url().'#nabidky';
    }
}
