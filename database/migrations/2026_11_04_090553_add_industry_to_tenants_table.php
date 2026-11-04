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
            // The tenant's primary industry, a key of config('industries.profiles'). Its words and
            // defaults are looked up from that, so more industries could sit alongside it later
            $table->string('industry')->default('other')->after('modules');
            // Only what the tenant has changed from its industry's defaults: terms, booking_types
            // and customer_fields
            $table->json('industry_overrides')->nullable()->after('industry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['industry', 'industry_overrides']);
        });
    }
};
