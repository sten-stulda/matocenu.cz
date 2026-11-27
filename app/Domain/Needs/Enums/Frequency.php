<?php

namespace App\Domain\Needs\Enums;

enum Frequency: string
{
    case Once = 'once';
    case PerVisit = 'per_visit';
    case PerHour = 'per_hour';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Once => 'jednorázově',
            self::PerVisit => 'za návštěvu',
            self::PerHour => 'za hodinu',
            self::Weekly => 'týdně',
            self::Monthly => 'měsíčně',
            self::Yearly => 'ročně',
        };
    }

    public function short(): string
    {
        return match ($this) {
            self::Once => 'jednorázově',
            self::PerVisit => '/ návštěva',
            self::PerHour => '/ hod',
            self::Weekly => '/ týden',
            self::Monthly => '/ měsíc',
            self::Yearly => '/ rok',
        };
    }
}
