<?php

namespace App\Domain\Questions\Services;

use App\Domain\Questions\Enums\QuestionType;
use App\Support\Text;
use Illuminate\Validation\Rule;

/** Shared validation rules for question forms (web, admin, API). */
class QuestionRules
{
    /** @return array<string, mixed> */
    public static function rules(bool $admin = false): array
    {
        $types = $admin ? QuestionType::cases() : array_filter(QuestionType::cases(), fn ($t) => $t !== QuestionType::Daily);

        return [
            'text' => ['required', 'string', function ($attr, $value, $fail) {
                $t = Text::clean((string) $value);
                $len = Text::length($t);
                if ($len < 10) {
                    $fail('Otázka je moc krátká (min. 10 znaků).');
                } elseif ($len > 200) {
                    $fail('Otázka může mít nejvýše 200 znaků.');
                } elseif (! str_ends_with($t, '?')) {
                    $fail('Otázka by měla končit otazníkem.');
                }
            }],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'type' => ['required', Rule::enum(QuestionType::class)->only(array_values($types))],
            'location_id' => ['nullable', 'integer', 'exists:locations,id', 'required_if:type,local'],
            'is_sensitive' => ['boolean'],
            'is_anonymous' => ['boolean'],
            'options' => ['array', 'max:8', 'required_if:type,choice', 'required_if:type,poll'],
            'options.*' => ['string', 'max:100'],
            'closes_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
