# MáToCenu.cz – pokyny pro vývoj

- Zadání: `MASTER_PROMPT.md`, design: `design/` (statické HTML + PNG). Stav: `IMPLEMENTATION_STATUS.md`.
- Stack: Laravel 13, Livewire 4, Tailwind 4, Alpine, PostgreSQL 17, Redis, Horizon, Fortify, Sanctum, Socialite, Spatie Permission, Pest.
- Doménová logika v `app/Domain/<Oblast>` (Actions, Services, DTO, Enums, Events, Exceptions). Modely v `app/Models`.
  Controllery a Livewire komponenty jen orchestrují – žádná obchodní logika.
- UI texty česky. Barvy jen přes design tokeny (`resources/css/app.css`).
- Testy: `php artisan test` (PostgreSQL DB `matocenu_test` z docker compose na 127.0.0.1:54333). AI vždy přes `FakeProvider`.
- Změny přes větev + PR (`gh pr create`), commity bez podpisu AI.
