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
        Schema::create('cms_template_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('cms_templates')->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            // Whether the section starts collapsed in the content editor
            $table->boolean('is_collapsed')->default(false);
            $table->unsignedInteger('order');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_template_sections');
    }
};
