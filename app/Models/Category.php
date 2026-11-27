<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $color
 */
class Category extends Model
{
    public const COLORS = [
        'forest' => ['bg-forest-100/70 dark:bg-surface-2', 'bg-forest'],
        'mustard' => ['bg-mustard-100 dark:bg-surface-2', 'bg-mustard'],
        'terra' => ['bg-terra-100 dark:bg-surface-2', 'bg-terra'],
        'charcoal' => ['bg-surface-2', 'bg-charcoal dark:bg-cream'],
        'rust' => ['bg-terra-100 dark:bg-surface-2', 'bg-terra-ink'],
        'sage' => ['bg-[#E6E8E1] dark:bg-surface-2', 'bg-forest-300'],
    ];

    protected $fillable = ['name', 'slug', 'description', 'color', 'sort_order', 'is_active', 'is_sensitive'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_sensitive' => 'boolean'];
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function tileClass(): string
    {
        return self::COLORS[$this->color][0] ?? self::COLORS['forest'][0];
    }

    public function dotClass(): string
    {
        return self::COLORS[$this->color][1] ?? self::COLORS['forest'][1];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
