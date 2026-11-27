<?php

namespace App\Domain\Answers\DTO;

final readonly class AnswerData
{
    public function __construct(
        public string $originalText,
        public bool $isAnonymous = false,
        public bool $showOriginal = false,
        public ?int $optionId = null,
        public ?string $numericValue = null,
        /** Chosen AI variant (Etapa C) – the stylised display text comes from it, never from the client. */
        public ?int $variantId = null,
    ) {}
}
