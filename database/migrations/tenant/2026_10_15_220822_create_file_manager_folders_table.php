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
        Schema::create('file_manager_folders', function (Blueprint $table) {
            $table->id();
            // Top-level folders have no parent. Folders with contents can't be deleted.
            $table->foreignId('parent_id')->nullable()->constrained('file_manager_folders')->restrictOnDelete();
            $table->string('name', 100);
            // The folder's full path on the file manager disk, e.g. "images/team"
            $table->string('path', 500)->unique();
            $table->timestamps();

            $table->unique(['parent_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_manager_folders');
    }
};
