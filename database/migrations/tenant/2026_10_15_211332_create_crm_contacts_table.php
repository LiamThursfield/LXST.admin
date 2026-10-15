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
        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_spam')->default(false);
            // Names are optional at the database level, as contacts created from form submissions may only have an email
            $table->string('title')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('telephone', 20)->nullable();
            // Unique across every row, including soft-deleted ones
            $table->string('email')->unique();
            $table->boolean('marketing_email')->default(false);
            $table->boolean('marketing_sms')->default(false);
            $table->boolean('marketing_telephone')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_spam');
            $table->index('first_name');
            $table->index('last_name');
            $table->index('telephone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_contacts');
    }
};
