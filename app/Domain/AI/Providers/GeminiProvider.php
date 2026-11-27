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

/** POST {base}/models/{model}:generateContent (Gemini API, key in x-goog-api-key header). */
class GeminiProvider implements AIProviderInterface
{
    public function __construct(private AiProvider $provider) {}

    public function chat(ChatRequest $request): ChatResponse
    {
        $base = rtrim((string) $this->provider->baseUrl(), '/');
        UrlGuard::assertAllowed($base, 'gemini');
        $key = $this->provider->resolvedApiKey() ?: throw new AiUnavailable('Chybí API klíč Gemini.', 'not_configured');
        $config = ['maxOutputTokens' => $request->maxTokens];
        if ($request->temperature !== null) {
            $config['temperature'] = $request->temperature;
        }
        if ($request->json) {
            $config['responseMimeType'] = 'application/json';
        }
        try {
            $res = Http::withHeaders(['x-goog-api-key' => $key])->acceptJson()->timeout($request->timeout)->connectTimeout(5)
                ->post($base.'/models/'.rawurlencode($request->model).':generateContent', [
                    'systemInstruction' => ['parts' => [['text' => $request->system]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $request->user]]]],
                    'generationConfig' => $config,
                ]);
        } catch (ConnectionException $e) {
            throw new AiUnavailable('Gemini neodpovídá.', 'timeout', $e);
        }
        if (! $res->successful()) {
            throw new AiUnavailable('Gemini vrátil chybu '.$res->status().'.', 'http_'.$res->status());
        }
        $text = collect((array) $res->json('candidates.0.content.parts', []))->pluck('text')->implode('');

        return new ChatResponse($text, (int) $res->json('usageMetadata.promptTokenCount', 0), (int) $res->json('usageMetadata.candidatesTokenCount', 0), (int) $res->json('usageMetadata.thoughtsTokenCount', 0));
    }

    public function verify(): array
    {
        try {
            $res = Http::withHeaders(['x-goog-api-key' => (string) $this->provider->resolvedApiKey()])->timeout(10)->get(rtrim((string) $this->provider->baseUrl(), '/').'/models');
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'Nepodařilo se spojit.'];
        }

        return $res->successful() ? ['ok' => true, 'message' => 'Klíč platný.'] : ['ok' => false, 'message' => 'Odpověď '.$res->status().'.'];
    }
}
