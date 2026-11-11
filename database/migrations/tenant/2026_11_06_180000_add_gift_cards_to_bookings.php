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
        Schema::create('bookings_gift_cards', function (Blueprint $table) {
            $table->id();
            // Random, for the link to the card a customer is sent
            $table->ulid('public_id')->unique();
            // What the customer gives to use it, e.g. ABCD-EFGH-JKLM
            $table->string('code', 20)->unique();
            $table->string('kind', 20);
            // For a voucher for a service
            $table->foreignId('service_id')->nullable()->constrained('bookings_services')->nullOnDelete();
            $table->string('status', 20);
            // What it's worth and what's left, in minor units. A voucher's amount is the service's price when issued.
            $table->unsignedInteger('amount');
            $table->unsignedInteger('balance');
            $table->string('currency', 3);
            // What was paid for it and how, in minor units. No method means it was given free.
            $table->unsignedInteger('price_paid')->default(0);
            $table->string('payment_method', 20)->nullable();
            $table->unsignedInteger('application_fee')->default(0);
            $table->string('stripe_checkout_session_id', 255)->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->string('stripe_payment_intent_id', 255)->nullable();
            $table->string('purchaser_name', 255)->nullable();
            $table->string('purchaser_email', 255)->nullable();
            $table->string('recipient_name', 255)->nullable();
            $table->string('recipient_email', 255)->nullable();
            $table->text('message')->nullable();
            // The last day it can be used. Null means it never expires.
            $table->date('expires_on')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('link_expires_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'expires_on']);
        });

        Schema::table('bookings_payments', function (Blueprint $table) {
            // The gift card a payment was taken from
            $table->foreignId('gift_card_id')->nullable()->after('booking_id')->constrained('bookings_gift_cards')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gift_card_id');
        });

        Schema::dropIfExists('bookings_gift_cards');
    }
};
