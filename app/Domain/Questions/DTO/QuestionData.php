<?php

namespace App\Domain\Questions\DTO;

use App\Domain\Questions\Enums\QuestionType;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final readonly class QuestionData
{
    /** @param list<string> $options */
    public function __construct(
        public string $text,
        public int $categoryId,
        public QuestionType $type = QuestionType::Open,
        public ?int $locationId = null,
        public bool $isSensitive = false,
        public bool $isAnonymous = false,
        public array $options = [],
        public ?CarbonInterface $closesAt = null,
    ) {}

    /** @param array<string, mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(
            text: (string) $a['text'],
            categoryId: (int) $a['category_id'],
            type: QuestionType::from($a['type'] ?? 'open'),
            locationId: ($a['location_id'] ?? null) ? (int) $a['location_id'] : null,
            isSensitive: (bool) ($a['is_sensitive'] ?? false),
            isAnonymous: (bool) ($a['is_anonymous'] ?? false),
            options: array_values(array_filter(array_map(fn ($o) => trim((string) $o), (array) ($a['options'] ?? [])))),
            closesAt: ! empty($a['closes_at']) ? Carbon::parse($a['closes_at'], config('matocenu.display_timezone')) : null,
        );
    }
}
