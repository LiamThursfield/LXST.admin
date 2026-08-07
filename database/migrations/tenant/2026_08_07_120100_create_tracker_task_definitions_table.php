<?php

use App\Enums\Tracker\TaskDefinitionStatus;
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
        Schema::create('tracker_task_definitions', function (Blueprint $table) {
            $table->id();
            // Personal data: owned by a user and removed with them.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Links a definition to the first version in its lineage. Null on the
            // lineage root (its own id is the lineage key); set on every successor
            // created when a semantic edit supersedes an earlier version.
            $table->foreignId('lineage_id')->nullable()->constrained('tracker_task_definitions')->nullOnDelete();

            // Optional category; if the category is removed the task becomes uncategorised.
            $table->foreignId('category_id')->nullable()->constrained('tracker_categories')->nullOnDelete();

            $table->string('title', 120);
            $table->text('description')->nullable();

            $table->string('period', 20);
            // Recurrence filters layered on the period (days of week, months, etc.).
            $table->json('schedule')->nullable();

            $table->string('measure', 20);
            $table->string('unit', 20);
            $table->string('comparator', 20);
            $table->string('aggregation', 20);

            // Targets are stored in the unit's canonical base (see TrackerUnit::toBase()).
            $table->decimal('target_min', 12, 4)->nullable();
            $table->decimal('target_max', 12, 4)->nullable();

            $table->boolean('stepped')->default(false);
            $table->boolean('counts_during_holiday')->default(false);

            $table->date('active_from');
            $table->date('active_to')->nullable();

            $table->string('status', 20)->default(TaskDefinitionStatus::Active->value);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracker_task_definitions');
    }
};
