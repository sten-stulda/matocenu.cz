<?php

namespace App\Domain\Needs\Enums;

enum NeedType: string
{
    case Problem = 'problem';
    case MissingService = 'missing_service';
    case MissingProduct = 'missing_product';
    case Municipality = 'municipality';
    case BusinessIdea = 'business_idea';
    case JointSolution = 'joint_solution';

    public function label(): string
    {
        return match ($this) {
            self::Problem => 'Skutečný problém',
            self::MissingService => 'Chybějící služba',
            self::MissingProduct => 'Chybějící produkt',
            self::Municipality => 'Potřeba obce',
            self::BusinessIdea => 'Podnikatelský nápad',
            self::JointSolution => 'Návrh společného řešení',
        };
    }
}
