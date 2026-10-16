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
        Schema::create('cms_templates', function (Blueprint $table) {
            $table->id();
            // page, layout, component or repeater (App\Enums\Cms\TemplateType)
            $table->string('type', 50)->index();
            $table->string('name', 120)->index();
            // Unique per type, so e.g. a page and a layout template can both be "default"
            $table->string('slug', 120);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['type', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_templates');
    }
};
