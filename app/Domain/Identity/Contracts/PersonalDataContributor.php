<?php

namespace App\Domain\Identity\Contracts;

use App\Models\User;

/**
 * Modules that store personal data implement this so GDPR export and account deletion
 * stay complete. Register implementations in config('matocenu.personal_data').
 */
interface PersonalDataContributor
{
    /** Section name in the export file. */
    public function key(): string;

    /** @return array<mixed> */
    public function export(User $user): array;

    /** Remove or anonymise the user's data. $deleteContent=false keeps public content as anonymous. */
    public function erase(User $user, bool $deleteContent): void;
}
