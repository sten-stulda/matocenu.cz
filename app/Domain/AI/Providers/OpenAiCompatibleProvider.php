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

/**
 * POST {base}/chat/completions – OpenRouter, OpenAI, Ollama (/v1) and other OpenAI-compatible servers.
 * OpenRouter additionally reports the real cost when "usage": {"include": true} is sent.
 */
class OpenAiCompatibleProvider implements AIProviderInterface
{
    public function __construct(protected AiProvider $provider) {}

    public function chat(ChatRequest $request): ChatResponse
    {
        $base = rtrim((string) $this->provider->baseUrl(), '/');
        UrlGuard::assertAllowed($base, $this->provider->driver);
        $key = $this->provider->resolvedApiKey();
        if (! $key && ! in_array($this->provider->driver, ['ollama', 'openai_compatible'], true)) {
            throw new AiUnavailable('Chybí API klíč poskytovatele '.$this->provider->name.'.', 'not_configured');
        }

        $payload = [
            'model' => $request->model,
            'messages' => [
                ['role' => 'system', 'content' => $request->system],
                ['role' => 'user', 'content' => $request->user],
            ],
            'max_tokens' => $request->maxTokens,
        ];
        if ($request->temperature !== null) {
            $payload['temperature'] = $request->temperature;
        }
        if ($request->json) {
            $payload['response_format'] = ['type' => 'json_object'];
        }
        if ($this->provider->driver === 'openrouter') {
            // Usage accounting: the response then carries the real cost (usage.cost).
            $payload['usage'] = ['include' => true];
        }

        try {
            $res = Http::withToken((string) $key)
                ->withHeaders($this->provider->driver === 'openrouter' ? ['HTTP-Referer' => config('app.url'), 'X-Title' => 'MaToCenu.cz'] : [])
                ->acceptJson()->timeout($request->timeout)->connectTimeout(5)
                ->post($base.'/chat/completions', $payload);
        } catch (ConnectionException $e) {
            throw new AiUnavailable('Poskytovatel AI neodpovídá.', 'timeout', $e);
        }
        if ($res->status() === 402) {
            throw new AiUnavailable('U poskytovatele AI došel kredit.', 'payment_required');
        }
        if ($res->status() === 429) {
            throw new AiUnavailable('Poskytovatel AI omezil počet požadavků.', 'rate_limited');
        }
        if (! $res->successful()) {
            throw new AiUnavailable('Poskytovatel AI vrátil chybu '.$res->status().'.', 'http_'.$res->status());
        }

        $text = (string) ($res->json('choices.0.message.content') ?? '');
        if ($text === '') {
            throw new AiUnavailable('Poskytovatel AI vrátil prázdnou odpověď.', 'empty');
        }
        $usage = (array) $res->json('usage', []);
        $reasoning = (int) data_get($usage, 'completion_tokens_details.reasoning_tokens', 0);

        return new ChatResponse(
            text: $text,
            inputTokens: (int) ($usage['prompt_tokens'] ?? 0),
            outputTokens: (int) ($usage['completion_tokens'] ?? 0) - $reasoning,
            extraTokens: $reasoning,
            reportedCostUsd: isset($usage['cost']) && is_numeric($usage['cost']) ? (float) $usage['cost'] : null,
            model: $res->json('model'),
        );
    }

    public function verify(): array
    {
        $base = rtrim((string) $this->provider->baseUrl(), '/');
        try {
            UrlGuard::assertAllowed($base, $this->provider->driver);
            $req = Http::withToken((string) $this->provider->resolvedApiKey())->acceptJson()->timeout(10);
            // OpenRouter exposes key info; others list models.
            $res = $this->provider->driver === 'openrouter' ? $req->get($base.'/key') : $req->get($base.'/models');
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Nepodařilo se spojit: '.$e->getMessage()];
        }
        if (! $res->successful()) {
            return ['ok' => false, 'message' => 'Odpověď '.$res->status().' – zkontroluj klíč a adresu.'];
        }
        if ($this->provider->driver === 'openrouter') {
            $limit = $res->json('data.limit');
            $usage = $res->json('data.usage');

            return ['ok' => true, 'message' => 'Klíč platný. Utraceno u OpenRouteru: '.number_format((float) $usage, 4).' USD'.($limit !== null ? ' z limitu '.$limit.' USD' : '').'.'];
        }

        return ['ok' => true, 'message' => 'Spojení funguje.'];
    }
}
