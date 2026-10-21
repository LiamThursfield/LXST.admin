<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A gig's live session (#124) is superseded once an imported session linked to the same gig covers
 * its time (#159), so the gig's plays aren't listed or counted twice. History still shows both.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dj_history_sessions', function (Blueprint $table) {
            $table->boolean('is_superseded')->default(false)->after('include_in_stats')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dj_history_sessions', function (Blueprint $table) {
            $table->dropIndex(['is_superseded']);
            $table->dropColumn('is_superseded');
        });
    }
};
