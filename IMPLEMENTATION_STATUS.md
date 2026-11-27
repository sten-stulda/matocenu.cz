# Stav implementace – MáToCenu.cz

Průběžný stav podle `MASTER_PROMPT.md` §46. Aktualizuje se s každým PR.

| Etapa | Obsah | Stav | PR |
|---|---|---|---|
| A – základ | Laravel 13, PostgreSQL, Redis, Docker, CI, autentizace (Fortify + Google), role a oprávnění, design systém, layouty, homepage, administrace (uživatelé, role, nastavení, feature flags, audit, GDPR, právní dokumenty), účet uživatele (profil, 2FA, relace, export, smazání) | hotovo | #1 |
| B – komunita | otázky, odpovědi, kategorie, otázka dne, reakce, komentáře, moderace, profily | čeká | |
| C – AI | providery, OpenRouter, jazykové styly, stylizace, PromptOps, cache, náklady, limity | čeká | |
| D – rozšíření | žebříčky, ocenění, hlášky, notifikace, sdílení, vyhledávání | čeká | |
| E – potřeby | potřeby, lokality, zájem, ochota platit, nabídky, analytika | čeká | |
| F – monetizace | právní evidence, licence, slogany, produkty, bazar (vypnuté) | čeká | |
| G – dokončení | bezpečnost, optimalizace, dokumentace, deployment | čeká | |

## Poslední ověření
- Etapa A: `php artisan test` – 39 testů, 141 asercí, vše prošlo (PostgreSQL `matocenu_test`); Pint OK; Larastan level 5 bez chyb.
