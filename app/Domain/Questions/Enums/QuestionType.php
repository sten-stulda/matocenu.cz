<?php

namespace App\Domain\Questions\Enums;

enum QuestionType: string
{
    case Open = 'open';
    case YesNo = 'yes_no';
    case Choice = 'choice';
    case Numeric = 'numeric';
    case Poll = 'poll';
    case Local = 'local';
    case Need = 'need';
    case Humor = 'humor';
    case Daily = 'daily';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Otevřená otázka',
            self::YesNo => 'Ano / ne',
            self::Choice => 'Výběr možností',
            self::Numeric => 'Číselná odpověď',
            self::Poll => 'Anketa',
            self::Local => 'Lokální otázka',
            self::Need => 'Skutečná potřeba',
            self::Humor => 'Humorná otázka',
            self::Daily => 'Otázka dne',
        };
    }

    /** Types whose answers pick one of question_options. */
    public function hasOptions(): bool
    {
        return in_array($this, [self::Choice, self::Poll, self::YesNo], true);
    }

    public function requiresLocation(): bool
    {
        return $this === self::Local;
    }
}
