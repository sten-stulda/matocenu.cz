<?php

namespace App\Domain\Questions\Services;

use App\Models\Question;
use App\Support\Text;
use Illuminate\Support\Collection;

/** Suggests existing questions before a new one is created (pg_trgm similarity). Never merges anything. */
class SimilarQuestions
{
    /** @return Collection<int, Question> */
    public function find(string $text, int $limit = 5): Collection
    {
        $t = Text::clean($text);
        if (Text::length($t) < 8) {
            return collect();
        }

        return Question::query()->visible()
            ->select('questions.*')
            ->selectRaw('similarity(text, ?) as sim', [$t])
            ->whereRaw('text % ?', [$t])
            ->orderByDesc('sim')
            ->limit($limit)
            ->get();
    }
}
