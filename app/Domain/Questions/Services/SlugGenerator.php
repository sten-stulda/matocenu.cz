<?php

namespace App\Domain\Questions\Services;

use App\Models\Question;
use Illuminate\Support\Str;

class SlugGenerator
{
    public function forQuestion(string $text, ?int $ignoreId = null): string
    {
        $base = Str::slug(Str::limit($text, 80, ''), '-', 'cs') ?: 'otazka';
        $slug = $base;
        $i = 2;
        while (Question::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
