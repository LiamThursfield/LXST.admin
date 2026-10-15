<?php

use App\Enums\Tracker\TaskOccurrenceStatus;
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
        Schema::create('tracker_task_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_definition_id')->constrained('tracker_task_definitions')->cascadeOnDelete();
            // Denormalised for fast "my dashboard" queries and ownership scoping.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Canonical window identifier: 2026-08-07 | 2026-W32 | 2026-08 | 2026 | lifetime | oneoff.
            $table->string('period_key', 20);
            $table->date('period_start');
            // Null only for an open-ended lifetime goal.
            $table->date('period_end')->nullable();

            // Cached aggregate of the occurrence's entries (populated during evaluation).
            $table->decimal('computed_value', 12, 4)->nullable();
            $table->string('status', 20)->default(TaskOccurrenceStatus::Pending->value);
            $table->boolean('is_holiday')->default(false);
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            // Makes generation idempotent.
            $table->unique(['task_definition_id', 'period_key']);
            $table->index(['user_id', 'status']);
            $table->index('period_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracker_task_occurrences');
    }
};
