<x-layouts::app title="Jak počítáme žebříčky" description="Transparentní pravidla žebříčků MáToCenu.cz.">
    <article class="card prose-mtc mx-auto max-w-3xl">
        <h1 class="font-display text-3xl font-extrabold">Jak počítáme žebříčky</h1>
        <p>Žebříčky nestojí na prostém počtu reakcí. Každá odpověď dostane skóre podle těchto pravidel:</p>
        <ol>
            <li><b>Jen reakce ostatních.</b> Vlastní odpověď hodnotit nejde a každý člověk může na odpověď reagovat jen jednou (hlídá to databáze).</li>
            <li><b>Váha reakce.</b> „To má cenu!“ má váhu {{ \App\Models\ReactionType::where('slug', 'ma-cenu')->value('weight') }}, „Zlatá slova!“ {{ \App\Models\ReactionType::where('slug', 'zlata-slova')->value('weight') }}, ostatní 1. Váhy nastavuje administrace.</li>
            <li><b>Důvěra.</b> Reakce z účtů mladších 3 dnů mají poloviční váhu, z pozastavených účtů nulovou.</li>
            <li><b>Unikátní lidé.</b> Více různých reagujících zvyšuje skóre: × (1 + {{ setting('rankings.unique_weight') }} · ln(1 + počet reagujících)).</li>
            <li><b>Stáří.</b> Skóre klesá se stářím odpovědi: ÷ (1 + stáří ve dnech)^({{ setting('rankings.gravity') }} / 4).</li>
            <li><b>Kvalita, ne kvantita.</b> Od jednoho autora se za den počítají nejvýše {{ setting('rankings.max_answers_per_day') }} nejlepší odpovědi.</li>
            <li><b>Známky manipulace.</b> Pokud víc než polovina reakcí přijde z čerstvých účtů, skóre se sníží na polovinu a odpověď nemůže vyhrát hlášku.</li>
        </ol>
        <p>Žebříčky se přepočítávají každou hodinu. Kraje se počítají jen z neanonymních odpovědí lidí, kteří zobrazení svého kraje povolili.</p>
    </article>
</x-layouts::app>
