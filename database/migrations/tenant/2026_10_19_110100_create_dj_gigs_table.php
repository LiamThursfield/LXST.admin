<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gigs: the backbone for requests, setlists and stats
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dj_gigs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('venue')->nullable();
            // e.g. the town or address
            $table->string('location')->nullable();
            // Stored in UTC, shown in the gig's own timezone
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();
            $table->string('timezone', 64);
            $table->text('notes')->nullable();
            // Pinned as live: the current gig, whatever the time. At most one is pinned.
            $table->boolean('is_pinned')->default(false)->index();
            // GigRequestSettings: only the settings this gig overrides; the rest follow DJ settings
            $table->json('request_settings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dj_gigs');
    }
};
