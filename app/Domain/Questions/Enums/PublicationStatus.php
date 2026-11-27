<?php

namespace App\Domain\Questions\Enums;

enum PublicationStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Closed = 'closed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Koncept',
            self::Scheduled => 'Naplánováno',
            self::Published => 'Zveřejněno',
            self::Closed => 'Uzavřeno pro odpovědi',
            self::Archived => 'Archiv',
        };
    }
}
