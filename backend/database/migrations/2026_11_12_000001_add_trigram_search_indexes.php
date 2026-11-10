<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Searches use ILIKE '%term%' (customers by name, e-mail and phone; professionals and services by
 * name; services also by category). B-tree indexes cannot serve a leading wildcard; GIN trigram
 * indexes can, so these lists stay fast as companies grow.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const COLUMNS = [
        'customers' => ['name', 'email', 'phone'],
        'professionals' => ['name'],
        'services' => ['name', 'category'],
    ];

    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement("CREATE INDEX IF NOT EXISTS {$table}_{$column}_trgm_index ON {$table} USING gin ({$column} gin_trgm_ops)");
            }
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement("DROP INDEX IF EXISTS {$table}_{$column}_trgm_index");
            }
        }

        // pg_trgm is left installed on purpose: other objects may depend on it.
    }
};
