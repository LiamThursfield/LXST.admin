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
            // The tenant's own Stripe account, linked through Connect. Kept centrally so webhooks can find the tenant.
            $table->string('stripe_account_id', 64)->nullable()->unique();
            $table->boolean('stripe_charges_enabled')->default(false);
            $table->boolean('stripe_details_submitted')->default(false);
            // What the platform takes from each payment. Both null means nothing.
            $table->decimal('platform_fee_percent', 5, 2)->nullable();
            $table->unsignedInteger('platform_fee_fixed')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['stripe_account_id']);
            $table->dropColumn(['stripe_account_id', 'stripe_charges_enabled', 'stripe_details_submitted', 'platform_fee_percent', 'platform_fee_fixed']);
        });
    }
};
