<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_service', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('professional_id');
            $table->uuid('service_id');

            $table->primary(['professional_id', 'service_id']);
            $table->index('service_id');

            // Composite keys: the database itself refuses to link records of different companies.
            $table->foreign(['company_id', 'professional_id'])
                ->references(['company_id', 'id'])->on('professionals')->cascadeOnDelete();
            $table->foreign(['company_id', 'service_id'])
                ->references(['company_id', 'id'])->on('services')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_service');
    }
};
