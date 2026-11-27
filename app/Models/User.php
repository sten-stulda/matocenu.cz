<?php

namespace App\Models;

use App\Domain\Identity\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserStatus $status
 * @property string|null $avatar_path
 * @property int|null $location_id
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $suspended_until
 * @property Carbon|null $created_at
 * @property Carbon|null $two_factor_confirmed_at
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /** Mass assignment only for safe profile fields; status/roles are set explicitly. */
    protected $fillable = ['name', 'email', 'password', 'location_id'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'google_id', 'email'];

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'suspended_until' => 'datetime',
            'deleted_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    /** @return HasOne<UserSetting, $this> */
    public function settings(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    /** Settings row, created lazily with defaults. */
    public function preferences(): UserSetting
    {
        return $this->settings()->firstOrCreate([]);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function blockedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_blocks', 'blocker_id', 'blocked_id');
    }

    /** @return HasMany<ConsentRecord, $this> */
    public function consents(): HasMany
    {
        return $this->hasMany(ConsentRecord::class);
    }

    public function isActive(): bool
    {
        if ($this->status === UserStatus::Suspended && $this->suspended_until && $this->suspended_until->isPast()) {
            return true;
        }

        return $this->status === UserStatus::Active;
    }

    public function isDeleted(): bool
    {
        return $this->status === UserStatus::Deleted;
    }

    public function initials(): string
    {
        return Str::of($this->name)->replaceMatches('/[^\pL\pN ]+/u', ' ')->squish()->explode(' ')
            ->take(2)->map(fn (string $w) => Str::upper(Str::substr($w, 0, 1)))->implode('')
            ?: Str::upper(Str::substr($this->name, 0, 2));
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    public function canAccessAdmin(): bool
    {
        return $this->can('admin.access');
    }

    public function hasBlocked(int $userId): bool
    {
        return $this->blockedUsers()->whereKey($userId)->exists();
    }
}
