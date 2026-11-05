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
            // The kinds of resource a booking takes one of each of, e.g. a person and a space. Null uses the booking type's default.
            $table->json('required_resource_types')->nullable()->after('travel_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings_services', function (Blueprint $table) {
            $table->dropColumn('required_resource_types');
        });
    }
};
