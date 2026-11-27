<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Question> */
class QuestionFactory extends Factory
{
    public function definition(): array
    {
        $text = 'Testovací otázka '.Str::random(6).'?';

        return [
            'category_id' => Category::query()->value('id') ?? Category::create(['name' => 'Test', 'slug' => 'test-'.Str::random(4)])->id,
            'type' => 'open',
            'text' => $text,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Question $q) {
            $q->slug ??= Str::slug($q->text).'-'.Str::lower(Str::random(4));
        })->afterCreating(function (Question $q) {
            $q->versions()->create(['version' => 1, 'text' => $q->text]);
        });
    }

    public function live(): static
    {
        return $this->state(fn () => [])->afterMaking(function (Question $q) {
            $q->moderation_status = 'approved';
            $q->publication_status = 'published';
            $q->published_at = now()->subMinute();
        });
    }
}
