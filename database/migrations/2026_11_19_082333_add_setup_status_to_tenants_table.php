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
        Schema::table('tenants', function (Blueprint $table) {
            // Tenants made from the central admin are set up in the background (ProvisionTenant).
            // Existing tenants, and those made by commands, are ready straight away.
            $table->string('setup_status')->default('ready')->after('disabled_at');
            $table->text('setup_error')->nullable()->after('setup_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['setup_status', 'setup_error']);
        });
    }
};
