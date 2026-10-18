<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Song requests (#120) are songs on a gig's requests list (#146), with request-only columns, and
 * upvotes: a duplicate request becomes a vote, one per visitor
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dj_song_list_items', function (Blueprint $table) {
            // RequestStatus: pending, accepted, declined or played; null on setlists
            $table->string('status', 20)->nullable()->after('type')->index();
            $table->string('requester_name', 100)->nullable()->after('guest_name');
            $table->text('message')->nullable()->after('requester_name');
            // On one of the gig's setlists as do not play when requested, for the DJ to see (#122)
            $table->boolean('is_do_not_play')->default(false)->after('message');
            $table->timestamp('played_at')->nullable()->after('is_do_not_play');
        });

        Schema::create('dj_song_list_item_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('song_list_item_id')->constrained('dj_song_list_items')->cascadeOnDelete();
            // Who voted: a visitor (hashed IP and user agent), and their guest pass if one was needed
            $table->string('visitor_hash', 64)->nullable();
            $table->foreignId('guest_pass_id')->nullable()->constrained('dj_guest_passes')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['song_list_item_id', 'visitor_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dj_song_list_item_votes');

        Schema::table('dj_song_list_items', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'requester_name', 'message', 'is_do_not_play', 'played_at']);
        });
    }
};
