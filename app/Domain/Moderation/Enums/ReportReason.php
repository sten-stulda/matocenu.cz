<?php

namespace App\Domain\Moderation\Enums;

enum ReportReason: string
{
    case Spam = 'spam';
    case Insult = 'insult';
    case Harassment = 'harassment';
    case Discrimination = 'discrimination';
    case PersonalData = 'personal_data';
    case Fraud = 'fraud';
    case Copyright = 'copyright';
    case Inappropriate = 'inappropriate';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spam => 'Spam',
            self::Insult => 'Urážky',
            self::Harassment => 'Obtěžování',
            self::Discrimination => 'Diskriminační obsah',
            self::PersonalData => 'Osobní údaje',
            self::Fraud => 'Podvod',
            self::Copyright => 'Porušení autorských práv',
            self::Inappropriate => 'Nevhodný obsah',
            self::Other => 'Jiné',
        };
    }

    public function risk(): string
    {
        return match ($this) {
            self::Harassment, self::Discrimination, self::PersonalData, self::Fraud => 'high',
            self::Insult, self::Spam, self::Copyright => 'medium',
            default => 'low',
        };
    }
}
