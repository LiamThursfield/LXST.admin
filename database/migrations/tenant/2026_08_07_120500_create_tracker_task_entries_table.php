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
        Schema::create('tracker_task_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_occurrence_id')->constrained('tracker_task_occurrences')->cascadeOnDelete();
            // Denormalised for ownership scoping.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Stored in the definition unit's canonical base (see TrackerUnit::toBase()).
            $table->decimal('value', 12, 4);
            $table->string('label', 120)->nullable();
            $table->timestamp('occurred_at');

            $table->timestamps();

            $table->index(['task_occurrence_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracker_task_entries');
    }
};
