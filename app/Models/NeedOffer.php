<?php

namespace App\Models;

use App\Domain\Moderation\Enums\ModerationStatus;
use App\Models\Concerns\Moderatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $need_id
 * @property int $user_id
 * @property string $description
 * @property string|null $price_text
 * @property string $status
 * @property Carbon|null $last_broadcast_at
 * @property Need $need
 * @property User $user
 */
class NeedOffer extends Model implements Contracts\ModeratableContent
{
    use Moderatable, SoftDeletes;

    public const STATUSES = ['pending' => 'Nabídnuto', 'accepted' => 'Přijato autorem potřeby', 'declined' => 'Nevyužito', 'withdrawn' => 'Staženo'];

    protected $fillable = ['description', 'price_text'];

    protected $attributes = ['status' => 'pending', 'moderation_status' => 'approved'];

    protected function casts(): array
    {
        return ['moderation_status' => ModerationStatus::class, 'last_broadcast_at' => 'datetime'];
    }

    /** @return BelongsTo<Need, $this> */
    public function need(): BelongsTo
    {
        return $this->belongsTo(Need::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function moderationOwnerId(): ?int
    {
        return $this->user_id;
    }

    public function moderationExcerpt(): string
    {
        return $this->description;
    }
}
