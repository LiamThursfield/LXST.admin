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
        Schema::table('bookings_services', function (Blueprint $table) {
            // Each is off when null
            $table->unsignedInteger('min_notice_minutes')->nullable()->after('required_resource_types');
            $table->unsignedSmallInteger('max_advance_days')->nullable()->after('min_notice_minutes');
            $table->unsignedSmallInteger('cancellation_hours')->nullable()->after('max_advance_days');
            // Upcoming bookings one customer can hold at once, matched by email
            $table->unsignedSmallInteger('max_per_customer')->nullable()->after('cancellation_hours');
            // Customers' bookings wait for staff to confirm them. Staff bookings never do.
            $table->boolean('requires_approval')->default(false)->after('max_per_customer');
        });

        Schema::table('bookings_bookings', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('notes');
            // Cancelled inside the service's cancellation window, e.g. for a fee (#180)
            $table->boolean('is_late_cancellation')->default(false)->after('cancelled_at');
            // Made by staff who chose to ignore the service's rules
            $table->boolean('rules_ignored')->default(false)->after('is_late_cancellation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings_bookings', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'is_late_cancellation', 'rules_ignored']);
        });

        Schema::table('bookings_services', function (Blueprint $table) {
            $table->dropColumn(['min_notice_minutes', 'max_advance_days', 'cancellation_hours', 'max_per_customer', 'requires_approval']);
        });
    }
};
