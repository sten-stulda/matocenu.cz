<?php

use App\Domain\Administration\ReferenceData\AchievementsSyncer;
use App\Domain\Administration\ReferenceData\AiSyncer;
use App\Domain\Administration\ReferenceData\CategoriesSyncer;
use App\Domain\Administration\ReferenceData\FeatureFlagsSyncer;
use App\Domain\Administration\ReferenceData\LanguageStylesSyncer;
use App\Domain\Administration\ReferenceData\LegalDocumentsSyncer;
use App\Domain\Administration\ReferenceData\LocationsSyncer;
use App\Domain\Administration\ReferenceData\ReactionTypesSyncer;
use App\Domain\Administration\ReferenceData\RolesSyncer;
use App\Domain\AI\Services\AiPersonalData;
use App\Domain\AI\Services\AiPromptsInReview;
use App\Domain\AI\Services\AiPruner;
use App\Domain\Analytics\Widgets\AiWidget;
use App\Domain\Analytics\Widgets\CommunityWidget;
use App\Domain\Identity\Services\CommunityPersonalData;
use App\Domain\Moderation\Services\AppealCount;
use App\Domain\Moderation\Services\QueueCount;
use App\Domain\Needs\Services\NeedsPersonalData;

/*
 | Application defaults. Values marked "setting" can be overridden by admins in
 | Administrace → Systémová nastavení (table settings); these are the fallbacks.
 */
