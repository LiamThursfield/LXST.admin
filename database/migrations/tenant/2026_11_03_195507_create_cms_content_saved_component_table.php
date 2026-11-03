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
        Schema::create('cms_content_saved_component', function (Blueprint $table) {
            // Tied to the content row, so it goes when a page or template field is deleted
            $table->foreignId('content_id')->constrained('cms_content')->cascadeOnDelete();
            // Saved components in use can't be deleted
            $table->foreignId('saved_component_id')->constrained('cms_saved_components')->restrictOnDelete();

            $table->unique(['content_id', 'saved_component_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_content_saved_component');
    }
};
