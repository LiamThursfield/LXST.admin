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
        Schema::table('bookings_resources', function (Blueprint $table) {
            // Spaces in the same group at the same location can be joined for a party no one space fits, e.g. tables
            $table->string('combine_group', 60)->nullable()->after('capacity');
        });

        Schema::table('bookings_bookings', function (Blueprint $table) {
            // Shared by the bookings made together as a repeating series, e.g. weekly lessons
            $table->uuid('series_id')->nullable()->after('session_id');
            $table->index('series_id');
        });

        Schema::create('bookings_waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('bookings_services')->cascadeOnDelete();
            // A full timetable entry, or else any time on a date
            $table->foreignId('session_id')->nullable()->constrained('bookings_sessions')->cascadeOnDelete();
            $table->date('wanted_on')->nullable();
            $table->foreignId('location_id')->nullable()->constrained('crm_organisation_units')->nullOnDelete();
            $table->string('customer_name', 255);
            $table->string('customer_email', 255)->nullable();
            $table->string('customer_phone', 50)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->text('notes')->nullable();
            $table->string('status', 20);
            // The booking made for them, once there was room
            $table->foreignId('booking_id')->nullable()->constrained('bookings_bookings')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings_waitlist_entries');

        Schema::table('bookings_bookings', function (Blueprint $table) {
            $table->dropIndex(['series_id']);
            $table->dropColumn('series_id');
        });

        Schema::table('bookings_resources', function (Blueprint $table) {
            $table->dropColumn('combine_group');
        });
    }
};
