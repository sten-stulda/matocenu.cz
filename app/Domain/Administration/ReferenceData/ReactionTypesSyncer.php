<?php

namespace App\Domain\Administration\ReferenceData;

use App\Models\ReactionType;

class ReactionTypesSyncer implements ReferenceDataSyncer
{
    public const DEFAULTS = [
        // slug, label, icon, weight, funny
        ['ma-cenu', 'To má cenu!', 'coin', '1.50', false],
        ['pobavilo', 'To mě pobavilo!', 'smile', '1.00', true],
        ['mam-stejne', 'Mám to stejně!', 'equal', '1.00', false],
        ['zlata-slova', 'Zlatá slova!', 'star', '1.25', false],
        ['to-sedi', 'To sedí!', 'target', '1.00', false],
        ['toz-pravda', 'Tož pravda!', 'check', '1.00', false],
    ];

    public function sync(): string
    {
        $created = 0;
        foreach (self::DEFAULTS as $i => [$slug, $label, $icon, $weight, $funny]) {
            $t = ReactionType::firstOrCreate(['slug' => $slug], ['label' => $label, 'icon' => $icon, 'weight' => $weight, 'counts_as_funny' => $funny, 'sort_order' => $i]);
            $created += (int) $t->wasRecentlyCreated;
        }
        ReactionType::flush();

        return $created.' nových typů reakcí';
    }
}
