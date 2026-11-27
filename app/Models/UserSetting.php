<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'anonymous_by_default', 'show_original_by_default', 'humor_on_sensitive',
        'show_achievements', 'show_region', 'marketing_emails', 'notifications',
    ];

    protected $attributes = [
        'anonymous_by_default' => false,
        'show_original_by_default' => true,
        'humor_on_sensitive' => false,
        'show_achievements' => true,
        'show_region' => true,
        'marketing_emails' => false,
        'notifications' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'anonymous_by_default' => 'boolean',
            'show_original_by_default' => 'boolean',
            'humor_on_sensitive' => 'boolean',
            'show_achievements' => 'boolean',
            'show_region' => 'boolean',
            'marketing_emails' => 'boolean',
            'notifications' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Whether the user wants notification $type over $channel (database|mail). */
    public function wantsNotification(string $type, string $channel, bool $default = true): bool
    {
        return (bool) ($this->notifications[$type][$channel] ?? $default);
    }
}
