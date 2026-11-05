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
        Schema::table('bookings_bookings', function (Blueprint $table) {
            // Booked in person for straight away, rather than ahead
            $table->boolean('is_walk_in')->default(false)->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings_bookings', function (Blueprint $table) {
            $table->dropColumn('is_walk_in');
        });
    }
};
