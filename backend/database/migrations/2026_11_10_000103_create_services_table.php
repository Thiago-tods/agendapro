<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('category', 60)->nullable();
            $table->unsignedInteger('price_cents');
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('buffer_minutes')->default(0);
            $table->char('color', 7);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'id']);
            $table->index(['company_id', 'is_active', 'name']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE services
                ADD CONSTRAINT services_price_check CHECK (price_cents >= 0),
                ADD CONSTRAINT services_duration_check CHECK (duration_minutes BETWEEN 5 AND 720),
                ADD CONSTRAINT services_buffer_check CHECK (buffer_minutes BETWEEN 0 AND 240),
                ADD CONSTRAINT services_color_check CHECK (color ~ '^#[0-9A-F]{6}$')
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
