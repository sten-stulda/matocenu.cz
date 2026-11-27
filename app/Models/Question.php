<?php

namespace App\Models;

use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Questions\Enums\PublicationStatus;
use App\Domain\Questions\Enums\QuestionType;
use App\Models\Concerns\Moderatable;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $author_id
 * @property int $category_id
 * @property int|null $location_id
 * @property QuestionType $type
 * @property string $slug
 * @property string $text
 * @property int $version
 * @property ModerationStatus $moderation_status
 * @property PublicationStatus $publication_status
 * @property Carbon|null $published_at
 * @property Carbon|null $closes_at
 * @property bool $is_sensitive
 * @property bool $is_anonymous
 * @property int $answers_count
 * @property int $reactions_count
 * @property int $comments_count
 * @property int $followers_count
 * @property Carbon|null $last_activity_at
 */
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory, Moderatable, SoftDeletes;

    protected $fillable = ['category_id', 'location_id', 'type', 'text', 'closes_at', 'is_sensitive', 'is_anonymous', 'is_daily_candidate'];

    protected $attributes = [
        'moderation_status' => 'pending',
        'publication_status' => 'draft',
        'type' => 'open',
        'version' => 1,
        'is_sensitive' => false,
        'is_anonymous' => false,
    ];

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'moderation_status' => ModerationStatus::class,
            'publication_status' => PublicationStatus::class,
            'published_at' => 'datetime',
            'closes_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'is_sensitive' => 'boolean',
            'is_anonymous' => 'boolean',
            'is_daily_candidate' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
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

    /** @return HasMany<QuestionVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(QuestionVersion::class)->orderByDesc('version');
    }

    /** @return HasMany<QuestionOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sort_order');
    }

    /** @return HasMany<Answer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    /** @return HasMany<DailyQuestion, $this> */
    public function dailyEntries(): HasMany
    {
        return $this->hasMany(DailyQuestion::class);
    }

    /** @return MorphMany<Comment, $this> */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /** @return MorphMany<Follow, $this> */
    public function follows(): MorphMany
    {
        return $this->morphMany(Follow::class, 'followable');
    }

    /** Published, approved and not deleted. @param Builder<Question> $query */
    public function scopeLive(Builder $query): void
    {
        $query->where('publication_status', PublicationStatus::Published->value)
            ->whereIn('moderation_status', ModerationStatus::publicValues())
            ->where('published_at', '<=', now());
    }

    /** Published OR closed (archive pages) – still visible. @param Builder<Question> $query */
    public function scopeVisible(Builder $query): void
    {
        $query->whereIn('publication_status', [PublicationStatus::Published->value, PublicationStatus::Closed->value, PublicationStatus::Archived->value])
            ->whereIn('moderation_status', ModerationStatus::publicValues())
            ->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isVisible(): bool
    {
        return in_array($this->publication_status, [PublicationStatus::Published, PublicationStatus::Closed, PublicationStatus::Archived], true)
            && $this->moderation_status->isPublic() && $this->published_at && ! $this->published_at->isFuture() && ! $this->trashed();
    }

    public function acceptsAnswers(): bool
    {
        return $this->publication_status === PublicationStatus::Published && $this->isVisible()
            && (! $this->closes_at || $this->closes_at->isFuture());
    }

    public function moderationOwnerId(): ?int
    {
        return $this->author_id;
    }

    public function moderationExcerpt(): string
    {
        return $this->text;
    }

    public function url(): string
    {
        return route('questions.show', $this);
    }
}
