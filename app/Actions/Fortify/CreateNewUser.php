<?php

namespace App\Actions\Fortify;

use App\Domain\Identity\Actions\RecordConsents;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Rules\UniqueNickname;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        // Honeypot – bots fill every field.
        if (! empty($input['website'])) {
            throw ValidationException::withMessages(['email' => 'Registraci se nepodařilo dokončit.']);
        }

        $input['name'] = Str::squish((string) ($input['name'] ?? ''));
        $input['email'] = Str::lower(trim((string) ($input['email'] ?? '')));

        Validator::make($input, [
            'name' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[\pL\pN][\pL\pN ._\-]*$/u', new UniqueNickname],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
            'privacy' => ['accepted'],
        ], [
            'name.regex' => 'Přezdívka může obsahovat písmena, číslice, mezery, tečky, podtržítka a pomlčky.',
            'terms.accepted' => 'Bez souhlasu s podmínkami to nepůjde.',
            'privacy.accepted' => 'Potvrď prosím, že znáš zásady ochrany osobních údajů.',
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);
            $user->assignRole(Role::User->value);
            $user->settings()->create([]);
            app(RecordConsents::class)->registration($user, (bool) ($input['marketing'] ?? false));

            return $user;
        });
    }
}
