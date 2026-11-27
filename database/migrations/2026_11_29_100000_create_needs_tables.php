<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa E – real needs ("Co lidem chybí"), expressions of interest (non-binding vs. commitment,
 * declared willingness to pay), offers from providers, consent-based relay messages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('needs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30); // problem | missing_service | missing_product | municipality | business_idea | joint_solution
            $table->string('slug', 160)->unique();
            $table->string('title', 140);
            $table->string('description', 1500);
            $table->string('urgency', 10)->default('medium'); // low | medium | high
            $table->string('status', 20)->default('open'); // open | in_progress | solved | closed
            $table->string('solved_note', 500)->nullable();
            $table->string('moderation_status', 20)->default('approved');
            $table->boolean('is_anonymous')->default(false);
            $table->unsignedInteger('interested_count')->default(0); // unique people with any interest
            $table->unsignedInteger('need_too_count')->default(0);
            $table->unsignedInteger('would_use_count')->default(0);
            $table->unsignedInteger('would_pay_count')->default(0);
            $table->unsignedInteger('can_deliver_count')->default(0);
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('offers_count')->default(0);
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('last_activity_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['moderation_status', 'status', 'published_at']);
            $table->index(['location_id', 'interested_count']);
            $table->index(['category_id', 'interested_count']);
        });
        DB::statement("CREATE INDEX needs_fts ON needs USING gin (to_tsvector('simple', mtc_unaccent(title || ' ' || description)))");
        DB::statement('CREATE INDEX needs_title_trgm ON needs USING gin (title gin_trgm_ops)');

        Schema::create('need_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('need_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20); // like | need_too | would_use | would_pay | can_deliver
            $table->decimal('amount', 12, 2)->nullable(); // declared, non-binding
            $table->string('currency', 3)->nullable();
            $table->string('frequency', 20)->nullable(); // once | per_visit | per_hour | weekly | monthly | yearly
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('urgency', 10)->nullable();
            $table->boolean('is_commitment')->default(false); // explicit "závazný zájem" – still not a purchase
            $table->boolean('contact_consent')->default(false); // providers may message me through the platform
            $table->timestampsTz();
            $table->unique(['need_id', 'user_id', 'kind']);
            $table->index(['need_id', 'kind']);
            $table->index('created_at');
        });

        Schema::create('need_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('need_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('description', 1500);
            $table->string('price_text', 120)->nullable();
            $table->string('status', 20)->default('pending'); // pending | accepted | declined | withdrawn
            $table->string('moderation_status', 20)->default('approved');
            $table->timestampTz('last_broadcast_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unique(['need_id', 'user_id']);
        });

        // Messages a provider sends to consenting interested people (relayed, no contact data revealed).
        Schema::create('need_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('need_offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->cascadeOnDelete(); // null = broadcast to consenting
            $table->string('body', 1000);
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['need_offer_id', 'created_at']);
        });

        Schema::create('need_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('need_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['need_status_history', 'need_messages', 'need_offers', 'need_interests'] as $t) {
            Schema::dropIfExists($t);
        }
        DB::statement('DROP INDEX IF EXISTS needs_fts');
        Schema::dropIfExists('needs');
    }
};
