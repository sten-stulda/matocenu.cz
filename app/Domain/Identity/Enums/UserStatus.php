<?php

namespace App\Domain\Identity\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktivní',
            self::Suspended => 'Pozastavený',
            self::Deleted => 'Smazaný',
        };
    }
}
