<?php

namespace App\Domain\Moderation\Enums;

enum ModerationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Hidden = 'hidden';
    case Reported = 'reported';
    case UnderReview = 'under_review';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Čeká na schválení',
            self::Approved => 'Schváleno',
            self::Rejected => 'Zamítnuto',
            self::Hidden => 'Skryto',
            self::Reported => 'Nahlášeno',
            self::UnderReview => 'V přezkumu',
        };
    }

    /** Statuses visible to the public. Reported content stays visible until a moderator or the auto-hide threshold acts. */
    public static function publicValues(): array
    {
        return [self::Approved->value, self::Reported->value];
    }

    public function isPublic(): bool
    {
        return in_array($this->value, self::publicValues(), true);
    }
}
