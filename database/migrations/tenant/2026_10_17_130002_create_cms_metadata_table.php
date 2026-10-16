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
        Schema::create('cms_metadata', function (Blueprint $table) {
            $table->id();
            // The page (or later, anything else) these details are for (App\Models\Concerns\HasMetadata)
            $table->morphs('metadatable');
            $table->string('title')->nullable();
            $table->string('description')->nullable();
            $table->string('keywords')->nullable();
            $table->string('canonical')->nullable();
            $table->string('og_title')->nullable();
            $table->string('og_description')->nullable();
            $table->string('og_type')->nullable();
            $table->string('og_url')->nullable();
            // A File Manager image (legacy stored its URL); cleared if the file is deleted
            $table->foreignId('og_image_file_id')->nullable()->constrained('file_manager_files')->nullOnDelete();
            $table->timestamps();

            $table->unique(['metadatable_type', 'metadatable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_metadata');
    }
};
