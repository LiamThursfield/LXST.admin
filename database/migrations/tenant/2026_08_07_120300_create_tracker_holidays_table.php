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
        Schema::create('tracker_holidays', function (Blueprint $table) {
            $table->id();
            // Personal data: an away-period belongs to a user and is removed with them.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('label', 120)->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();

            $table->index(['user_id', 'starts_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracker_holidays');
    }
};
