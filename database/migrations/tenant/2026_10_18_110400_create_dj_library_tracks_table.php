<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A track as one library holds it, linked to the canonical track once matched
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dj_library_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_id')->constrained('dj_libraries')->cascadeOnDelete();
            $table->foreignId('library_artist_id')->nullable()->constrained('dj_library_artists')->nullOnDelete();
            $table->foreignId('canonical_track_id')->nullable()->constrained('dj_canonical_tracks')->nullOnDelete();
            // The track's ID in the source (e.g. Rekordbox), so re-imports update rather than duplicate
            $table->string('source_track_id');
            $table->string('title')->index();
            $table->decimal('bpm', 5, 2)->nullable();
            // In seconds
            $table->unsignedSmallInteger('duration')->nullable();
            // MusicalKey: the Camelot code, e.g. 8A
            $table->string('key', 3)->nullable();
            $table->timestamps();

            $table->unique(['library_id', 'source_track_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dj_library_tracks');
    }
};
