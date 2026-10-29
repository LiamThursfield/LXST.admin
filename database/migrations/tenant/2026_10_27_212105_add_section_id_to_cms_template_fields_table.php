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
        Schema::table('cms_template_fields', function (Blueprint $table) {
            // Fields with no section sit above the first section
            $table->foreignId('section_id')->nullable()->after('template_id')->constrained('cms_template_sections')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cms_template_fields', function (Blueprint $table) {
            $table->dropConstrainedForeignId('section_id');
        });
    }
};
