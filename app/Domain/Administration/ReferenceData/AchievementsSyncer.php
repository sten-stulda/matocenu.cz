<?php

namespace App\Domain\Administration\ReferenceData;

use App\Models\Achievement;

class AchievementsSyncer implements ReferenceDataSyncer
{
    public const DEFAULTS = [
        // slug, name, description, rule, threshold, is_title, tier, color
        ['novacek', 'Nováček', 'První zveřejněná odpověď.', 'first_answer', 1, true, 1, 'outline'],
        ['prvni-hlaska', 'První hláška', 'První odpověď, na kterou někdo zareagoval.', 'rated_answers', 1, false, 1, 'outline'],
        ['mudrlant', 'Mudrlant', '10 odpovědí, které ocenil někdo jiný.', 'rated_answers', 10, true, 2, 'outline'],
        ['zlata-huba', 'Zlatá huba', '100 reakcí od ostatních na tvé odpovědi.', 'reactions_received', 100, true, 3, 'outline'],
        ['selsky-filozof', 'Selský filozof', '25 odpovědí na otázku dne.', 'daily_answers', 25, true, 4, 'forest'],
        ['mistr-hlasek', 'Mistr hlášek', '3× hláška dne, týdne nebo měsíce.', 'quotes', 3, true, 5, 'mustard'],
        ['kral-gresli', 'Král grešlí', '1 000× „To má cenu!“ od komunity.', 'primary_reactions', 1000, true, 6, 'forest-gold'],
        ['legendarni-vypravec', 'Legendární vypravěč', 'Hláška měsíce.', 'quote_month', 1, true, 7, 'charcoal'],
        ['pomocna-ruka', 'Pomocná ruka', 'Tvá nabídka pomohla vyřešit skutečnou potřebu.', 'need_helper', 1, false, 1, 'forest'],
    ];

    public function sync(): string
    {
        $n = 0;
        foreach (self::DEFAULTS as $i => [$slug, $name, $desc, $rule, $threshold, $title, $tier, $color]) {
            $a = Achievement::firstOrCreate(['slug' => $slug], ['name' => $name, 'description' => $desc, 'rule' => $rule, 'threshold' => $threshold, 'is_title' => $title, 'tier' => $tier, 'color' => $color, 'sort_order' => $i]);
            $n += (int) $a->wasRecentlyCreated;
        }

        return $n.' nových ocenění';
    }
}
