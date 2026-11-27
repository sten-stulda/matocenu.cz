<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa B – community: categories, questions (versioned), daily questions, answers (versioned),
 * reactions, comments, reports, moderation history + appeals, bookmarks, follows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 90)->unique();
            $table->string('description', 300)->nullable();
            $table->string('color', 20)->default('forest'); // design token: forest | mustard | terra | charcoal | sage | rust
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_sensitive')->default(false); // questions inherit "humor off" by default
            $table->timestampsTz();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('open');
            $table->string('slug', 140)->unique();
            $table->string('text', 300); // current original text (history in question_versions)
            $table->unsignedInteger('version')->default(1);
            $table->string('moderation_status', 20)->default('pending');
            $table->string('publication_status', 20)->default('draft'); // draft | scheduled | published | closed | archived
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('closes_at')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_daily_candidate')->default(false);
            $table->unsignedInteger('answers_count')->default(0);
            $table->unsignedInteger('reactions_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->unsignedInteger('followers_count')->default(0);
            $table->timestampTz('last_activity_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['publication_status', 'moderation_status', 'published_at']);
            $table->index(['category_id', 'published_at']);
            $table->index(['location_id', 'published_at']);
            $table->index('last_activity_at');
        });
        DB::statement('CREATE INDEX questions_text_trgm ON questions USING gin (text gin_trgm_ops)');

        Schema::create('question_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('text', 300);
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 300)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['question_id', 'version']);
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('label', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedInteger('answers_count')->default(0);
            $table->index(['question_id', 'sort_order']);
        });

        Schema::create('daily_questions', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique(); // calendar day in Europe/Prague
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('planned'); // planned | approved | published | skipped
            $table->foreignId('planned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('evaluated_at')->nullable();
            $table->jsonb('evaluation')->nullable(); // daily summary: answers, reactions, top answer id…
            $table->timestampsTz();
            $table->index(['status', 'date']);
        });

        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_text', 500); // what the person wrote – basis for statistics
            $table->string('display_text', 500); // published form (original or chosen stylisation)
            $table->boolean('is_stylized')->default(false);
            $table->boolean('show_original')->default(false); // author's consent to show original next to stylisation
            $table->boolean('is_anonymous')->default(false);
            $table->foreignId('option_id')->nullable()->constrained('question_options')->nullOnDelete();
            $table->decimal('numeric_value', 14, 2)->nullable();
            $table->string('moderation_status', 20)->default('approved');
            $table->unsignedInteger('reactions_count')->default(0);
            $table->unsignedInteger('unique_reactors_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->decimal('score', 12, 4)->default(0); // ranking score (see RankingScore)
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('edited_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['question_id', 'moderation_status', 'score']);
            $table->index(['question_id', 'published_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['moderation_status', 'published_at']);
        });
        DB::statement('CREATE INDEX answers_display_trgm ON answers USING gin (display_text gin_trgm_ops)');

        Schema::create('answer_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('answer_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('original_text', 500);
            $table->string('display_text', 500);
            $table->boolean('is_stylized');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['answer_id', 'version']);
        });

        Schema::create('reaction_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('label', 40);
            $table->string('icon', 30)->default('coin');
            $table->string('color', 20)->default('forest');
            $table->decimal('weight', 4, 2)->default(1); // contribution to ranking score
            $table->boolean('counts_as_funny')->default(false); // for "nejvtipnější" rankings
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        // One reaction per user per item; DB unique key protects against double-counting and races.
        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('reactable');
            $table->foreignId('reaction_type_id')->constrained()->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['user_id', 'reactable_type', 'reactable_id']);
            $table->index(['reactable_type', 'reactable_id', 'reaction_type_id']);
            $table->index('created_at');
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->morphs('commentable'); // question | answer
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->string('body', 1000);
            $table->string('moderation_status', 20)->default('approved');
            $table->unsignedInteger('reactions_count')->default(0);
            $table->timestampTz('edited_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['commentable_type', 'commentable_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->morphs('reportable');
            $table->string('reason', 30);
            $table->string('note', 500)->nullable();
            $table->string('status', 20)->default('open'); // open | resolved | dismissed
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
            $table->unique(['reporter_id', 'reportable_type', 'reportable_id']);
            $table->index(['status', 'created_at']);
        });

        // Full moderation history of every content item.
        Schema::create('moderation_actions', function (Blueprint $table) {
            $table->id();
            $table->morphs('moderatable');
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete(); // null = automatic check
            $table->string('action', 30); // auto_flag | approve | reject | hide | restore | edit | appeal_upheld | appeal_overturned
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('reason', 500)->nullable();
            $table->string('risk', 10)->nullable(); // low | medium | high
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['moderatable_type', 'moderatable_id', 'created_at']);
        });

        Schema::create('moderation_appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moderation_action_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('message', 1000);
            $table->string('status', 20)->default('open'); // open | upheld | overturned
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision_note', 500)->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();
            $table->unique(['moderation_action_id', 'user_id']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('bookmarkable');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['user_id', 'bookmarkable_type', 'bookmarkable_id']);
        });

        Schema::create('follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('followable'); // question | category | need
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['user_id', 'followable_type', 'followable_id']);
        });
    }

    public function down(): void
    {
        foreach (['follows', 'bookmarks', 'moderation_appeals', 'moderation_actions', 'reports', 'comments', 'reactions', 'reaction_types', 'answer_versions', 'answers', 'daily_questions', 'question_options', 'question_versions', 'questions', 'categories'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
