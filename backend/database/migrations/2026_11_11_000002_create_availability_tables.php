<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Availability is described at two levels, distinguished by professional_id:
 * NULL = company (business hours), set = one professional (working hours).
 * Times in working_hours / availability_exceptions are local wall-clock times in the
 * company's timezone; blocked_periods are absolute instants (timestamptz, UTC).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Weekly recurring periods. Breaks are the gaps between periods of the same weekday.
        Schema::create('working_hours', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->uuid('professional_id')->nullable();
            $table->unsignedSmallInteger('weekday'); // 0 = Sunday ... 6 = Saturday
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->foreign(['company_id', 'professional_id'])
                ->references(['company_id', 'id'])->on('professionals')->cascadeOnDelete();
            $table->index(['company_id', 'professional_id', 'weekday']);
        });

        // Date-specific overrides: a closed day, or replacement hours for that date.
        Schema::create('availability_exceptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->uuid('professional_id')->nullable();
            $table->date('date');
            $table->boolean('is_closed');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('reason', 200)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['company_id', 'professional_id'])
                ->references(['company_id', 'id'])->on('professionals')->cascadeOnDelete();
            $table->index(['company_id', 'date']);
        });

        // Absolute unavailable ranges (vacation, meeting, maintenance), half-open [starts_at, ends_at).
        Schema::create('blocked_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->uuid('professional_id')->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('reason', 200)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['company_id', 'professional_id'])
                ->references(['company_id', 'id'])->on('professionals')->cascadeOnDelete();
            $table->index(['company_id', 'starts_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE working_hours
                ADD CONSTRAINT working_hours_weekday_check CHECK (weekday BETWEEN 0 AND 6),
                ADD CONSTRAINT working_hours_range_check CHECK (start_time < end_time)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE availability_exceptions
                ADD CONSTRAINT availability_exceptions_shape_check CHECK (
                    (is_closed AND start_time IS NULL AND end_time IS NULL)
                    OR (NOT is_closed AND start_time IS NOT NULL AND end_time IS NOT NULL AND start_time < end_time)
                )
        SQL);

        DB::statement('ALTER TABLE blocked_periods ADD CONSTRAINT blocked_periods_range_check CHECK (starts_at < ends_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_periods');
        Schema::dropIfExists('availability_exceptions');
        Schema::dropIfExists('working_hours');
    }
};
