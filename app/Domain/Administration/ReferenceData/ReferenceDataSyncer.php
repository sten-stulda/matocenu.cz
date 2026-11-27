<?php

namespace App\Domain\Administration\ReferenceData;

/**
 * Idempotent seeding of reference data (roles, categories, styles…) that the app needs
 * in every environment. Syncers create missing rows and never overwrite admin edits.
 */
interface ReferenceDataSyncer
{
    /** @return string short summary for the console */
    public function sync(): string;
}
