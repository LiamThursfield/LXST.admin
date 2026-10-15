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
        Schema::create('crm_form_submissions', function (Blueprint $table) {
            $table->id();
            // Forms are soft-deleted, so submissions are kept with them
            $table->foreignId('form_id')->constrained('crm_forms')->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->boolean('is_spam')->default(false);
            // The answers, each stored with the field's label and type at the time of submitting,
            // plus the answers to the marketing consent questions the form asked
            $table->json('data');
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();

            $table->index('is_spam');
            $table->index('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_form_submissions');
    }
};
