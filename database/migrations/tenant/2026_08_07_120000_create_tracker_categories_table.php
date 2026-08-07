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
        Schema::create('tracker_categories', function (Blueprint $table) {
            $table->id();
            // Nullable owner: null = system/global category, set = user-owned.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 80);
            $table->string('colour', 30)->nullable();
            $table->string('icon', 60)->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            // Backstop for per-owner duplicates; the authoritative scoped uniqueness
            // (public names unique globally, private names unique per user) is enforced
            // in the form requests, since it cannot be expressed as a single index.
            $table->unique(['user_id', 'name', 'is_public']);
            $table->index('is_public');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracker_categories');
    }
};
