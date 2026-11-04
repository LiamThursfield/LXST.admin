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
        Schema::create('bookings_resource_service', function (Blueprint $table) {
            $table->foreignId('resource_id')->constrained('bookings_resources')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('bookings_services')->cascadeOnDelete();
            // Used instead of the service's own when set. The price is in minor units.
            $table->unsignedInteger('price')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();

            $table->primary(['resource_id', 'service_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings_resource_service');
    }
};
