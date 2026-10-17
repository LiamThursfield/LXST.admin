<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rekordbox's My Tags, per library, and the tracks they're on
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dj_library_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_id')->constrained('dj_libraries')->cascadeOnDelete();
            // The tag's ID in the source, so re-imports update rather than duplicate
            $table->string('source_tag_id');
            $table->string('name');
            // The My Tag category it sits under, e.g. "Genre"
            $table->string('category')->nullable();
            $table->timestamps();

            $table->unique(['library_id', 'source_tag_id']);
        });

        Schema::create('dj_library_tag_track', function (Blueprint $table) {
            $table->foreignId('library_tag_id')->constrained('dj_library_tags')->cascadeOnDelete();
            $table->foreignId('library_track_id')->constrained('dj_library_tracks')->cascadeOnDelete();

            $table->primary(['library_tag_id', 'library_track_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dj_library_tag_track');
        Schema::dropIfExists('dj_library_tags');
    }
};
