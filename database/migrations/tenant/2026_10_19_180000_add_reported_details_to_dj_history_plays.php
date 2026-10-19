<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plays reported live by the CLI tool (#124) keep the artist and title they were reported with,
 * so plays that don't match a library track still show, and still match custom requests
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dj_history_plays', function (Blueprint $table) {
            $table->string('artist')->nullable()->after('source_track_id');
            $table->string('title')->nullable()->after('artist');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dj_history_plays', function (Blueprint $table) {
            $table->dropColumn(['artist', 'title']);
        });
    }
};
