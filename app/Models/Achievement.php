<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $rule
 * @property int $threshold
 * @property bool $is_title
 * @property int $tier
 * @property string $color
 */
class Achievement extends Model
{
    public const COLORS = [
        'outline' => 'border border-ink/70 text-ink',
        'mustard' => 'bg-mustard text-charcoal',
        'forest' => 'bg-forest text-cream',
        'forest-gold' => 'bg-forest text-mustard',
        'charcoal' => 'bg-charcoal text-mustard',
        'dashed' => 'border border-dashed border-muted text-muted',
    ];

    protected $fillable = ['slug', 'name', 'description', 'rule', 'threshold', 'is_title', 'tier', 'color', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_title' => 'boolean', 'is_active' => 'boolean'];
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievements');
    }

    public function badgeClass(): string
    {
        return self::COLORS[$this->color] ?? self::COLORS['outline'];
    }
}
