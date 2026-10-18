<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The track details Rekordbox gives us (see the spike, #111), and a flag for tracks a full import no longer has
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dj_library_tracks', function (Blueprint $table) {
            $table->string('album')->nullable()->after('title');
            $table->string('genre')->nullable()->index()->after('album');
            $table->string('label')->nullable()->after('genre');
            $table->unsignedSmallInteger('year')->nullable()->after('label');
            $table->text('comments')->nullable()->after('key');
            // Rekordbox's own count, not plays recorded here
            $table->unsignedInteger('play_count')->default(0)->after('comments');
            // 0 to 5 stars
            $table->unsignedTinyInteger('rating')->nullable()->after('play_count');
            $table->date('date_added')->nullable()->after('rating');
            // A file path, or a streaming ID such as tidal:tracks:356883832
            $table->text('location')->nullable()->after('date_added');
            // TrackService and its ID, parsed from the location, for exact matching and streaming search later
            $table->string('service', 20)->nullable()->after('location');
            $table->string('service_track_id')->nullable()->after('service');
            // Set when a full import doesn't include the track, cleared if it comes back; never deleted, as history points at it
            $table->timestamp('missing_since')->nullable()->index()->after('service_track_id');

            $table->index(['service', 'service_track_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dj_library_tracks', function (Blueprint $table) {
            $table->dropIndex(['service', 'service_track_id']);
            $table->dropIndex(['genre']);
            $table->dropIndex(['missing_since']);
            $table->dropColumn([
                'album', 'genre', 'label', 'year', 'comments', 'play_count', 'rating',
                'date_added', 'location', 'service', 'service_track_id', 'missing_since',
            ]);
        });
    }
};
