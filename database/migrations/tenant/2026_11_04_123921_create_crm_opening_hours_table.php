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
        Schema::create('crm_opening_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_unit_id')->constrained('crm_organisation_units')->cascadeOnDelete();
            // ISO day of the week: 1 is Monday, 7 is Sunday. A day has one row per period, so split shifts are two rows.
            $table->unsignedTinyInteger('day_of_week');
            // Wall clock times as HH:MM in the location's timezone. A close at or before the open runs into the next day.
            $table->string('opens_at', 5);
            $table->string('closes_at', 5);
            $table->string('label', 100)->nullable();
            $table->timestamps();

            $table->index(['organisation_unit_id', 'day_of_week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_opening_hours');
    }
};
