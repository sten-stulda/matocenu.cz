<?php

namespace App\Models;

use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Needs\Enums\NeedType;
use App\Models\Concerns\Moderatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $location_id
 * @property NeedType $type
 * @property string $slug
 * @property string $title
 * @property string $description
 * @property string $urgency
 * @property string $status
 * @property bool $is_anonymous
 * @property int $interested_count
 * @property int $would_pay_count
 * @property int $offers_count
 * @property Carbon|null $published_at
 * @property User $user
 */
class Need extends Model implements Contracts\ModeratableContent
{
    use Moderatable, SoftDeletes;

    public const STATUSES = ['open' => 'Otevřená', 'in_progress' => 'Řeší se', 'solved' => 'Splněno', 'closed' => 'Uzavřená'];

    public const URGENCY = ['low' => 'Nespěchá', 'medium' => 'Během pár měsíců', 'high' => 'Hoří'];

    protected $fillable = ['category_id', 'location_id', 'type', 'title', 'description', 'urgency', 'is_anonymous'];

    protected $attributes = ['status' => 'open', 'moderation_status' => 'approved', 'urgency' => 'medium', 'is_anonymous' => false];

    protected function casts(): array
    {
        return [
            'type' => NeedType::class,
            'moderation_status' => ModerationStatus::class,
            'is_anonymous' => 'boolean',
            'published_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return HasMany<NeedInterest, $this> */
    public function interests(): HasMany
    {
        return $this->hasMany(NeedInterest::class);
    }

    /** @return HasMany<NeedOffer, $this> */
    public function offers(): HasMany
    {
        return $this->hasMany(NeedOffer::class);
    }

    /** @param Builder<Need> $q */
    public function scopeVisible(Builder $q): void
    {
        $q->whereIn('moderation_status', ModerationStatus::publicValues())->whereNotNull('published_at');
    }

    public function publicAuthor(): ?User
    {
        return $this->is_anonymous || $this->user->isDeleted() ? null : $this->user;
    }

    public function moderationOwnerId(): ?int
    {
        return $this->user_id;
    }

    public function moderationExcerpt(): string
    {
        return $this->title.' – '.$this->description;
    }

    public function url(): string
    {
        return route('needs.show', $this);
    }
}
