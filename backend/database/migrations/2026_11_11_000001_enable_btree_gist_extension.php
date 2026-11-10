<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Required by the appointments exclusion constraint (uuid equality inside a GiST index). */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
    }

    public function down(): void
    {
        // Left installed on purpose: other objects may depend on it.
    }
};
