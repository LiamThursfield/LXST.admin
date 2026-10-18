<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requesters opt in to their name showing on the public request list (#121)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dj_song_list_items', function (Blueprint $table) {
            $table->boolean('show_requester_name')->default(false)->after('requester_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dj_song_list_items', function (Blueprint $table) {
            $table->dropColumn('show_requester_name');
        });
    }
};
