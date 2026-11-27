<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $type
 * @property string $name
 */
class Location extends Model
{
    public const TYPES = ['country' => 'Stát', 'region' => 'Kraj', 'district' => 'Okres', 'municipality' => 'Obec'];

    protected $fillable = ['parent_id', 'type', 'code', 'name', 'slug', 'population'];

    /** @return BelongsTo<Location, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    /** @return HasMany<Location, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Location::class, 'parent_id');
    }

    /** Region (kraj) this location belongs to, or itself. */
    public function region(): ?Location
    {
        $node = $this;
        while ($node && $node->type !== 'region') {
            $node = $node->parent;
        }

        return $node;
    }

    /** IDs of this location and all its descendants (for "z mého kraje" filters). */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        $frontier = [$this->id];
        while ($frontier) {
            $frontier = Location::whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }

    public function label(): string
    {
        return $this->type === 'region' || $this->type === 'country' ? $this->name : $this->name.' ('.(self::TYPES[$this->type] ?? '').')';
    }
}
