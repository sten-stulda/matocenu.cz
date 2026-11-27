<?php

namespace App\Domain\AI\Exceptions;

use RuntimeException;

/** Provider not configured, unreachable, timed out or returned an error. */
class AiUnavailable extends RuntimeException
{
    public function __construct(string $message, public string $type = 'unavailable', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
