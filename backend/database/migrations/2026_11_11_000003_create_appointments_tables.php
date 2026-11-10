<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->uuid('customer_id');
            $table->uuid('professional_id');
            $table->uuid('service_id');

            // Half-open intervals in UTC:
            //   service time  [starts_at, ends_at)
            //   occupied time [starts_at, occupied_until)  = service time + buffer
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->timestampTz('occupied_until');

            $table->string('status', 20);

            // Service snapshot at booking time; later catalog changes never alter past appointments.
            $table->string('service_name', 120);
            $table->unsignedInteger('service_price_cents');
            $table->unsignedSmallInteger('service_duration_minutes');
            $table->unsignedSmallInteger('service_buffer_minutes');

            $table->text('notes')->nullable();

            $table->timestampTz('checked_in_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            // Composite keys: an appointment can only reference records of its own company.
            // Default ON DELETE RESTRICT: records with appointments cannot be hard-deleted.
            $table->foreign(['company_id', 'customer_id'])->references(['company_id', 'id'])->on('customers');
            $table->foreign(['company_id', 'professional_id'])->references(['company_id', 'id'])->on('professionals');
            $table->foreign(['company_id', 'service_id'])->references(['company_id', 'id'])->on('services');

            $table->index(['company_id', 'starts_at']);
            $table->index(['company_id', 'customer_id', 'starts_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE appointments
                ADD CONSTRAINT appointments_status_check
                    CHECK (status IN ('scheduled', 'confirmed', 'checked_in', 'completed', 'cancelled', 'no_show')),
                ADD CONSTRAINT appointments_interval_check
                    CHECK (starts_at < ends_at AND ends_at <= occupied_until),
                ADD CONSTRAINT appointments_cancellation_check
                    CHECK ((status = 'cancelled') = (cancelled_at IS NOT NULL))
        SQL);

        // The final guard against double booking, including concurrent requests:
        // no two time-occupying appointments of the same professional may overlap.
        // '[)' makes back-to-back appointments (10:00-11:00 and 11:00-12:00) valid.
        DB::statement(<<<'SQL'
            ALTER TABLE appointments
                ADD CONSTRAINT appointments_no_overlap
                EXCLUDE USING gist (
                    professional_id WITH =,
                    tstzrange(starts_at, occupied_until, '[)') WITH &&
                ) WHERE (status IN ('scheduled', 'confirmed', 'checked_in', 'completed'))
        SQL);

        Schema::create('appointment_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('appointment_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->jsonb('changes')->nullable();
            $table->timestampTz('created_at');

            $table->index(['appointment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_events');
        Schema::dropIfExists('appointments');
    }
};
