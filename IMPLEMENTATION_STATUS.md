# Stav implementace – MáToCenu.cz

Průběžný stav podle `MASTER_PROMPT.md` §46. Aktualizuje se s každým PR.

| Etapa | Obsah | Stav | PR |
|---|---|---|---|
| A – základ | Laravel 13, PostgreSQL, Redis, Docker, CI, autentizace (Fortify + Google), role a oprávnění, design systém, layouty, homepage, administrace (uživatelé, role, nastavení, feature flags, audit, GDPR, právní dokumenty), účet uživatele (profil, 2FA, relace, export, smazání) | hotovo | #1 |
| B – komunita | otázky (verze, typy, podobné otázky), odpovědi (limit 100 znaků Unicode, historie, anonymita, ochrana originálu), kategorie, otázka dne (kalendář, automatická publikace, vyhodnocení), reakce (unikátní klíč, skóre s důvěrou), komentáře (hloubka, blokování), nahlašování, moderace (automatické kontroly, fronta, historie, odvolání), profily, záložky, sledování, notifikace | hotovo | #2 |
| C – AI | AIProviderInterface (OpenRouter, OpenAI, Ollama/OpenAI-kompatibilní, Anthropic, Gemini, Fake), primární + záložní model, 14 jazykových osobností (verze), stylizace odpovědí (6 kroků, cache HMAC, sdílená cache pro obecné odpovědi, zámek proti souběhu, validace výstupu + 1 oprava), stylizace otázek se schvalováním, osobnost dne, PromptOps (verze, stavy, 4 oči, rollback, testovací sada), monitoring nákladů, rozpočty a kvóty, šifrované klíče, SSRF ochrana | hotovo | #3 |
| D – rozšíření | žebříčky (transparentní skóre: důvěra, unikátní lidé, stáří, limit/den, detekce manipulace; hodinové snapshoty, delty), ocenění a tituly ze zadání, hláška dne/týdne/měsíce (jen ze skutečných reakcí, právní stavy výchozí neschváleno), notifikace ocenění a hlášek, sdílecí PNG karty (OFL písma), Open Graph, sitemap, fulltext bez diakritiky | hotovo | #4 |
| E – potřeby | potřeby, lokality, zájem, ochota platit, nabídky, analytika | čeká | |
| F – monetizace | právní evidence, licence, slogany, produkty, bazar (vypnuté) | čeká | |
| G – dokončení | bezpečnost, optimalizace, dokumentace, deployment | čeká | |

## Poslední ověření
- Etapa D: 135 testů, 453 asercí – vše prošlo; Pint OK; Larastan bez chyb.
- Etapa C: 122 testů, 404 asercí – vše prošlo (AI jen přes FakeProvider / Http::fake); Pint OK; Larastan bez chyb.
- Etapa B: 93 testů, 315 asercí – vše prošlo; Pint OK; Larastan bez chyb.
- Etapa A: `php artisan test` – 39 testů, 141 asercí, vše prošlo (PostgreSQL `matocenu_test`); Pint OK; Larastan level 5 bez chyb.
