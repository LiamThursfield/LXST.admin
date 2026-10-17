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
        Schema::create('cms_page_views', function (Blueprint $table) {
            $table->id();
            // The page (or later, anything else with a URL) that was viewed (App\Models\Concerns\HasViews).
            // No foreign key, as it's polymorphic: deleting the item unlinks its views instead.
            $table->nullableMorphs('viewable');
            // The path viewed, kept so site-wide stats still work once the item is gone
            $table->string('url', 2048);
            $table->string('referrer', 2048)->nullable();
            $table->string('user_agent', 512)->nullable();
            // Truncated, never the full address (see VisitorPrivacy)
            $table->string('ip_address', 45)->nullable();
            // Counts unique visitors per day without identifying them (see VisitorPrivacy)
            $table->char('visitor_hash', 64)->nullable()->index();
            $table->string('session_id')->nullable();
            // utm_source, utm_medium, utm_campaign, utm_term and utm_content
            $table->json('source')->nullable();
            $table->json('additional_data')->nullable();
            // When the visitor viewed the page, rather than when the queued job saved it
            $table->timestamp('viewed_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_page_views');
    }
};
