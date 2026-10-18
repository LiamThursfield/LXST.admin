<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical matching (#116): the keys records are matched on, a log of every merge and split,
 * and suggested duplicates someone has said aren't the same
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dj_canonical_artists', function (Blueprint $table) {
            // The name, normalised, e.g. "JAY-Z ft. Beyoncé" → "jay z"
            $table->string('match_key')->nullable()->index()->after('name');
        });

        Schema::table('dj_canonical_tracks', function (Blueprint $table) {
            // The title without case, punctuation, featured artists or version tags, e.g. "humble"
            $table->string('match_key')->nullable()->after('title');

            $table->index(['canonical_artist_id', 'match_key']);
        });

        Schema::create('dj_canonical_merges', function (Blueprint $table) {
            $table->id();
            // CanonicalRecord: artist or track
            $table->string('record', 10);
            // MergeAction: merge or split
            $table->string('action', 10);
            // The record kept (merge) or created (split); null once it's been deleted
            $table->unsignedBigInteger('target_id')->nullable()->index();
            $table->string('target_name');
            // The record merged away (merge) or split from (split), named as it was
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_name');
            // Library records moved to the target
            $table->unsignedInteger('moved_count')->default(0);
            // Who did it; null when the re-match did it automatically
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('dj_match_dismissals', function (Blueprint $table) {
            $table->id();
            // CanonicalRecord: artist or track
            $table->string('record', 10);
            // The pair, smaller ID first
            $table->unsignedBigInteger('first_id');
            $table->unsignedBigInteger('second_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['record', 'first_id', 'second_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dj_match_dismissals');
        Schema::dropIfExists('dj_canonical_merges');

        Schema::table('dj_canonical_tracks', function (Blueprint $table) {
            $table->dropIndex(['canonical_artist_id', 'match_key']);
            $table->dropColumn('match_key');
        });

        Schema::table('dj_canonical_artists', function (Blueprint $table) {
            $table->dropIndex(['match_key']);
            $table->dropColumn('match_key');
        });
    }
};
