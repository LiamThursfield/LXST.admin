<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Song lists: setlists, and each gig's requests, share one model (#146)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dj_song_lists', function (Blueprint $table) {
            $table->id();
            // A list can belong to one gig, or none; a gig can have several setlists
            $table->foreignId('gig_id')->nullable()->constrained('dj_gigs')->nullOnDelete();
            // SongListKind: setlist or requests
            $table->string('kind', 20)->index();
            $table->string('name');
            $table->text('description')->nullable();
            // SongListVisibility: private or public
            $table->string('visibility', 20)->default('private');
            $table->timestamps();
        });

        Schema::create('dj_song_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('song_list_id')->constrained('dj_song_lists')->cascadeOnDelete();
            // A song from the library, or a custom artist and title when it isn't in it
            $table->foreignId('canonical_track_id')->nullable()->constrained('dj_canonical_tracks')->nullOnDelete();
            $table->string('custom_artist')->nullable();
            $table->string('custom_title')->nullable();
            // SetlistSongType: must play, maybe or do not play; null on requests
            $table->string('type', 20)->nullable()->index();
            $table->unsignedInteger('position')->default(0);
            $table->text('note')->nullable();
            // Who added it: a logged-in user, a guest pass (#147), or an anonymous visitor (requests)
            $table->foreignId('added_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('guest_pass_id')->nullable()->index();
            $table->string('visitor_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['song_list_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dj_song_list_items');
        Schema::dropIfExists('dj_song_lists');
    }
};
