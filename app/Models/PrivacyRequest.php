<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivacyRequest extends Model
{
    public const TYPES = ['export' => 'Export údajů', 'erasure' => 'Výmaz', 'rectification' => 'Oprava', 'objection' => 'Námitka', 'other' => 'Jiné'];

    public const STATUSES = ['open' => 'Nová', 'in_progress' => 'Řeší se', 'done' => 'Vyřízeno', 'rejected' => 'Zamítnuto'];

    protected $fillable = ['user_id', 'type', 'status', 'message', 'resolution', 'handled_by', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
