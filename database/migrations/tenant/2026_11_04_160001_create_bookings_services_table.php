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
        Schema::create('bookings_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('bookings_service_categories')->nullOnDelete();
            $table->foreignId('image_file_id')->nullable()->constrained('file_manager_files')->nullOnDelete();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->string('booking_type', 20);
            $table->string('price_type', 20);
            // In minor units, e.g. pence. Null when priced on quote. A hire's price is per hire unit.
            $table->unsignedInteger('price')->nullable();
            // The columns below apply only to some booking types: see BookingType::details()
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->unsignedSmallInteger('buffer_before_minutes')->default(0);
            $table->unsignedSmallInteger('buffer_after_minutes')->default(0);
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedSmallInteger('min_party_size')->nullable();
            $table->unsignedSmallInteger('max_party_size')->nullable();
            $table->string('hire_unit', 10)->nullable();
            $table->unsignedSmallInteger('min_hire_units')->nullable();
            $table->unsignedSmallInteger('max_hire_units')->nullable();
            $table->unsignedSmallInteger('travel_minutes')->nullable();
            $table->boolean('is_bookable_online')->default(true);
            $table->timestamps();
            // Soft deleted, so past bookings (#178) keep the service they were for
            $table->softDeletes();

            $table->index('booking_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings_services');
    }
};
