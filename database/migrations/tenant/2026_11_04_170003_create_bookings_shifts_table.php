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
        // A date with shifts on the rota uses them instead of that weekday's working hours
        Schema::create('bookings_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('bookings_resources')->cascadeOnDelete();
            $table->date('works_on');
            $table->string('starts_at', 5);
            $table->string('ends_at', 5);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['resource_id', 'works_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings_shifts');
    }
};
