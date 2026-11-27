<?php

namespace App\Domain\AI\Exceptions;

use RuntimeException;

class AiInvalidOutput extends RuntimeException
{
    /** @param list<string> $problems */
    public function __construct(public array $problems)
    {
        parent::__construct('AI výstup neprošel kontrolou: '.implode('; ', $problems));
    }
}
