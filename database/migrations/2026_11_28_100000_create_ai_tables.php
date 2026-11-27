<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa C – AI: language styles (versioned), providers & models, PromptOps (prompts,
 * versions, test cases, results), generations, cache, variants, usage logs.
 * Money: USD prices per 1M tokens decimal(12,6); costs decimal(14,6) USD / decimal(14,4) CZK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('language_styles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 60)->unique();
            $table->string('description', 200)->nullable();
            $table->string('icon', 4); // short badge text, e.g. "Mo"
            $table->string('color', 20)->default('mustard');
            $table->text('instructions')->nullable(); // AI instruction for this style
            $table->jsonb('examples')->default('[]'); // [{"original": "...", "styled": "..."}]
            $table->jsonb('content_types')->default('["question","answer"]');
            $table->string('sensitive_policy', 20)->default('disabled'); // allowed | disabled (humour off on sensitive topics)
            $table->boolean('is_humorous')->default(true);
            $table->boolean('is_random')->default(false); // "Náhodný styl" picks another active style
            $table->boolean('is_original')->default(false); // "Originál – bez AI úprav"
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();
        });

        Schema::create('language_style_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('language_style_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->text('instructions')->nullable();
            $table->jsonb('examples')->default('[]');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['language_style_id', 'version']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('favorite_style_id')->nullable()->after('location_id')->constrained('language_styles')->nullOnDelete();
        });
        Schema::table('answers', function (Blueprint $table) {
            $table->foreignId('language_style_id')->nullable()->after('is_stylized')->constrained()->nullOnDelete();
        });
        Schema::table('daily_questions', function (Blueprint $table) {
            $table->foreignId('language_style_id')->nullable()->after('question_id')->constrained()->nullOnDelete();
        });

        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name', 80);
            $table->string('driver', 30); // openrouter | openai | anthropic | gemini | ollama | openai_compatible | fake
            $table->string('base_url', 255)->nullable();
            $table->text('api_key')->nullable(); // encrypted (APP_KEY)
            $table->boolean('is_active')->default(false);
            $table->unsignedSmallInteger('timeout_seconds')->default(20);
            $table->unsignedTinyInteger('max_retries')->default(1);
            $table->timestampTz('verified_at')->nullable();
            $table->string('verify_message', 300)->nullable();
            $table->timestampsTz();
        });

        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_provider_id')->constrained()->cascadeOnDelete();
            $table->string('model', 120); // provider model id, e.g. google/gemini-2.5-flash-lite
            $table->string('name', 120);
            $table->decimal('input_price_per_mtok', 12, 6)->nullable(); // USD per 1M input tokens
            $table->decimal('output_price_per_mtok', 12, 6)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('max_output_tokens')->default(400);
            $table->decimal('temperature', 3, 2)->nullable();
            $table->boolean('supports_temperature')->default(true);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_fallback')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampTz('prices_synced_at')->nullable();
            $table->timestampsTz();
            $table->unique(['ai_provider_id', 'model']);
        });
        DB::statement('CREATE UNIQUE INDEX ai_models_one_primary ON ai_models (is_primary) WHERE is_primary');
        DB::statement('CREATE UNIQUE INDEX ai_models_one_fallback ON ai_models (is_fallback) WHERE is_fallback');

        Schema::create('ai_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique(); // answer_stylize | question_stylize | semantic_check
            $table->string('name', 120);
            $table->string('purpose', 500);
            $table->jsonb('variables')->default('[]');
            $table->timestampsTz();
        });

        Schema::create('ai_prompt_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_prompt_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->text('system_template');
            $table->text('user_template');
            $table->string('status', 20)->default('draft'); // draft | review | approved | production | archived
            $table->string('change_note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('production_at')->nullable();
            $table->timestampsTz();
            $table->unique(['ai_prompt_id', 'version']);
        });
        DB::statement("CREATE UNIQUE INDEX ai_prompt_versions_one_production ON ai_prompt_versions (ai_prompt_id) WHERE status = 'production'");

        Schema::create('ai_prompt_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_prompt_version_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 300)->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('ai_prompt_test_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_prompt_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->jsonb('variables');
            $table->jsonb('expectations')->default('{}'); // {"must_contain_numbers":true,"max_length":100,"must_not_contain":["…"]}
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('ai_prompt_test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_prompt_test_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_prompt_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_model_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('run_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('output')->nullable();
            $table->boolean('passed');
            $table->jsonb('checks')->default('[]');
            $table->decimal('cost_czk', 14, 4)->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['ai_prompt_version_id', 'created_at']);
        });

        // One user-visible generation request (answer stylisation, question variant…).
        Schema::create('ai_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('operation', 40);
            $table->foreignId('question_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('language_style_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('style_version')->nullable();
            $table->foreignId('ai_prompt_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_model_id')->nullable()->constrained()->nullOnDelete();
            $table->string('input_hash', 64); // HMAC of the normalised original – never the raw text
            $table->string('cache_key', 64)->index();
            $table->boolean('cache_hit')->default(false);
            $table->boolean('is_regeneration')->default(false);
            $table->string('status', 20); // succeeded | failed | rejected | unavailable | budget_exceeded
            $table->string('error', 300)->nullable();
            $table->timestampsTz();
            $table->index(['user_id', 'created_at']);
            $table->index(['operation', 'created_at']);
        });

        // Selectable stylisations of one answer draft (VariantResolver checks ownership + input hash).
        Schema::create('answer_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_generation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_style_id')->nullable()->constrained()->nullOnDelete();
            $table->string('input_hash', 64);
            $table->string('text', 500);
            $table->unsignedTinyInteger('position');
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['user_id', 'question_id']);
        });

        Schema::create('question_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('question_version');
            $table->foreignId('language_style_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('style_version');
            $table->foreignId('ai_prompt_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_generation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('text', 300);
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['question_id', 'question_version', 'language_style_id', 'style_version', 'ai_prompt_version_id'], 'question_variants_unique_combo');
            $table->index(['question_id', 'status']);
        });

        Schema::create('ai_generation_cache', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key', 64)->unique(); // HMAC-SHA256 of normalised inputs
            $table->string('scope', 30); // global | user:{id}
            $table->string('operation', 40);
            $table->jsonb('variants'); // list of strings
            $table->foreignId('ai_model_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_prompt_version_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('cost_czk', 14, 4)->default(0); // what generating it cost (for savings)
            $table->unsignedInteger('hits')->default(0);
            $table->timestampTz('last_hit_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();
            $table->index(['scope', 'created_at']);
        });

        // Every AI call (and cache hit). No prompt or answer text is stored here.
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_generation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 40)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('operation', 40);
            $table->foreignId('ai_prompt_version_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('extra_tokens')->default(0); // reasoning / other billed tokens if reported
            $table->decimal('cost_usd', 14, 6)->default(0);
            $table->decimal('cost_czk', 14, 4)->default(0);
            $table->boolean('cost_is_estimate')->default(true);
            $table->decimal('saved_czk', 14, 4)->default(0); // cache hits: what the call would have cost
            $table->unsignedInteger('duration_ms')->default(0);
            $table->boolean('cache_hit')->default(false);
            $table->string('status', 20); // ok | error | rejected | cache_hit
            $table->string('error_type', 60)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index('created_at');
            $table->index(['operation', 'created_at']);
            $table->index(['model', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['ai_usage_logs', 'ai_generation_cache', 'question_variants', 'answer_variants', 'ai_generations', 'ai_prompt_test_results', 'ai_prompt_test_cases', 'ai_prompt_status_history', 'ai_prompt_versions', 'ai_prompts', 'ai_models', 'ai_providers'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('daily_questions', fn (Blueprint $t) => $t->dropConstrainedForeignId('language_style_id'));
        Schema::table('answers', fn (Blueprint $t) => $t->dropConstrainedForeignId('language_style_id'));
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('favorite_style_id'));
        Schema::dropIfExists('language_style_versions');
        Schema::dropIfExists('language_styles');
    }
};
