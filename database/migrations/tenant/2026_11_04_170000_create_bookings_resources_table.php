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
        Schema::create('bookings_resources', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->foreignId('image_file_id')->nullable()->constrained('file_manager_files')->nullOnDelete();
            // Its home location. Null means it isn't tied to one.
            $table->foreignId('location_id')->nullable()->constrained('crm_organisation_units')->nullOnDelete();
            // People only: who signs in as this person, and their job title
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('job_title', 120)->nullable();
            // Spaces only: how many it holds, e.g. the seats at a table
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            // Soft deleted, so past bookings (#178) and reports keep who or what they were for
            $table->softDeletes();

            $table->index(['type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings_resources');
    }
};
