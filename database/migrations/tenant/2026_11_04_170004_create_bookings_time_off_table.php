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
        Schema::create('bookings_time_off', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('bookings_resources')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('status', 20);
            // Whole days, both included
            $table->date('starts_on');
            $table->date('ends_on');
            $table->text('reason')->nullable();
            $table->foreignId('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['resource_id', 'status', 'starts_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings_time_off');
    }
};
