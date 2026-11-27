<?php

namespace App\Domain\Identity\Services;

use App\Domain\Administration\Services\AuditLogger;
use App\Domain\Identity\Contracts\PersonalDataContributor;
use App\Models\User;

class PersonalDataExporter
{
    /** @return array<string, mixed> */
    public function export(User $user): array
    {
        $user->loadMissing('settings', 'location');
        $data = [
            'vygenerovano' => now()->toIso8601String(),
            'ucet' => [
                'prezdivka' => $user->name,
                'email' => $user->email,
                'email_overen' => $user->email_verified_at?->toIso8601String(),
                'registrace' => $user->created_at?->toIso8601String(),
                'posledni_aktivita' => $user->last_seen_at?->toIso8601String(),
                'lokalita' => $user->location?->name,
                'prihlaseni_google' => (bool) $user->google_id,
                'dvoufazove_overeni' => (bool) $user->two_factor_confirmed_at,
                'role' => $user->getRoleNames()->all(),
            ],
            'nastaveni' => $user->settings?->only(['anonymous_by_default', 'show_original_by_default', 'humor_on_sensitive', 'show_achievements', 'show_region', 'marketing_emails', 'notifications']),
            'souhlasy' => $user->consents()->orderBy('created_at')->get()->map(fn ($c) => [
                'typ' => $c->consent_type, 'udelen' => $c->granted, 'kdy' => $c->created_at?->toIso8601String(), 'zdroj' => $c->source,
            ])->all(),
            'blokovani_uzivatele' => $user->blockedUsers()->pluck('name')->all(),
        ];
        foreach ($this->contributors() as $c) {
            $data[$c->key()] = $c->export($user);
        }
        app(AuditLogger::class)->log('privacy.export', $user);

        return $data;
    }

    /** @return list<PersonalDataContributor> */
    public function contributors(): array
    {
        return array_map(fn (string $class) => app($class), config('matocenu.personal_data', []));
    }
}
