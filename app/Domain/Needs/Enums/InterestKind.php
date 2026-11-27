<?php

namespace App\Domain\Needs\Enums;

enum InterestKind: string
{
    case Like = 'like';
    case NeedToo = 'need_too';
    case WouldUse = 'would_use';
    case WouldPay = 'would_pay';
    case CanDeliver = 'can_deliver';

    public function label(): string
    {
        return match ($this) {
            self::Like => 'Líbí se mi to',
            self::NeedToo => 'Taky to potřebuju',
            self::WouldUse => 'Službu bych využil/a',
            self::WouldPay => 'Zaplatil/a bych',
            self::CanDeliver => 'Dokážu to realizovat',
        };
    }

    public function counter(): string
    {
        return $this->value.'_count';
    }
}
