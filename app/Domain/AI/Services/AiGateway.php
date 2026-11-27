<?php

namespace App\Domain\AI\Services;

use App\Domain\AI\DTO\ChatRequest;
use App\Domain\AI\DTO\ChatResponse;
use App\Domain\AI\Exceptions\AiUnavailable;
use App\Models\AiModel;
use App\Models\AiPromptVersion;
use App\Models\AiUsageLog;
use App\Models\User;

/**
 * Calls the primary model, retries transient failures, falls back to the fallback model
 * and logs every attempt (tokens, cost, latency, status) without storing prompt text.
 */
class AiGateway
{
    public function __construct(private ProviderFactory $factory, private CostCalculator $costs, private AiStatus $status, private PromptRegistry $prompts) {}

    /**
     * @param  array<string, scalar|null>  $vars
     * @return array{response: ChatResponse, model: AiModel, cost_czk: string}
     */
    public function complete(string $operation, AiPromptVersion $prompt, array $vars, ?User $user = null, ?int $generationId = null, ?float $temperatureBoost = null, ?AiModel $only = null): array
    {
        $models = $only ? [$only->loadMissing('provider')] : $this->status->models();
        if (! $models) {
            throw new AiUnavailable('Není nastaven žádný aktivní AI model.', 'not_configured');
        }
        $system = $this->prompts->render($prompt->system_template, $vars);
        $userMsg = $this->prompts->render($prompt->user_template, $vars);
        $last = null;

        foreach ($models as $model) {
            $provider = $model->provider;
            $client = $this->factory->make($provider);
            $attempts = max(1, $provider->max_retries + 1);
            for ($i = 0; $i < $attempts; $i++) {
                $temp = $model->supports_temperature && $model->temperature !== null ? (float) $model->temperature + ($temperatureBoost ?? 0) : null;
                $started = hrtime(true);
                try {
                    $r = $client->chat(new ChatRequest(
                        model: $model->model, system: $system, user: $userMsg,
                        maxTokens: $model->max_output_tokens, temperature: $temp !== null ? min(1.5, $temp) : null,
                        json: true, timeout: $provider->timeout_seconds,
                    ));
                    $cost = $this->costs->cost($model, $r);
                    $this->log($operation, $model, $prompt, $user, $generationId, $started, 'ok', null, $r, $cost);

                    return ['response' => $r, 'model' => $model, 'cost_czk' => $cost['czk']];
                } catch (AiUnavailable $e) {
                    $last = $e;
                    $this->log($operation, $model, $prompt, $user, $generationId, $started, 'error', $e->type);
                    if (in_array($e->type, ['not_configured', 'payment_required', 'http_401', 'http_403', 'http_400'], true)) {
                        break; // retrying will not help – try the fallback model
                    }
                }
            }
        }

        throw $last ?? new AiUnavailable('AI je nedostupná.');
    }

    /** @param array{usd: string, czk: string, estimate: bool}|null $cost */
    private function log(string $operation, AiModel $model, AiPromptVersion $prompt, ?User $user, ?int $generationId, int $started, string $status, ?string $error, ?ChatResponse $r = null, ?array $cost = null): void
    {
        AiUsageLog::create([
            'ai_generation_id' => $generationId,
            'user_id' => $user?->id,
            'provider' => $model->provider->slug,
            'model' => $model->model,
            'operation' => $operation,
            'ai_prompt_version_id' => $prompt->id,
            'input_tokens' => $r ? $r->inputTokens : 0,
            'output_tokens' => $r ? $r->outputTokens : 0,
            'extra_tokens' => $r ? $r->extraTokens : 0,
            'cost_usd' => $cost['usd'] ?? '0',
            'cost_czk' => $cost['czk'] ?? '0',
            'cost_is_estimate' => $cost['estimate'] ?? true,
            'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            'status' => $status,
            'error_type' => $error,
        ]);
    }

    /** Record a rejected output (validation) or a cache hit (with the saving). */
    public function logEvent(string $operation, string $status, ?User $user, ?int $generationId, ?AiPromptVersion $prompt = null, string $savedCzk = '0', ?string $error = null): void
    {
        AiUsageLog::create([
            'ai_generation_id' => $generationId, 'user_id' => $user?->id, 'operation' => $operation,
            'ai_prompt_version_id' => $prompt?->id, 'status' => $status, 'cache_hit' => $status === 'cache_hit',
            'saved_czk' => $savedCzk, 'error_type' => $error,
        ]);
    }
}
