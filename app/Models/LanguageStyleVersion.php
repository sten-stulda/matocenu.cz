<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LanguageStyleVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['language_style_id', 'version', 'instructions', 'examples', 'created_by'];

    protected function casts(): array
    {
        return ['examples' => 'array', 'created_at' => 'datetime'];
    }
}
