<?php

namespace App\Domain\AI\Services;

use App\Domain\Administration\Services\AuditLogger;
use App\Models\AiModel;
use Illuminate\Support\Facades\Http;

/** Refreshes model prices from OpenRouter's public catalog (GET /api/v1/models, no key needed). */
class ModelCatalog
{
    /** @return array{updated: int, missing: list<string>} */
    public function syncOpenRouterPrices(): array
    {
        $res = Http::acceptJson()->timeout(20)->get('https://openrouter.ai/api/v1/models');
        if (! $res->successful()) {
            throw new \RuntimeException('Katalog OpenRouter není dostupný ('.$res->status().').');
        }
        $catalog = collect((array) $res->json('data', []))->keyBy('id');
        $updated = 0;
        $missing = [];
        foreach (AiModel::whereHas('provider', fn ($q) => $q->where('driver', 'openrouter'))->get() as $m) {
            $entry = $catalog->get($m->model);
            if (! $entry) {
                $missing[] = $m->model;

                continue;
            }
            $m->forceFill([
                'input_price_per_mtok' => bcmul((string) ($entry['pricing']['prompt'] ?? '0'), '1000000', 6),
                'output_price_per_mtok' => bcmul((string) ($entry['pricing']['completion'] ?? '0'), '1000000', 6),
                'supports_temperature' => in_array('temperature', (array) ($entry['supported_parameters'] ?? []), true),
                'prices_synced_at' => now(),
            ])->save();
            $updated++;
        }
        app(AuditLogger::class)->log('ai.prices_synced', null, ['updated' => $updated, 'missing' => $missing]);

        return ['updated' => $updated, 'missing' => $missing];
    }
}
