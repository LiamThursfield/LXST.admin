<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('crm_organisation_units', function (Blueprint $table) {
            // Only locations have these. Companies and departments leave them null.
            $table->string('location_type', 20)->nullable()->after('type');
            $table->string('timezone', 64)->nullable()->after('location_type');
            $table->json('service_areas')->nullable()->after('timezone');
        });

        // Every existing location is a place people visit until someone says otherwise
        DB::table('crm_organisation_units')->where('type', 'location')->update(['location_type' => 'premises']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_organisation_units', function (Blueprint $table) {
            $table->dropColumn(['location_type', 'timezone', 'service_areas']);
        });
    }
};
