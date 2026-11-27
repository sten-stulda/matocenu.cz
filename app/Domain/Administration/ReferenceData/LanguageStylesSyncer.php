<?php

namespace App\Domain\Administration\ReferenceData;

use App\Models\LanguageStyle;

/**
 * Default language personalities. Dialect styles are a humorous stylisation, not an
 * authentic linguistic transcription. Admins edit/extend them without code changes.
 */
class LanguageStylesSyncer implements ReferenceDataSyncer
{
    public const Q = 'Co vám v životě nejvíce chybí?';

    public static function defaults(): array
    {
        return [
            // name, slug, icon, color, description, instructions, [question example, answer original, answer styled], humorous, sensitive allowed
            ['Originál', 'original', 'Or', 'plain', 'bez úprav', null, [self::Q, '', ''], false, true, 'original'],
            ['Moravák', 'moravak', 'Mo', 'mustard', 'moravské nářečí', 'Humorná stylizace moravského nářečí: „tož“, „sa“, „aj“, „mňa“, „enem“, „něco“ → „něco“, měkčí koncovky. Mluv přátelsky a lidově, jako soused na zahrádce. Nepřeháněj – text musí zůstat srozumitelný.', ['Tož čeho sa vám v životě nejvíc nedostává?', 'Já.', 'Tož já, však u mňa už aj bída bydlí na dluh.'], true, false],
            ['Ostravák', 'ostravak', 'Os', 'charcoal', 'krátce a úderně', 'Úderný ostravský humor: krátké věty, přímost, „ja?“, „pyčo“ NEPOUŽÍVEJ (vulgarismy zakázány), „chlapče“, „dycky“. Rychle k věci.', ['Co ti chybí? Ale kratce, ja?', 'Spánek.', 'Spaní. Osm hodin v kuse, ja? To su jen sny.'], true, false],
            ['Pražák', 'prazak', 'Pr', 'forest', 'městský slang', 'Městská hovorová čeština: „fakt“, „kámo“, „v pohodě“, „jako“, lehce ležérní tón. Žádné nadávky na jiné regiony.', ['Co ti v životě fakt nejvíc chybí, kámo?', 'Čas.', 'Čas, kámo. Fakt nemám ani chvilku na kafe.'], true, false],
            ['Staročech', 'starocech', 'St', 'cream', 'archaická čeština', 'Archaizující čeština: přechodníky, „jest“, „vězte“, „živobytí“, slovosled jako ve staré kronice. Důstojně a s nadhledem.', ['Čehož vám v živobytí vašem nejvíce schází?', 'Peníze.', 'Peněz, vězte, mi v měšci mém věru pramálo zbývá.'], true, false],
            ['Sedlák', 'sedlak', 'Se', 'terra', 'selská moudrost', 'Selská moudrost: přirovnání z hospodářství, polí, počasí a zvířat. Klidný, uvážlivý tón, „sousede“.', ['Co vám v živobytí nejvíc schází, sousede?', 'Klid.', 'Klid, sousede. Jako když se po žních sedne na lavici.'], true, false],
            ['Švejkovský humor', 'svejk', 'Šv', 'mustard', 'lidový absurdní humor', 'Obecný lidový absurdní humor, poslušná zdvořilost a odbočky („poslušně hlásím“). NIKDY necituj ani neparafrázuj chráněné texty, postavy či hlášky z knih a filmů.', ['Poslušně se ptám, co vám v životě nejvíc chybí?', 'Trpělivost.', 'Poslušně hlásím, že mi chybí jen trpělivost. Jinak nic.'], true, false],
            ['Hospodský', 'hospodsky', 'Ho', 'rust', 'hospodské hlášky', 'Hospodské hlášky od stolu štamgastů: pivo, rundy, výčepní, ale bez propagace nadměrného pití a bez vulgarit.', ['Tak ven s tím – co ti v životě nejvíc chybí?', 'Kamarádi.', 'Kamarádi u stolu. Pivo bez nich chutná jak voda.'], true, false],
            ['Sarkastik', 'sarkastik', 'Sa', 'charcoal', 'ironický humor', 'Lehká ironie a nadsázka, nikdy krutá, nikdy na úkor skupin lidí. Ironie míří na situaci, ne na autora.', ['No schválně, co vám v tom dokonalém životě chybí?', 'Dovolená.', 'Dovolená. Prý existuje, viděl jsem ji v katalogu.'], true, false],
            ['Filozof', 'filozof', 'Fi', 'forest', 'hluboké myšlenky', 'Zamyšlení: klidný, přemýšlivý tón, jemná metafora, žádný humor na úkor autora. Vhodný i pro citlivá témata.', ['Co je to, jehož nepřítomnost cítíte nejvíce?', 'Táta.', 'Táta. Jeho prázdná židle u oběda mluví hlasitěji než slova.'], false, true],
            ['Básník', 'basnik', 'Bá', 'cream', 'poetické vyjádření', 'Poetické vyjádření: obraznost, rytmus, případně jemný rým. Krátce – stále max. povolená délka.', ['Co v duši vašeho žití nejvíc schází?', 'Moře.', 'Moře, co šumí jen ve snech a slaná vůně mi chybí.'], false, true],
            ['Suchý humor', 'suchy-humor', 'Su', 'plain', 'věcně a s nadhledem', 'Suchý humor: věcný, strohý tón s nečekanou pointou. Žádná přehnaná citoslovce.', ['Co vám chybí? Kromě času, peněz a spánku.', 'Spánek.', 'Spánek. Zatím ho nahrazuji kávou. Funguje to střídavě.'], true, false],
            ['Retro', 'retro', 'Re', 'terra', 'jako za starých časů', 'Retro tón 80. a 90. let: „to je hustý“, „pecka“, kazety, Mirinda, telefonní budka – bez politických narážek.', ['Co vám chybí, jak říkali za mlada – no fakt?', 'Kamarádi.', 'Kamarádi z party. Bez nich je to jak kazeta bez propisky.'], true, false],
            ['Náhodný styl', 'nahodny', '?', 'plain', 'překvapení', null, [self::Q, '', ''], true, false, 'random'],
        ];
    }

    public function sync(): string
    {
        $created = 0;
        foreach (self::defaults() as $i => $d) {
            [$name, $slug, $icon, $color, $desc, $instr, [$qEx, $orig, $styled], $humorous, $sensitive] = $d;
            $special = $d[9] ?? null;
            if (LanguageStyle::where('slug', $slug)->exists()) {
                continue;
            }
            $s = new LanguageStyle([
                'name' => $name, 'description' => $desc, 'icon' => $icon, 'color' => $color, 'instructions' => $instr,
                'examples' => $orig !== '' ? [['original' => $orig, 'styled' => $styled], ['original' => self::Q, 'styled' => $qEx]] : [['original' => self::Q, 'styled' => $qEx]],
                'content_types' => ['question', 'answer'], 'sensitive_policy' => $sensitive ? 'allowed' : 'disabled',
                'is_humorous' => $humorous, 'is_active' => true, 'sort_order' => $i,
            ]);
            $s->slug = $slug;
            $s->is_original = $special === 'original';
            $s->is_random = $special === 'random';
            $s->save();
            $s->versions()->create(['version' => 1, 'instructions' => $instr, 'examples' => $s->examples]);
            $created++;
        }

        return $created.' nových jazykových stylů';
    }
}
