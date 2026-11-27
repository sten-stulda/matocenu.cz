<?php

namespace App\Domain\AI\DTO;

final readonly class ChatResponse
{
    public function __construct(
        public string $text,
        public int $inputTokens,
        public int $outputTokens,
        public int $extraTokens = 0,
        /** Provider-reported cost in USD (OpenRouter usage accounting), null = estimate from prices. */
        public ?float $reportedCostUsd = null,
        public ?string $model = null,
    ) {}
}
