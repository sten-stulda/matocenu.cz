<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa A – platform foundation: locations, user profile/settings, system settings,
 * feature flags, audit log, legal documents and consents.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');

        // CZ territorial units: country → region (kraj) → district (okres) → municipality (obec).
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->string('type', 20); // country | region | district | municipality
            $table->string('code', 20); // ČSÚ / NUTS / LAU code
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->unsignedInteger('population')->nullable();
            $table->timestampsTz();
            $table->unique(['type', 'code']);
            $table->index(['parent_id', 'name']);
        });
        DB::statement('CREATE INDEX locations_name_trgm ON locations USING gin (name gin_trgm_ops)');

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('avatar_path')->constrained('locations')->nullOnDelete();
        });

        Schema::create('user_settings', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->boolean('anonymous_by_default')->default(false);
            $table->boolean('show_original_by_default')->default(true);
            $table->boolean('humor_on_sensitive')->default(false);
            $table->boolean('show_achievements')->default(true);
            $table->boolean('show_region')->default(true);
            $table->boolean('marketing_emails')->default(false);
            // Per-type notification channels, e.g. {"daily_question":{"database":true,"mail":false}}
            $table->jsonb('notifications')->default('{}');
            $table->timestampsTz();
        });

        Schema::create('user_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['blocker_id', 'blocked_id']);
            $table->index('blocked_id');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->jsonb('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('feature_flags', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(false);
            // Public modules may only be enabled when marked ready by the code (see FeatureFlags).
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80);
            // String id: some subjects (settings, feature flags) have string keys.
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->index(['subject_type', 'subject_id']);
            $table->jsonb('changes')->nullable(); // {"before":{..},"after":{..}} – never secrets
            $table->string('ip_hash', 64)->nullable(); // HMAC of IP, not the raw IP
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['action', 'created_at']);
            $table->index('created_at');
        });

        // Legal texts (terms, privacy, community rules…) – versioned, editable in admin.
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40); // terms | privacy | community | ai | content_license | commercial | cookies | shop_terms
            $table->unsignedInteger('version');
            $table->string('title', 160);
            $table->text('body'); // Markdown
            $table->boolean('requires_consent')->default(false);
            $table->boolean('is_draft_for_review')->default(true); // working draft for legal review
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['type', 'version']);
        });

        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('consent_type', 40); // terms | privacy | ai_processing | marketing | contact_by_providers …
            $table->boolean('granted');
            $table->string('source', 40)->default('web'); // web | api | registration
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['user_id', 'consent_type', 'created_at']);
        });

        // GDPR requests (export, erasure, rectification…) and their processing.
        Schema::create('privacy_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30); // export | erasure | rectification | objection | other
            $table->string('status', 20)->default('open'); // open | in_progress | done | rejected
            $table->text('message')->nullable();
            $table->text('resolution')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_requests');
        Schema::dropIfExists('consent_records');
        Schema::dropIfExists('legal_documents');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('user_blocks');
        Schema::dropIfExists('user_settings');
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('location_id'));
        Schema::dropIfExists('locations');
    }
};
