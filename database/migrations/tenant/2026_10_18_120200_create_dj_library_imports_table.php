<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each import into a library: who started it, its progress, what it changed and any error
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dj_library_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_id')->constrained('dj_libraries')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // LibrarySource: the kind of file imported
            $table->string('source', 20);
            // ImportStatus
            $table->string('status', 20)->index();
            $table->string('original_filename');
            // Where the upload is kept until the import finishes; null once it's deleted
            $table->string('file_path')->nullable();
            // Tracks to import and tracks done, for progress
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('processed')->default(0);
            // What the import changed, e.g. tracks added, updated, unchanged and missing
            $table->json('counts')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dj_library_imports');
    }
};
