<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professionals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('title', 80)->nullable();
            $table->text('bio')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 15)->nullable();
            $table->char('color', 7);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Target of composite foreign keys that keep associations inside one company.
            $table->unique(['company_id', 'id']);
            $table->index(['company_id', 'is_active', 'name']);
        });

        DB::statement("ALTER TABLE professionals ADD CONSTRAINT professionals_color_check CHECK (color ~ '^#[0-9A-F]{6}$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('professionals');
    }
};
