<?php

namespace App\Domain\Analytics\Widgets;

use App\Domain\Analytics\Contracts\DashboardWidget;
use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Moderation\Services\QueueCount;
use App\Models\Answer;
use App\Models\DailyQuestion;
use App\Models\Question;
use App\Models\Reaction;
use App\Models\Report;
use Illuminate\Support\Facades\DB;

class CommunityWidget implements DashboardWidget
{
    public function permission(): string
    {
        return 'admin.access';
    }

    public function view(): string
    {
        return 'pages.admin.widgets.community';
    }

    public function data(): array
    {
        $today = now(config('matocenu.display_timezone'))->startOfDay();
        $answersToday = Answer::where('published_at', '>=', $today);

        return [
            'c' => [
                'questions' => Question::count(),
                'questions_today' => Question::where('created_at', '>=', $today)->count(),
                'answers' => Answer::count(),
                'answers_today' => (clone $answersToday)->count(),
                'stylized_share' => ($n = (clone $answersToday)->count()) ? round((clone $answersToday)->where('is_stylized', true)->count() / $n * 100) : null,
                'reactions' => Reaction::count(),
                'reactions_today' => Reaction::where('created_at', '>=', $today)->count(),
                'queue' => app(QueueCount::class)(),
                'reports_open' => Report::where('status', 'open')->count(),
                'reports_old' => Report::where('status', 'open')->where('created_at', '<', now()->subHour())->count(),
            ],
            'plan' => DailyQuestion::with('question')->whereDate('date', '>=', $today->toDateString())->orderBy('date')->limit(5)->get(),
            'topCategories' => DB::table('answers')->join('questions', 'questions.id', '=', 'answers.question_id')->join('categories', 'categories.id', '=', 'questions.category_id')
                ->where('answers.published_at', '>=', now()->subDays(7))->whereIn('answers.moderation_status', ModerationStatus::publicValues())
                ->groupBy('categories.name')->selectRaw('categories.name, count(*) as n')->orderByDesc('n')->limit(5)->get(),
        ];
    }
}
