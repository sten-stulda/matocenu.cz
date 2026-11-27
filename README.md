# MáToCenu.cz

**Skutečné otázky. Skuteční lidé. Neskutečné odpovědi.**

Česká komunitní platforma: jedna otázka denně, odpovědi do 100 znaků, AI stylizace do nářečí a hlášek – AI je vypravěčem, člověk zůstává autorem myšlenky.

- Zadání: [`MASTER_PROMPT.md`](MASTER_PROMPT.md) · design: [`design/`](design/) · stav: [`IMPLEMENTATION_STATUS.md`](IMPLEMENTATION_STATUS.md)

## Rychlý start (Docker)

```bash
cp .env.example .env
# vyplň DB_PASSWORD (libovolné silné heslo) a vygeneruj klíč:
php artisan key:generate        # nebo: docker compose run --rm app php artisan key:generate --show
docker compose up -d --build
docker compose exec app php artisan matocenu:create-admin you@example.com "Tvoje přezdívka"
```

Web běží na http://127.0.0.1:8100, e-maily zachytává Mailpit na http://127.0.0.1:8025.
Kontejner `app` při startu spustí migrace a `matocenu:sync-reference-data` (role, feature flags, kraje a okresy, právní texty…).

## Vývoj na hostiteli

```bash
composer install
docker compose up -d pgsql redis mailpit          # PostgreSQL 127.0.0.1:54333, Redis 63792
php artisan migrate && php artisan matocenu:sync-reference-data
npm ci && npm run build                             # nebo: docker run --rm -v $PWD:/app -w /app node:22-alpine sh -c "npm ci && npm run build"
php artisan serve
php artisan test                                    # testy běží na DB matocenu_test
```

Podrobnosti: `INSTALLATION.md`, `DEPLOYMENT.md`, `ARCHITECTURE.md` (doplňují se po etapách).
