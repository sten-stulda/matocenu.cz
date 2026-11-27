<?php

namespace App\Models;

use App\Domain\Moderation\Enums\ModerationStatus;
use App\Models\Concerns\Moderatable;
use Database\Factories\AnswerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $question_id
 * @property int $user_id
 * @property string $original_text
 * @property string $display_text
 * @property bool $is_stylized
 * @property bool $show_original
 * @property bool $is_anonymous
 * @property ModerationStatus $moderation_status
 * @property int $reactions_count
 * @property int $unique_reactors_count
 * @property int $comments_count
 * @property string $score
 * @property int $version
 * @property Carbon|null $published_at
 * @property Carbon|null $edited_at
 * @property Question $question
 * @property User $user
 */
class Answer extends Model
{
    /** @use HasFactory<AnswerFactory> */
    use HasFactory, Moderatable, SoftDeletes;

    protected $fillable = ['original_text', 'display_text', 'is_stylized', 'show_original', 'is_anonymous', 'option_id', 'numeric_value'];

    protected $attributes = ['moderation_status' => 'approved', 'is_stylized' => false, 'show_original' => false, 'is_anonymous' => false, 'version' => 1];

    /** Never leak the private original through serialisation; use publicOriginal(). */
    protected $hidden = ['original_text', 'user_id'];

    protected function casts(): array
    {
        return [
            'moderation_status' => ModerationStatus::class,
            'is_stylized' => 'boolean',
            'show_original' => 'boolean',
            'is_anonymous' => 'boolean',
            'numeric_value' => 'decimal:2',
            'published_at' => 'datetime',
            'edited_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<LanguageStyle, $this> */
    public function style(): BelongsTo
    {
        return $this->belongsTo(LanguageStyle::class, 'language_style_id');
    }

    /** @return BelongsTo<QuestionOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class);
    }

    /** @return HasMany<AnswerVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(AnswerVersion::class)->orderByDesc('version');
    }

    /** @return MorphMany<Comment, $this> */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /** @return MorphMany<Reaction, $this> */
    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    /** Original text, only when the author allowed it (or the viewer is the author / moderator). */
    public function publicOriginal(?User $viewer = null): ?string
    {
        if (! $this->is_stylized) {
            return null;
        }
        if ($this->show_original || ($viewer && ($viewer->id === $this->user_id || $viewer->can('answers.manage')))) {
            return $this->original_text;
        }

        return null;
    }

    /** Public author or null when anonymous / deleted account. */
    public function publicAuthor(): ?User
    {
        if ($this->is_anonymous || ! $this->user || $this->user->isDeleted()) {
            return null;
        }

        return $this->user;
    }

    public function moderationOwnerId(): ?int
    {
        return $this->user_id;
    }

    public function moderationExcerpt(): string
    {
        return $this->display_text;
    }

    public function url(): string
    {
        return route('answers.show', $this);
    }
}
