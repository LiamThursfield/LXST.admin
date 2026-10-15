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
        Schema::create('file_manager_files', function (Blueprint $table) {
            $table->id();
            // Files at the top level have no folder
            $table->foreignId('folder_id')->nullable()->constrained('file_manager_folders')->restrictOnDelete();
            // The file name including its extension, e.g. "team-photo.jpg"
            $table->string('name');
            // The file's full path on the file manager disk, e.g. "images/team/team-photo.jpg"
            $table->string('path', 500)->unique();
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            // Description of an image for screen readers, used wherever the file is shown
            $table->string('alt_text', 500)->nullable();
            $table->timestamps();

            $table->index('mime_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_manager_files');
    }
};
