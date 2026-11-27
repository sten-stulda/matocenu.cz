<?php

namespace App\Domain\Identity\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Case-insensitive unique nickname; also blocks names reserved for the platform. */
class UniqueNickname implements ValidationRule
{
    private const RESERVED = ['admin', 'administrator', 'moderator', 'matocenu', 'matocenu.cz', 'anonym', 'system', 'support', 'podpora', 'redakce'];

    public function __construct(private ?int $ignoreUserId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $lower = mb_strtolower((string) $value);
        if (in_array($lower, self::RESERVED, true)) {
            $fail('Tuhle přezdívku si nechává platforma. Zkus jinou.');

            return;
        }
        $exists = User::query()->whereRaw('lower(name) = ?', [$lower])
            ->when($this->ignoreUserId, fn ($q) => $q->whereKeyNot($this->ignoreUserId))->exists();
        if ($exists) {
            $fail('Tahle přezdívka už je obsazená.');
        }
    }
}
