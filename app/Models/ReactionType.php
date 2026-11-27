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
        return Cache::remember('mtc:reaction-types', 600, fn () => static::where('is_active', true)->orderBy('sort_order')->get());
    }

    public static function flush(): void
    {
        Cache::forget('mtc:reaction-types');
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flush());
        static::deleted(fn () => static::flush());
    }
}
