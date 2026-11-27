<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Bookmark extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'bookmarkable_type', 'bookmarkable_id'];

    /** @return MorphTo<Model, $this> */
    public function bookmarkable(): MorphTo
    {
        return $this->morphTo();
    }
}
