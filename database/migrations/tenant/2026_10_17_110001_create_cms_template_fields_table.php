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
        Schema::create('cms_template_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('cms_templates')->cascadeOnDelete();
            // App\Enums\Cms\TemplateFieldType
            $table->string('type', 50)->index();
            $table->string('name', 120);
            // Content is keyed by slug, so it must be unique within the template
            $table->string('slug', 120);
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('order');
            // App\Data\Cms\TemplateFieldSettings
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['template_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_template_fields');
    }
};
