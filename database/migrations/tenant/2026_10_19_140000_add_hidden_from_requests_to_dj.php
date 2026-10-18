<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Songs, artists and whole libraries can be hidden from song request search (#142), e.g. DJ
 * tools, intros and sound effects. Hiding an artist hides their songs, and hiding a library
 * hides the songs that are only in it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['dj_canonical_tracks', 'dj_canonical_artists', 'dj_libraries'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->timestamp('hidden_from_requests_at')->nullable()->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['dj_canonical_tracks', 'dj_canonical_artists', 'dj_libraries'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['hidden_from_requests_at']);
                $table->dropColumn('hidden_from_requests_at');
            });
        }
    }
};
