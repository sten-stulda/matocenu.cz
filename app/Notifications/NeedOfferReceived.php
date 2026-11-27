<?php

namespace App\Notifications;

use App\Models\NeedOffer;
use Illuminate\Support\Str;

class NeedOfferReceived extends MtcNotification
{
    public function __construct(public NeedOffer $offer) {}

    public function type(): string
    {
        return 'need_offer';
    }

    public function title(): string
    {
        return 'Někdo nabízí řešení tvé potřeby';
    }

    public function body(): string
    {
        return '„'.Str::limit($this->offer->need->title, 80).'“ – '.Str::limit($this->offer->description, 140);
    }

    public function url(): string
    {
        return $this->offer->need->url().'#nabidky';
    }
}
