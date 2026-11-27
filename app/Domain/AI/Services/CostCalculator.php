<?php

namespace App\Domain\AI\Services;

use App\Domain\Administration\Services\Settings;
use App\Domain\AI\DTO\ChatResponse;
use App\Models\AiModel;

/** Exact decimal arithmetic (bcmath) – money is never a float in storage. */
class CostCalculator
{
    public function __construct(private Settings $settings) {}

    /** @return array{usd: string, czk: string, estimate: bool} */
    public function cost(AiModel $model, ChatResponse $r): array
    {
        if ($r->reportedCostUsd !== null) {
            $usd = number_format($r->reportedCostUsd, 6, '.', '');
            $estimate = false;
        } elseif ($model->hasPrices()) {
            $in = bcmul((string) $r->inputTokens, (string) $model->input_price_per_mtok, 10);
            $out = bcmul((string) ($r->outputTokens + $r->extraTokens), (string) $model->output_price_per_mtok, 10);
            $usd = bcdiv(bcadd($in, $out, 10), '1000000', 6);
            $estimate = true;
        } else {
            $usd = '0.000000';
            $estimate = true;
        }

        return ['usd' => $usd, 'czk' => $this->toCzk($usd), 'estimate' => $estimate];
    }

    public function toCzk(string $usd): string
    {
        return bcmul($usd, $this->settings->decimal('ai.usd_czk_rate'), 4);
    }
}
