<?php

namespace App\Domain\Answers\Services;

use App\Domain\Administration\Services\Settings;
use App\Support\Text;

class AnswerLength
{
    public function __construct(private Settings $settings) {}

    public function max(): int
    {
        return max(10, $this->settings->int('answers.max_length'));
    }

    /** @return string|null error message */
    public function validate(string $text): ?string
    {
        $len = Text::length($text);
        if ($len === 0) {
            return 'Napiš aspoň jedno slovo.';
        }
        if ($len > $this->max()) {
            return "Odpověď může mít nejvýše {$this->max()} znaků včetně mezer (teď má {$len}).";
        }

        return null;
    }
}
