<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property list<array<string, mixed>> $items
 * @property Carbon $computed_at
 */
class RankingSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = ['type', 'period', 'period_key', 'location_id', 'items', 'computed_at'];

    protected function casts(): array
    {
        return ['items' => 'array', 'computed_at' => 'datetime'];
    }
}