return [
    'display_timezone' => 'Europe/Prague',

    // Order matters (later syncers may reference earlier data).
    'reference_data' => [
        RolesSyncer::class,
        FeatureFlagsSyncer::class,
        LocationsSyncer::class,
        LegalDocumentsSyncer::class,
        CategoriesSyncer::class,
        ReactionTypesSyncer::class,
        LanguageStylesSyncer::class,
        AiSyncer::class,
        AchievementsSyncer::class,
    ],

    // GDPR: modules storing personal data (App\Domain\Identity\Contracts\PersonalDataContributor).
    'personal_data' => [
        CommunityPersonalData::class,
        AiPersonalData::class,
        NeedsPersonalData::class,
    ],

    // Admin menu counters: route name => invokable class returning int.
    'admin_badges' => [
        'admin.moderation' => QueueCount::class,
        'admin.appeals' => AppealCount::class,
        'admin.ai.prompts' => AiPromptsInReview::class,
    ],

    // Retention pruners (invokable classes returning a summary line), run by matocenu:prune.
    'pruners' => [
        AiPruner::class,
    ],

    // Admin dashboard widgets (App\Domain\Analytics\Contracts\DashboardWidget).
    'admin_widgets' => [
        CommunityWidget::class,
        AiWidget::class,
    ],

    // Scheduled tasks shown on the dashboard: cache key suffix => label (see routes/console.php).
    'scheduled_tasks' => [
        'heartbeat' => 'Kontrola plánovače (každou minutu)',
        'prune' => 'Úklid dat podle retenčních lhůt',
        'daily_publish' => 'Publikace otázky dne',
        'daily_evaluate' => 'Denní vyhodnocení otázky dne',
        'rankings' => 'Přepočet žebříčků (každou hodinu)',
        'quotes' => 'Vyhodnocení hlášky dne / týdne / měsíce',
        'needs_digest' => 'Týdenní souhrn potřeb pro sledující',
    ],

    'settings' => [
        // key => [default, type, label, group]
        'brand.slogan' => ['Skutečné otázky. Skuteční lidé. Neskutečné odpovědi.', 'string', 'Hlavní slogan', 'Značka'],
        'brand.slogans_alt' => [[
            'Jedna otázka. Sto znaků. Nekonečně mnoho lidských příběhů.',
            'Život berem vážně. Sebe už míň.',
            'Tož pověz, stojí to za groš, či za starú belu?',
            'To má cenu!',
        ], 'list', 'Alternativní slogany (jeden na řádek)', 'Značka'],
        'answers.max_length' => [100, 'int', 'Maximální délka odpovědi (znaků)', 'Odpovědi'],
        'answers.max_per_question' => [1, 'int', 'Počet veřejných odpovědí uživatele na otázku', 'Odpovědi'],
        'answers.edit_window_minutes' => [0, 'int', 'Doba pro úpravu odpovědi v minutách (0 = bez omezení)', 'Odpovědi'],
        'answers.require_verified_email' => [true, 'bool', 'Odpovídat mohou jen uživatelé s ověřeným e-mailem', 'Odpovědi'],
        'questions.user_questions_need_approval' => [true, 'bool', 'Otázky uživatelů musí schválit moderátor', 'Otázky'],
        'questions.daily_publish_time' => ['06:00', 'string', 'Čas zveřejnění otázky dne (HH:MM)', 'Otázky'],
        'comments.max_depth' => [2, 'int', 'Maximální hloubka vláken komentářů', 'Komentáře'],
        'comments.max_length' => [500, 'int', 'Maximální délka komentáře', 'Komentáře'],
        'reactions.allow_change' => [true, 'bool', 'Uživatel může reakci změnit / odebrat', 'Reakce'],
        'rankings.max_answers_per_day' => [3, 'int', 'Do žebříčku se počítá max. N odpovědí autora za den', 'Žebříčky'],
        'rankings.gravity' => [1.5, 'float', 'Gravitace (stárnutí) skóre', 'Žebříčky'],
        'rankings.unique_weight' => [1.0, 'float', 'Váha unikátních reagujících', 'Žebříčky'],
        'moderation.auto_hide_reports' => [3, 'int', 'Automaticky skrýt po N nahlášeních od různých uživatelů', 'Moderace'],
        'moderation.blocked_words' => [[], 'list', 'Zakázaná slova (jedno na řádek)', 'Moderace'],
        'ai.enabled' => [true, 'bool', 'AI stylizace zapnuta', 'AI'],
        'ai.daily_budget_czk' => ['50.00', 'decimal', 'Denní rozpočet AI (Kč)', 'AI'],
        'ai.monthly_budget_czk' => ['1000.00', 'decimal', 'Měsíční rozpočet AI (Kč)', 'AI'],
        'ai.user_daily_generations' => [20, 'int', 'Generování na uživatele za den', 'AI'],
        'ai.regenerations_per_answer' => [2, 'int', 'Opakovaná generování na jednu odpověď', 'AI'],
        'ai.variants_per_request' => [3, 'int', 'Počet variant na jedno generování', 'AI'],
        'ai.usd_czk_rate' => ['23.50', 'decimal', 'Kurz USD → CZK pro přepočet nákladů', 'AI'],
        'daily.style_schedule' => [['1' => 'moravak', '2' => 'starocech', '3' => 'sarkastik', '4' => 'sedlak', '5' => 'hospodsky', '6' => 'basnik', '7' => 'filozof'], 'json', 'Jazyková osobnost dne podle dne v týdnu (1 = pondělí)', 'Otázka dne'],
        'ai.semantic_check' => [false, 'bool', 'Kontrola významu dalším AI voláním (zvyšuje náklady)', 'AI'],
        'ai.auto_publish_suggestions' => [false, 'bool', 'AI návrhy otázek se smí zveřejnit automaticky', 'AI'],
        'privacy.retention_days_audit' => [730, 'int', 'Retence auditního logu (dny)', 'Soukromí'],
        'privacy.retention_days_ai_logs' => [180, 'int', 'Retence AI logů (dny)', 'Soukromí'],
        'privacy.retention_days_deleted_content' => [30, 'int', 'Za kolik dní se fyzicky smaže obsah smazaného účtu', 'Soukromí'],
        'seo.default_description' => ['Česká komunitní platforma: jedna otázka denně, odpovědi do 100 znaků a hlášky v nářečí. AI je vypravěčem, člověk zůstává autorem.', 'string', 'Výchozí meta description', 'SEO'],
        'seo.indexing' => [true, 'bool', 'Povolit indexaci vyhledávači', 'SEO'],
    ],

    // Feature flags: key => [name, description, default, public_ready]
    // public_ready=false means the module is not complete – admins can't enable it publicly.
    'features' => [
        'questions' => ['Má to cenu říct?', 'Otázky, odpovědi, reakce a komentáře.', true, true],
        'needs' => ['Má to cenu řešit?', 'Co lidem chybí – skutečné potřeby a sběr zájmu.', true, true],
        'opportunities' => ['Má to cenu uskutečnit?', 'Agregované podnikatelské příležitosti a nabídky realizátorů.', true, true],
        'quotes' => ['Hlášky', 'Hláška dne / týdne / měsíce a hlasování.', true, true],
        'google_login' => ['Přihlášení přes Google', 'Zobrazí tlačítko, pokud je nastaven GOOGLE_CLIENT_ID.', true, true],
        'api' => ['REST API v1', 'Veřejné a autentizované API /api/v1.', true, true],
        'web_push' => ['Web push notifikace', 'Připravená integrace, chybí VAPID klíče a service worker.', false, false],
        'shop' => ['Má to cenu koupit?', 'E-shop s originálními produkty – čeká na obchodní, právní a platební podmínky.', false, false],
        'marketplace' => ['Má to ještě cenu?', 'Bazar – zatím jen datový základ.', false, false],
        'payments' => ['Platby a předplatné', 'Čeká na poskytovatele plateb a obchodní podmínky.', false, false],
        'mcp' => ['MCP server', 'Budoucí rozhraní pro AI asistenty nad veřejnými daty.', false, false],
    ],

    'ai' => [
        'http_timeout' => 20,
        'cache_hmac_key' => env('AI_CACHE_HMAC_KEY'),
        // Optional env fallbacks for provider keys (admin UI keys take precedence).
        'provider_keys' => [
            'openrouter' => env('OPENROUTER_API_KEY'),
            'openai' => env('OPENAI_API_KEY'),
            'anthropic' => env('ANTHROPIC_API_KEY'),
            'gemini' => env('GEMINI_API_KEY'),
        ],
        // Answers that are safe to cache globally per question (short, non-personal).
        'shared_cache_answers' => ['ano', 'ne', 'já', 'ja', 'nevím', 'nevim', 'možná', 'mozna', 'nic', 'všechno', 'vsechno', 'děkuji', 'dekuji', 'díky', 'diky', 'čas', 'cas', 'peníze', 'penize', 'zdraví', 'zdravi', 'klid', 'spánek', 'spanek', 'láska', 'laska', 'rodina'],
    ],
];
