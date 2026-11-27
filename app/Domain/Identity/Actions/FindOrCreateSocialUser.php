<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialUser;

class FindOrCreateSocialUser
{
    public function google(SocialUser $google): ?User
    {
        $email = Str::lower((string) $google->getEmail());
        $verified = (bool) ($google->user['email_verified'] ?? $google->user['verified_email'] ?? false);

        if ($user = User::where('google_id', $google->getId())->first()) {
            return $user;
        }
        // Link to an existing account only if Google vouches for the e-mail address.
        if ($email !== '' && $verified && ($user = User::where('email', $email)->first())) {
            $user->forceFill(['google_id' => $google->getId(), 'email_verified_at' => $user->email_verified_at ?? now()])->save();

            return $user;
        }
        if ($email === '' || ! $verified) {
            return null;
        }

        return DB::transaction(function () use ($google, $email) {
            $user = new User(['name' => $this->uniqueNickname((string) ($google->getNickname() ?: $google->getName() ?: 'Host')), 'email' => $email]);
            $user->forceFill(['google_id' => $google->getId(), 'email_verified_at' => now()])->save();
            $user->assignRole(Role::User->value);
            $user->settings()->create([]);

            // No consent yet – EnsureCurrentConsents middleware sends the user to /souhlas.
            return $user;
        });
    }

    private function uniqueNickname(string $base): string
    {
        $base = Str::of($base)->replaceMatches('/[^\pL\pN ._\-]/u', '')->squish()->limit(30, '')->value() ?: 'Host';
        if (mb_strlen($base) < 3) {
            $base .= '_cz';
        }
        $name = $base;
        $i = 1;
        while (User::whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
            $name = $base.'_'.(++$i);
        }

        return $name;
    }
}
