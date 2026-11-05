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
        Schema::create('bookings_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('bookings_services')->restrictOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('bookings_sessions')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('crm_organisation_units')->nullOnDelete();
            // Set when the tenant has the CRM. The customer's details are kept here too, as it's optional.
            $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->string('customer_name', 255);
            $table->string('customer_email', 255)->nullable();
            $table->string('customer_phone', 50)->nullable();
            $table->string('status', 20);
            // In UTC. Empty for an enquiry until a time is set.
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            // The time its resources are tied up, including buffers and travel
            $table->dateTime('blocked_from')->nullable();
            $table->dateTime('blocked_until')->nullable();
            // The location's timezone when booked, for showing the time as the customer was told it
            $table->string('timezone', 64);
            // The party size, places taken or units hired
            $table->unsignedInteger('quantity')->default(1);
            // Copied from the service, so later changes to it don't change past bookings. The price is in minor units.
            $table->string('service_name', 120);
            $table->string('price_type', 20);
            $table->unsignedInteger('price')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'starts_at']);
            $table->index(['blocked_from', 'blocked_until']);
        });

        Schema::create('bookings_booking_resource', function (Blueprint $table) {
            $table->foreignId('booking_id')->constrained('bookings_bookings')->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained('bookings_resources')->restrictOnDelete();

            $table->primary(['booking_id', 'resource_id']);
        });

        // Copies of the variations and add-ons chosen, as those can be changed or removed later
        Schema::create('bookings_booking_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings_bookings')->cascadeOnDelete();
            $table->foreignId('service_option_id')->nullable()->constrained('bookings_service_options')->nullOnDelete();
            $table->string('type', 20);
            $table->string('name', 120);
            $table->integer('price_adjustment')->default(0);
            $table->smallInteger('duration_adjustment')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings_booking_options');
        Schema::dropIfExists('bookings_booking_resource');
        Schema::dropIfExists('bookings_bookings');
    }
};
