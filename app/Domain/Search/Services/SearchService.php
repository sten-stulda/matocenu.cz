<?php

namespace App\Domain\Search\Services;

use App\Domain\Moderation\Enums\ModerationStatus;
use App\Models\Answer;
use App\Models\Category;
use App\Models\Question;
use App\Support\Text;
use Illuminate\Support\Collection;

/**
 * PostgreSQL full-text (simple + unaccent → works without diacritics) with trigram fallback.
 * Answers are searched only in their published text – private originals are never searchable.
 */
class SearchService
{
    /** @param array{category?: int|null, region?: int[]|null, style?: int|null} $filters
     * @return array{questions: Collection<int, Question>, answers: Collection<int, Answer>, categories: Collection<int, Category>} */
    public function search(string $query, array $filters = [], int $limit = 20): array
    {
        $q = Text::clean($query);
        if (Text::length($q) < 2) {
            return ['questions' => collect(), 'answers' => collect(), 'categories' => collect()];
        }
        $tsquery = $this->tsquery($q);

        $questions = Question::query()->visible()->with('category')
            ->when($filters['category'] ?? null, fn ($w, $c) => $w->where('category_id', $c))
            ->when($filters['region'] ?? null, fn ($w, $r) => $w->whereIn('location_id', $r))
            ->where(fn ($w) => $w->whereRaw("to_tsvector('simple', mtc_unaccent(text)) @@ to_tsquery('simple', mtc_unaccent(?))", [$tsquery])
                ->orWhereRaw('mtc_unaccent(text) % mtc_unaccent(?)', [$q]))
            ->orderByRaw("ts_rank(to_tsvector('simple', mtc_unaccent(text)), to_tsquery('simple', mtc_unaccent(?))) DESC", [$tsquery])
            ->orderByDesc('answers_count')->limit($limit)->get();

        $answers = Answer::query()->with(['question', 'user', 'style'])->publiclyVisible()
            ->whereHas('question', fn ($w) => $w->visible()->when($filters['category'] ?? null, fn ($x, $c) => $x->where('category_id', $c)))
            ->when($filters['style'] ?? null, fn ($w, $s) => $w->where('language_style_id', $s))
            ->whereRaw("to_tsvector('simple', mtc_unaccent(display_text)) @@ to_tsquery('simple', mtc_unaccent(?))", [$tsquery])
            ->orderByDesc('score')->limit($limit)->get();

        $categories = Category::where('is_active', true)->whereRaw('mtc_unaccent(name) ILIKE mtc_unaccent(?)', ['%'.addcslashes($q, '%_\\').'%'])->get();

        return compact('questions', 'answers', 'categories');
    }

    /** "co chybí" → "co:* & chybi:*" – prefix match on every word, user input never reaches SQL syntax. */
    private function tsquery(string $q): string
    {
        $words = array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($q)) ?: [], fn ($w) => mb_strlen($w) > 1);

        return $words ? implode(' & ', array_map(fn ($w) => $w.':*', array_slice($words, 0, 8))) : 'xxxxxxxx';
    }

    public static function visible(): array
    {
        return ModerationStatus::publicValues();
    }
}
