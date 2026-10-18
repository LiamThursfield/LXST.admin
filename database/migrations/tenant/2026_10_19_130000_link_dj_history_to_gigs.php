<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Play history links to gigs (#118): a gig's played tracks are its linked sessions' plays.
 * History doesn't need a gig, so sessions can stay unlinked, e.g. practice at home.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dj_history_sessions', function (Blueprint $table) {
            // Manual sessions (plays added by hand to a gig) don't come from a library
            $table->unsignedBigInteger('library_id')->nullable()->change();
            $table->string('source_history_id')->nullable()->change();
            $table->foreignId('gig_id')->nullable()->after('library_id')->constrained('dj_gigs')->nullOnDelete();
            // HistorySource: import or manual
            $table->string('source', 20)->default('import')->after('gig_id');
            // Whether stats count it; tiny sessions (e.g. checking a track) usually shouldn't
            $table->boolean('include_in_stats')->default(true)->index()->after('source');
        });

        Schema::table('dj_history_plays', function (Blueprint $table) {
            // Plays added by hand name a song rather than a library track
            $table->string('source_track_id')->nullable()->change();
            $table->foreignId('canonical_track_id')->nullable()->after('library_track_id')->constrained('dj_canonical_tracks')->nullOnDelete();
        });

        // Sessions already imported count in stats only if they're more than a quick check
        DB::table('dj_history_sessions')
            ->whereRaw('(select count(*) from dj_history_plays where dj_history_plays.history_session_id = dj_history_sessions.id) < ?', [
                (int) config('dj.history.min_plays_for_stats', 5),
            ])
            ->update(['include_in_stats' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dj_history_plays', function (Blueprint $table) {
            $table->dropConstrainedForeignId('canonical_track_id');
        });

        Schema::table('dj_history_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gig_id');
            $table->dropIndex(['include_in_stats']);
            $table->dropColumn(['source', 'include_in_stats']);
        });
    }
};
