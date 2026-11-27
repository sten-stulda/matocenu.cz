<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Etapa B introduced a morph map (user, question, answer, comment, category).
 * Rows written before it store fully-qualified class names – normalise them to aliases.
 */
return new class extends Migration
{
    private const MAP = [
        'App\\Models\\User' => 'user',
        'App\\Models\\Question' => 'question',
        'App\\Models\\Answer' => 'answer',
        'App\\Models\\Comment' => 'comment',
        'App\\Models\\Category' => 'category',
    ];

    private const COLUMNS = [
        ['model_has_roles', 'model_type'],
        ['model_has_permissions', 'model_type'],
        ['personal_access_tokens', 'tokenable_type'],
        ['notifications', 'notifiable_type'],
        ['audit_logs', 'subject_type'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as [$table, $column]) {
            foreach (self::MAP as $class => $alias) {
                DB::table($table)->where($column, $class)->update([$column => $alias]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as [$table, $column]) {
            foreach (self::MAP as $class => $alias) {
                DB::table($table)->where($column, $alias)->update([$column => $class]);
            }
        }
    }
};
