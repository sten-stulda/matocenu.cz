<?php

namespace App\Domain\Administration\ReferenceData;

use App\Domain\Administration\Services\FeatureFlags;
use App\Models\FeatureFlag;

class FeatureFlagsSyncer implements ReferenceDataSyncer
{
    public function sync(): string
    {
        foreach (config('matocenu.features') as $key => [$name, $description, $default, $ready]) {
            $flag = FeatureFlag::firstOrNew(['key' => $key]);
            $flag->name = $name;
            $flag->description = $description;
            if (! $flag->exists) {
                $flag->enabled = $default && $ready;
            }
            if (! $ready) {
                $flag->enabled = false;
            }
            $flag->save();
        }
        app(FeatureFlags::class)->flush();

        return count(config('matocenu.features')).' feature flags';
    }
}
