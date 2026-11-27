<?php

namespace App\Domain\AI\Contracts;

use App\Domain\AI\DTO\ChatRequest;
use App\Domain\AI\DTO\ChatResponse;
use App\Domain\AI\Exceptions\AiUnavailable;

/**
 * Uniform interface for language-model providers. Implementations translate the request to
 * the provider's real HTTP API and normalise usage numbers; they never invent capabilities.
 */
interface AIProviderInterface
{
    /** @throws AiUnavailable */
    public function chat(ChatRequest $request): ChatResponse;

    /** Lightweight credential / reachability check for the admin UI. @return array{ok: bool, message: string} */
    public function verify(): array;
}
