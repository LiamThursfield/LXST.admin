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
        Schema::create('crm_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('crm_forms')->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('name', 120);
            // The key the answer is submitted and stored under
            $table->string('slug', 120);
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('order');
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->unique(['form_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_form_fields');
    }
};
