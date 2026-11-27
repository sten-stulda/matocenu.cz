<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ModerationAction extends Model
{
    public const UPDATED_AT = null;

    public const LABELS = [
        'auto_flag' => 'Automatická kontrola',
        'auto_hide' => 'Automaticky skryto po nahlášeních',
        'approve' => 'Schváleno',
        'reject' => 'Zamítnuto',
        'hide' => 'Skryto',
        'restore' => 'Obnoveno',
        'review' => 'Předáno k přezkumu',
        'appeal_upheld' => 'Odvolání zamítnuto',
        'appeal_overturned' => 'Odvolání vyhověno',
    ];

    protected $fillable = ['moderatable_type', 'moderatable_id', 'moderator_id', 'action', 'from_status', 'to_status', 'reason', 'risk'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return MorphTo<Model, $this> */
    public function moderatable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    /** The moderated content (typed). */
    public function content(): Answer|Comment|Question|null
    {
        $m = $this->moderatable;

        return $m instanceof Answer || $m instanceof Comment || $m instanceof Question ? $m : null;
    }

    /** @return BelongsTo<User, $this> */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    /** @return HasMany<ModerationAppeal, $this> */
    public function appeals(): HasMany
    {
        return $this->hasMany(ModerationAppeal::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->action] ?? $this->action;
    }
}
