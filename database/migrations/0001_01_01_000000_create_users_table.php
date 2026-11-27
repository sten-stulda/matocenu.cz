<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40); // public nickname (přezdívka)
            $table->string('email')->unique();
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('password')->nullable(); // null = only Google login
            $table->string('google_id')->nullable()->unique();
            $table->string('avatar_path')->nullable();
            $table->string('status', 20)->default('active'); // active | suspended | deleted
            $table->timestampTz('suspended_until')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->rememberToken();
            $table->timestampsTz();
            $table->timestampTz('deleted_at')->nullable(); // account deleted = anonymised, row kept for content integrity
        });

        // Nicknames are unique case-insensitively.
        DB::statement('CREATE UNIQUE INDEX users_name_lower_unique ON users (lower(name))');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestampTz('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
