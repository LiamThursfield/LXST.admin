<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An artist as one library names it, linked to the canonical artist once matched
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dj_library_artists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_id')->constrained('dj_libraries')->cascadeOnDelete();
            $table->foreignId('canonical_artist_id')->nullable()->constrained('dj_canonical_artists')->nullOnDelete();
            // The artist's ID in the source (e.g. Rekordbox), so re-imports update rather than duplicate
            $table->string('source_artist_id');
            $table->string('name');
            $table->timestamps();

            $table->unique(['library_id', 'source_artist_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dj_library_artists');
    }
};
