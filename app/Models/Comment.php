<?php

namespace App\Models;

use App\Domain\Moderation\Enums\ModerationStatus;
use App\Models\Concerns\Moderatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $parent_id
 * @property int $depth
 * @property string $body
 * @property ModerationStatus $moderation_status
 * @property Carbon|null $edited_at
 * @property User $user
 */
class Comment extends Model
{
    use Moderatable, SoftDeletes;

    protected $fillable = ['body'];

    protected $attributes = ['moderation_status' => 'approved', 'depth' => 0];

    protected function casts(): array
    {
        return ['moderation_status' => ModerationStatus::class, 'edited_at' => 'datetime'];
    }

    /** @return MorphTo<Model, $this> */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function target(): Answer|Question|null
    {
        $t = $this->commentable;

        return $t instanceof Answer || $t instanceof Question ? $t : null;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Comment, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /** @return HasMany<Comment, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->orderBy('created_at');
    }

    public function moderationOwnerId(): ?int
    {
        return $this->user_id;
    }

    public function moderationExcerpt(): string
    {
        return $this->body;
    }
}
