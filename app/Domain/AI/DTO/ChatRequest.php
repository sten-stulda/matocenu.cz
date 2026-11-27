<?php

namespace App\Domain\AI\DTO;

final readonly class ChatRequest
{
    public function __construct(
        public string $model,
        public string $system,
        public string $user,
        public int $maxTokens = 400,
        public ?float $temperature = null,
        public bool $json = true,
        public int $timeout = 20,
    ) {}
}
