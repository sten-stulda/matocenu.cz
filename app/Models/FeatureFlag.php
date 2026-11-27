<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'name', 'description', 'enabled', 'updated_by'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
