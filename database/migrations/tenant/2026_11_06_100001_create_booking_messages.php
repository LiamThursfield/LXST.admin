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
        // A tenant's own wording for each message. Without a row, the default wording is used.
        Schema::create('bookings_message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event', 30)->unique();
            $table->boolean('is_enabled')->default(true);
            $table->string('email_subject', 255)->nullable();
            $table->text('email_body')->nullable();
            $table->text('sms_body')->nullable();
            // A WhatsApp template approved by Meta, from the tenant's Twilio account
            $table->string('whatsapp_template_sid', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('bookings_message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained('bookings_bookings')->nullOnDelete();
            $table->string('event', 30);
            $table->string('channel', 20);
            $table->string('recipient', 255)->nullable();
            $table->string('status', 20);
            // Why it failed, was skipped, or fell back to another channel
            $table->text('note')->nullable();
            $table->string('provider_message_id', 100)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::table('bookings_bookings', function (Blueprint $table) {
            // How the customer is told about this booking
            $table->string('channel', 20)->default('email')->after('customer_phone');
        });

        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->string('preferred_channel', 20)->nullable()->after('telephone');
            // Opting out stops booking messages by that channel, which then fall back to email
            $table->boolean('sms_opted_out')->default(false)->after('preferred_channel');
            $table->boolean('whatsapp_opted_out')->default(false)->after('sms_opted_out');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->dropColumn(['preferred_channel', 'sms_opted_out', 'whatsapp_opted_out']);
        });

        Schema::table('bookings_bookings', function (Blueprint $table) {
            $table->dropColumn('channel');
        });

        Schema::dropIfExists('bookings_message_logs');
        Schema::dropIfExists('bookings_message_templates');
    }
};
