<?php

use App\Domain\Administration\ReferenceData\FeatureFlagsSyncer;
use App\Domain\Administration\ReferenceData\LegalDocumentsSyncer;
use App\Domain\Administration\ReferenceData\RolesSyncer;
use App\Domain\Identity\Actions\RecordConsents;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // Every test runs with roles, flags and current legal documents in place.
        app(RolesSyncer::class)->sync();
        app(FeatureFlagsSyncer::class)->sync();
        app(LegalDocumentsSyncer::class)->sync();
    })
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/** A verified, consented user, optionally with an admin role. */
function member(?string $role = null, array $attrs = []): User
{
    $user = User::factory()->create($attrs);
    $user->assignRole('user');
    if ($role) {
        $user->assignRole($role);
    }
    app(RecordConsents::class)->registration($user, false, 'test');

    return $user->fresh();
}
