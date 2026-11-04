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
        Schema::create('crm_opening_hour_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_unit_id')->constrained('crm_organisation_units')->cascadeOnDelete();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            // The hours for these dates, in place of the weekly hours. Null means closed.
            $table->json('periods')->nullable();
            $table->timestamps();

            $table->index(['organisation_unit_id', 'starts_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_opening_hour_exceptions');
    }
};
