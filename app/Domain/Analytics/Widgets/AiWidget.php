<?php

namespace App\Domain\Analytics\Widgets;

use App\Domain\AI\Services\AiCostReport;
use App\Domain\AI\Services\AiStatus;
use App\Domain\Analytics\Contracts\DashboardWidget;
use App\Models\LanguageStyle;
use Illuminate\Support\Facades\DB;

class AiWidget implements DashboardWidget
{
    public function permission(): string
    {
        return 'ai.costs.view';
    }

    public function view(): string
    {
        return 'pages.admin.widgets.ai';
    }

    public function data(): array
    {
        return [
            'r' => app(AiCostReport::class)->summary(),
            'aiOn' => app(AiStatus::class)->models() !== [],
            'styles' => DB::table('answers')->join('language_styles', 'language_styles.id', '=', 'answers.language_style_id')
                ->where('answers.published_at', '>=', now()->subDays(30))->groupBy('language_styles.name')
                ->selectRaw('language_styles.name, count(*) as n')->orderByDesc('n')->limit(5)->get(),
            'stylesTotal' => LanguageStyle::where('is_active', true)->count(),
        ];
    }
}
