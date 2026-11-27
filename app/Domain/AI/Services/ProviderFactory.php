<?php

namespace App\Domain\AI\Services;

use App\Domain\AI\Contracts\AIProviderInterface;
use App\Domain\AI\Providers\AnthropicProvider;
use App\Domain\AI\Providers\FakeProvider;
use App\Domain\AI\Providers\GeminiProvider;
use App\Domain\AI\Providers\OpenAiCompatibleProvider;
use App\Models\AiProvider;

class ProviderFactory
{
    public function make(AiProvider $provider): AIProviderInterface
    {
        return match ($provider->driver) {
            'openrouter', 'openai', 'ollama', 'openai_compatible' => new OpenAiCompatibleProvider($provider),
            'anthropic' => new AnthropicProvider($provider),
            'gemini' => new GeminiProvider($provider),
            default => new FakeProvider,
        };
    }
}
