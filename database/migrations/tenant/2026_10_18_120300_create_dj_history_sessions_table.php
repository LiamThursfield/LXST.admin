<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rekordbox's play history, kept as imported so it can be turned into gigs later (#118)
 * without another upload
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dj_history_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_id')->constrained('dj_libraries')->cascadeOnDelete();
            // The session's ID in the source, so re-imports update rather than duplicate
            $table->string('source_history_id');
            // e.g. "HISTORY 2025-12-05 (1)"
            $table->string('name');
            // The first and last plays (UTC)
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['library_id', 'source_history_id']);
        });

        Schema::create('dj_history_plays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('history_session_id')->constrained('dj_history_sessions')->cascadeOnDelete();
            $table->foreignId('library_track_id')->nullable()->constrained('dj_library_tracks')->nullOnDelete();
            // Kept in case the track arrives in a later import
            $table->string('source_track_id');
            // The play's place in the session
            $table->unsignedInteger('position');
            // When it was played (UTC)
            $table->timestamp('played_at')->nullable();
            $table->timestamps();

            $table->index(['history_session_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dj_history_plays');
        Schema::dropIfExists('dj_history_sessions');
    }
};
