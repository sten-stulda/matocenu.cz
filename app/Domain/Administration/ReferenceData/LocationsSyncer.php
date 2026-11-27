<?php

namespace App\Domain\Administration\ReferenceData;

use App\Models\Location;
use Illuminate\Support\Str;

class LocationsSyncer implements ReferenceDataSyncer
{
    public function sync(): string
    {
        $cz = Location::firstOrCreate(['type' => 'country', 'code' => 'CZ'], ['name' => 'Česká republika', 'slug' => 'cela-cr']);
        $n = 0;
        foreach (require database_path('data/cz-locations.php') as $code => [$name, $districts]) {
            $region = Location::firstOrCreate(['type' => 'region', 'code' => $code], ['name' => $name, 'slug' => Str::slug($name), 'parent_id' => $cz->id]);
            foreach ($districts as $dCode => $dName) {
                Location::firstOrCreate(['type' => 'district', 'code' => $dCode], ['name' => $dName, 'slug' => Str::slug('okres '.$dName), 'parent_id' => $region->id]);
                $n++;
            }
        }

        return '14 krajů, '.$n.' okresů';
    }
}
