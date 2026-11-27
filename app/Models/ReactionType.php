<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $slug
 * @property string $label
 * @property string $icon
 * @property string $weight
 */
class ReactionType extends Model
{
    protected $fillable = ['slug', 'label', 'icon', 'color', 'weight', 'counts_as_funny', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'counts_as_funny' => 'boolean', 'weight' => 'decimal:2'];
    }

    /** @return HasMany<Reaction, $this> */
    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    /** @return Collection<int, ReactionType> active types, cached */
    public static function active(): Collection
    {
        // Cache raw attributes, not objects (Laravel 13 cache.serializable_classes = false).
        return static::hydrate(Cache::remember('mtc:reaction-types:attrs', 600, fn () => static::where('is_active', true)->orderBy('sort_order')->get()->map(fn (self $m) => $m->getAttributes())->all()));
    }

    public static function flush(): void
    {
        Cache::forget('mtc:reaction-types:attrs');
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flush());
        static::deleted(fn () => static::flush());
    }
}
