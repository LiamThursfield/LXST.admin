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
        Schema::create('crm_organisation_units', function (Blueprint $table) {
            $table->id();
            // The parent chain is the only hierarchy stored: a unit's company and location are derived from it.
            // Units with children cannot be deleted, so the foreign key restricts deletes.
            $table->foreignId('parent_id')->nullable()->constrained('crm_organisation_units')->restrictOnDelete();
            $table->string('type', 20);
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email');
            $table->string('telephone', 20)->nullable();
            $table->string('mobile_phone', 20)->nullable();
            $table->json('socials')->nullable();
            $table->json('notification_emails')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_organisation_units');
    }
};
