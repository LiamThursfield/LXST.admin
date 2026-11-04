<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A service with no rows here is offered at every location
        Schema::create('bookings_service_location', function (Blueprint $table) {
            $table->foreignId('service_id')->constrained('bookings_services')->cascadeOnDelete();
            $table->foreignId('organisation_unit_id')->constrained('crm_organisation_units')->cascadeOnDelete();

            $table->primary(['service_id', 'organisation_unit_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings_service_location');
    }
};
