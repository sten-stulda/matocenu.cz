<?php

namespace App\Domain\Administration\ReferenceData;

use App\Models\Category;

class CategoriesSyncer implements ReferenceDataSyncer
{
    public const DEFAULTS = [
        ['Každodenní život', 'kazdodenni-zivot', 'charcoal', false],
        ['Peníze', 'penize', 'mustard', true],
        ['Práce', 'prace', 'forest', false],
        ['Rodina', 'rodina', 'terra', false],
        ['Vztahy', 'vztahy', 'rust', true],
        ['Bydlení', 'bydleni', 'rust', false],
        ['Humor', 'humor', 'mustard', false],
        ['Hudba a kultura', 'hudba-a-kultura', 'terra', false],
        ['Technologie', 'technologie', 'charcoal', false],
        ['Společnost', 'spolecnost', 'forest', false],
        ['Vzpomínky', 'vzpominky', 'mustard', false],
        ['Místní potřeby', 'mistni-potreby', 'terra', false],
        ['Podnikání', 'podnikani', 'forest', false],
        ['Přání a sny', 'prani-a-sny', 'mustard', false],
        ['Co kdyby…', 'co-kdyby', 'terra', false],
    ];

    public function sync(): string
    {
        $created = 0;
        foreach (self::DEFAULTS as $i => [$name, $slug, $color, $sensitive]) {
            $c = Category::firstOrCreate(['slug' => $slug], ['name' => $name, 'color' => $color, 'sort_order' => $i, 'is_sensitive' => $sensitive]);
            $created += (int) $c->wasRecentlyCreated;
        }

        return $created.' nových kategorií';
    }
}
