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
        Schema::table('crm_forms', function (Blueprint $table) {
            // Only takes effect once the tenant has set up captcha (#74)
            $table->boolean('captcha_enabled')->default(false)->after('marketing_telephone');
        });

        Schema::table('crm_form_submissions', function (Blueprint $table) {
            // Why it was marked as spam, see App\Enums\Crm\SpamReason
            $table->string('spam_reason', 30)->nullable()->after('is_spam');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_form_submissions', function (Blueprint $table) {
            $table->dropColumn('spam_reason');
        });

        Schema::table('crm_forms', function (Blueprint $table) {
            $table->dropColumn('captcha_enabled');
        });
    }
};
