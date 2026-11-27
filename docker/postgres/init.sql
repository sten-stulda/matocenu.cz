-- Separate database for the automated test suite (php artisan test on the host).
CREATE DATABASE matocenu_test OWNER matocenu;
\connect matocenu_test
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;
