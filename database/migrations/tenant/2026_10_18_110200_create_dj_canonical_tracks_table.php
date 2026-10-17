<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The real-world song, so the same song counts once across libraries and re-imports
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dj_canonical_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canonical_artist_id')->nullable()->constrained('dj_canonical_artists')->nullOnDelete();
            $table->string('title')->index();
            $table->decimal('bpm', 5, 2)->nullable();
            // In seconds
            $table->unsignedSmallInteger('duration')->nullable();
            // MusicalKey: the Camelot code, e.g. 8A
            $table->string('key', 3)->nullable();
            // When someone confirmed the details are right, so matching won't change them
            $table->timestamp('verified_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dj_canonical_tracks');
    }
};
