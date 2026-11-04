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
        Schema::create('bookings_working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('bookings_resources')->cascadeOnDelete();
            // ISO day of the week: 1 is Monday, 7 is Sunday. A day has one row per period, so a split day is two rows.
            $table->unsignedTinyInteger('day_of_week');
            // Wall clock times as HH:MM. An end at or before the start runs into the next day.
            $table->string('starts_at', 5);
            $table->string('ends_at', 5);
            $table->timestamps();

            $table->index(['resource_id', 'day_of_week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings_working_hours');
    }
};
