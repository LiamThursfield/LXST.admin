<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guest passes (#147): labelled, simple passwords that let people without a login, e.g. a bride
 * or a planner, view and add to a setlist, or that a gig's requests can need
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dj_guest_passes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('song_list_id')->constrained('dj_song_lists')->cascadeOnDelete();
            $table->string('label');
            // Encrypted rather than hashed, so the DJ can look it up and send it again
            $table->text('password');
            // Whether it can add songs, e.g. off once the playlist is final
            $table->boolean('can_add')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::table('dj_song_list_items', function (Blueprint $table) {
            // The pass that added it; the column came with song lists (#146)
            $table->foreign('guest_pass_id')->references('id')->on('dj_guest_passes')->nullOnDelete();
            // A name the guest gave with a shared pass, e.g. "Aunt Sue" on a "Family" pass
            $table->string('guest_name', 100)->nullable()->after('guest_pass_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dj_song_list_items', function (Blueprint $table) {
            $table->dropForeign(['guest_pass_id']);
            $table->dropColumn('guest_name');
        });

        Schema::dropIfExists('dj_guest_passes');
    }
};
