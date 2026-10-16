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
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            // A page template, and the layout the page sits in; neither can be deleted while used
            $table->foreignId('template_id')->constrained('cms_templates')->restrictOnDelete();
            $table->foreignId('layout_id')->constrained('cms_layouts')->restrictOnDelete();
            // Pages with children can't be deleted unless forced (see PageController::destroy)
            $table->foreignId('parent_id')->nullable()->constrained('cms_pages')->restrictOnDelete();
            $table->string('name', 120)->index();
            // Unique among siblings; blank for the home page. The full URL is unique in cms_urls.
            $table->string('slug', 120);
            $table->timestamps();

            $table->unique(['parent_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_pages');
    }
};
