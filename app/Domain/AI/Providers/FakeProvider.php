<?php

namespace App\Domain\AI\Providers;

use App\Domain\AI\Contracts\AIProviderInterface;
use App\Domain\AI\DTO\ChatRequest;
use App\Domain\AI\DTO\ChatResponse;

/**
 * Deterministic offline provider for tests, CI and the "testovací režim" (no API key).
 * It does NOT write humour – it marks the text so nobody mistakes it for real AI output.
 * Behaviour can be scripted in tests via FakeProvider::$script.
 */
class FakeProvider implements AIProviderInterface
{
    /** @var list<string|\Throwable> queued raw responses for tests */
    public static array $script = [];

    public static int $calls = 0;

    public function chat(ChatRequest $request): ChatResponse
    {
        static::$calls++;
        if (static::$script) {
            $next = array_shift(static::$script);
            if ($next instanceof \Throwable) {
                throw $next;
            }

            return new ChatResponse($next, 120, 40);
        }
        preg_match('/<original>(.*?)<\/original>/su', $request->user, $m);
        $original = trim($m[1] ?? 'text');
        preg_match('/<style>(.*?)<\/style>/su', $request->user, $s);
        $style = trim($s[1] ?? 'styl');
        $variants = ["[{$style} A] {$original}", "[{$style} B] {$original}", "[{$style} C] {$original}"];
        $isQuestion = ! str_contains($request->system, '"variants"');

        return new ChatResponse(json_encode($isQuestion ? ['question' => "[{$style}] {$original}"] : ['variants' => $variants], JSON_UNESCAPED_UNICODE), 120, 40);
    }

    public function verify(): array
    {
        return ['ok' => true, 'message' => 'Testovací režim – žádná skutečná AI, výstupy jsou jen označené kopie originálu.'];
    }

    public static function reset(): void
    {
        static::$script = [];
        static::$calls = 0;
    }
}
