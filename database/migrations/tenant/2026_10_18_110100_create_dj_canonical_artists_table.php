<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The real-world artist, so the same artist counts once across libraries and re-imports
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dj_canonical_artists', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
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
        Schema::dropIfExists('dj_canonical_artists');
    }
};
