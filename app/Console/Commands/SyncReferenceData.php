<?php

namespace App\Console\Commands;

use App\Domain\Administration\ReferenceData\ReferenceDataSyncer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('matocenu:sync-reference-data')]
#[Description('Doplní referenční data (role, oprávnění, feature flags, lokality, kategorie, styly, reakce, prompty…). Idempotentní.')]
class SyncReferenceData extends Command
{
    public function handle(): int
    {
        foreach (config('matocenu.reference_data') as $class) {
            /** @var ReferenceDataSyncer $syncer */
            $syncer = app($class);
            $this->components->task(class_basename($class).': '.$syncer->sync());
        }

        return self::SUCCESS;
    }
}
