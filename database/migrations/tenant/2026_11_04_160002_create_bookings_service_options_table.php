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
        Schema::create('bookings_service_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('bookings_services')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('name', 120);
            // Added to the service's price and duration, or taken off when negative. The price is in minor units.
            $table->integer('price_adjustment')->default(0);
            $table->smallInteger('duration_adjustment')->default(0);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['service_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings_service_options');
    }
};
