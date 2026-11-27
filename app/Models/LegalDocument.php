<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LegalDocument extends Model
{
    public const TYPES = [
        'terms' => ['Podmínky používání', 'podminky'],
        'privacy' => ['Zásady ochrany osobních údajů', 'soukromi'],
        'community' => ['Pravidla komunity', 'pravidla'],
        'ai' => ['Jak pracuje AI', 'jak-pracuje-ai'],
        'content_license' => ['Licenční podmínky uživatelského obsahu', 'licence-obsahu'],
        'commercial' => ['Podmínky komerčního využití hlášek', 'komercni-vyuziti'],
        'cookies' => ['Cookies', 'cookies'],
        'shop_terms' => ['Obchodní podmínky (připravujeme)', 'obchodni-podminky'],
    ];

    protected $fillable = ['type', 'version', 'title', 'body', 'requires_consent', 'is_draft_for_review', 'published_at', 'created_by'];

    protected function casts(): array
    {
        return ['requires_consent' => 'boolean', 'is_draft_for_review' => 'boolean', 'published_at' => 'datetime'];
    }

    public static function current(string $type): ?self
    {
        return static::query()->where('type', $type)->whereNotNull('published_at')
            ->where('published_at', '<=', now())->orderByDesc('version')->first();
    }

    /** @param Builder<LegalDocument> $query */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public static function typeForSlug(string $slug): ?string
    {
        foreach (self::TYPES as $type => [, $s]) {
            if ($s === $slug) {
                return $type;
            }
        }

        return null;
    }

    public function slug(): string
    {
        return self::TYPES[$this->type][1] ?? $this->type;
    }
}
