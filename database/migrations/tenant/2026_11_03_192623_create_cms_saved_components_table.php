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
        Schema::create('cms_saved_components', function (Blueprint $table) {
            $table->id();
            // A component template; templates in use can't be deleted
            $table->foreignId('template_id')->constrained('cms_templates')->restrictOnDelete();
            $table->string('name', 120)->index();
            // How the JSON import refers to it across environments
            $table->string('slug', 120)->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_saved_components');
    }
};
