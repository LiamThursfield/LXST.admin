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
        Schema::create('crm_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            // Unique across every form, including deleted ones, as websites refer to forms by slug
            $table->string('slug', 120)->unique();
            // Who is emailed when the form is submitted
            $table->json('email_recipients')->nullable();
            // Which marketing consent checkboxes the form asks for
            $table->boolean('marketing_email')->default(false);
            $table->boolean('marketing_sms')->default(false);
            $table->boolean('marketing_telephone')->default(false);
            $table->string('submit_button_text', 50)->nullable();
            $table->string('redirect_url', 150)->nullable();
            $table->string('success_message', 1024)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_forms');
    }
};
