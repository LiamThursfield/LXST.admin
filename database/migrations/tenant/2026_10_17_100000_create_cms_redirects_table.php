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
        Schema::create('cms_redirects', function (Blueprint $table) {
            $table->id();
            // A normalised path on the site, e.g. "/old-page"
            $table->string('url_from')->unique();
            // A normalised path on the site, or a full http(s) URL
            $table->string('url_to');
            $table->boolean('is_enabled')->default(false)->index();
            $table->boolean('is_permanent')->default(false);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('expired_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_redirects');
    }
};
