<?php

namespace Database\Factories;

use App\Models\Answer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Answer> */
class AnswerFactory extends Factory
{
    public function definition(): array
    {
        $t = 'Testovací odpověď';

        return ['original_text' => $t, 'display_text' => $t];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Answer $a) {
            $a->published_at ??= now();
        });
    }
}
