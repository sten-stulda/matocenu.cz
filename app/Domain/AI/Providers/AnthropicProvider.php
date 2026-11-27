<?php

namespace App\Domain\AI\Providers;

use App\Domain\AI\Contracts\AIProviderInterface;
use App\Domain\AI\DTO\ChatRequest;
use App\Domain\AI\DTO\ChatResponse;
use App\Domain\AI\Exceptions\AiUnavailable;
use App\Domain\AI\Services\UrlGuard;
use App\Models\AiProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** POST {base}/messages (Anthropic Messages API, anthropic-version 2023-06-01). */
class AnthropicProvider implements AIProviderInterface
{
    public function __construct(private AiProvider $provider) {}

    public function chat(ChatRequest $request): ChatResponse
    {
        $base = rtrim((string) $this->provider->baseUrl(), '/');
        UrlGuard::assertAllowed($base, 'anthropic');
        $key = $this->provider->resolvedApiKey() ?: throw new AiUnavailable('Chybí API klíč Anthropic.', 'not_configured');
        $payload = [
            'model' => $request->model,
            'max_tokens' => $request->maxTokens,
            'system' => $request->system.($request->json ? "\nOdpověz pouze platným JSON objektem." : ''),
            'messages' => [['role' => 'user', 'content' => $request->user]],
        ];
        if ($request->temperature !== null) {
            $payload['temperature'] = $request->temperature;
        }
        try {
            $res = Http::withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])
                ->acceptJson()->timeout($request->timeout)->connectTimeout(5)->post($base.'/messages', $payload);
        } catch (ConnectionException $e) {
            throw new AiUnavailable('Anthropic neodpovídá.', 'timeout', $e);
        }
        if (! $res->successful()) {
            throw new AiUnavailable('Anthropic vrátil chybu '.$res->status().'.', 'http_'.$res->status());
        }
        $text = collect((array) $res->json('content', []))->where('type', 'text')->pluck('text')->implode('');

        return new ChatResponse($text, (int) $res->json('usage.input_tokens', 0), (int) $res->json('usage.output_tokens', 0), model: $res->json('model'));
    }

    public function verify(): array
    {
        try {
            $res = Http::withHeaders(['x-api-key' => (string) $this->provider->resolvedApiKey(), 'anthropic-version' => '2023-06-01'])
                ->acceptJson()->timeout(10)->get(rtrim((string) $this->provider->baseUrl(), '/').'/models');
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Nepodařilo se spojit.'];
        }

        return $res->successful() ? ['ok' => true, 'message' => 'Klíč platný.'] : ['ok' => false, 'message' => 'Odpověď '.$res->status().'.'];
    }
}
