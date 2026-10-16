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
        Schema::create('cms_content', function (Blueprint $table) {
            $table->id();
            // The layout or page the value belongs to (App\Models\Concerns\HasContent)
            $table->morphs('contentable');
            // Removing a field from its template removes its content
            $table->foreignId('template_field_id')->constrained('cms_template_fields')->cascadeOnDelete();
            // The field's type when the value was saved (App\Enums\Cms\TemplateFieldType)
            $table->string('template_field_type', 50);
            $table->json('data')->nullable();
            $table->timestamps();

            $table->unique(['contentable_type', 'contentable_id', 'template_field_id'], 'cms_content_contentable_field_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_content');
    }
};
