<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa D – rankings (precomputed snapshots), achievements, quotes of the day/week/month,
 * full-text search indexes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 80);
            $table->string('description', 255);
            $table->string('rule', 40); // answers | rated_answers | reactions_received | primary_reactions | daily_answers | quotes | quote_month | need_helper | first_answer
            $table->unsignedInteger('threshold')->default(1);
            $table->boolean('is_title')->default(false); // can be displayed as the user's title
            $table->unsignedSmallInteger('tier')->default(1); // higher = more prestigious title
            $table->string('color', 20)->default('outline');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('achievement_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('awarded_at')->useCurrent();
            $table->unique(['user_id', 'achievement_id']);
        });

        Schema::create('ranking_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40); // answers | authors | questions_interesting | questions_popular | regions | needs
            $table->string('period', 10); // day | week | month
            $table->string('period_key', 20); // 2026-11-27 | 2026-W48 | 2026-11
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete(); // regional rankings
            $table->jsonb('items'); // [{id, score, rank, …}]
            $table->timestampTz('computed_at');
            $table->unique(['type', 'period', 'period_key', 'location_id']);
        });

        // Quote of the day / week / month – the winning real answer, with its legal & licence state.
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('answer_id')->constrained()->cascadeOnDelete();
            $table->string('period', 10); // day | week | month
            $table->string('period_key', 20);
            $table->string('original_text', 500);
            $table->string('display_text', 500);
            $table->boolean('is_stylized');
            $table->foreignId('language_style_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 30)->default('answer');
            $table->decimal('score', 12, 4);
            $table->unsignedInteger('reactions_count');
            $table->unsignedInteger('unique_reactors_count');
            $table->jsonb('history')->default('[]'); // how it came to be: question, style, votes…
            $table->string('legal_status', 20)->default('unchecked'); // unchecked | in_review | cleared | rejected
            $table->string('license_status', 20)->default('none'); // none | requested | granted | refused
            $table->string('commercial_status', 20)->default('not_approved'); // not_approved | approved
            $table->timestampTz('awarded_at');
            $table->timestampsTz();
            $table->unique(['period', 'period_key']);
            $table->index('answer_id');
        });

        // Accent-insensitive Czech full-text search (simple config + unaccent through an IMMUTABLE wrapper).
        DB::statement("CREATE OR REPLACE FUNCTION mtc_unaccent(text) RETURNS text LANGUAGE sql IMMUTABLE PARALLEL SAFE STRICT AS \$\$ SELECT public.unaccent('public.unaccent', \$1) \$\$");
        DB::statement("CREATE INDEX questions_fts ON questions USING gin (to_tsvector('simple', mtc_unaccent(text)))");
        DB::statement("CREATE INDEX answers_fts ON answers USING gin (to_tsvector('simple', mtc_unaccent(display_text)))");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS answers_fts');
        DB::statement('DROP INDEX IF EXISTS questions_fts');
        DB::statement('DROP FUNCTION IF EXISTS mtc_unaccent(text)');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('ranking_snapshots');
        Schema::dropIfExists('user_achievements');
        Schema::dropIfExists('achievements');
    }
};
