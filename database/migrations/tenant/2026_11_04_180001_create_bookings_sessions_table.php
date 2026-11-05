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
        // The scheduled times of a class or event, which bookings take places on
        Schema::create('bookings_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('bookings_services')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('crm_organisation_units')->nullOnDelete();
            // In UTC
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            // Null uses the service's capacity
            $table->unsignedInteger('capacity')->nullable();
            $table->timestamps();

            $table->index(['service_id', 'starts_at']);
        });

        // Who and what the session ties up
        Schema::create('bookings_session_resource', function (Blueprint $table) {
            $table->foreignId('session_id')->constrained('bookings_sessions')->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained('bookings_resources')->cascadeOnDelete();

            $table->primary(['session_id', 'resource_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings_session_resource');
        Schema::dropIfExists('bookings_sessions');
    }
};
