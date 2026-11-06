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
        Schema::table('bookings_message_templates', function (Blueprint $table) {
            // Hours before the start for a reminder, or after the end for a follow-up. Null uses the default.
            $table->unsignedSmallInteger('offset_hours')->nullable()->after('is_enabled');
        });

        Schema::table('bookings_message_logs', function (Blueprint $table) {
            // What was written, for a one-off message that has no template
            $table->text('body')->nullable()->after('recipient');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings_message_logs', function (Blueprint $table) {
            $table->dropColumn('body');
        });

        Schema::table('bookings_message_templates', function (Blueprint $table) {
            $table->dropColumn('offset_hours');
        });
    }
};
