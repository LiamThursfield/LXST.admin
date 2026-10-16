<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cms_urls', function (Blueprint $table) {
            $table->id();
            // The page (or later, anything else) at this URL (App\Models\Concerns\HasUrl)
            $table->morphs('urlable');
            // The item's own part, e.g. "/team", and the whole path, e.g. "/about/team"
            $table->string('url_main');
            $table->string('url_full')->unique();
            $table->boolean('is_enabled')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('expired_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['urlable_type', 'urlable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_urls');
    }
};
