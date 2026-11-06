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
        Schema::table('bookings_services', function (Blueprint $table) {
            // Each in minor units when fixed, or a whole percentage of the price. Null means no fee.
            $table->string('no_show_fee_type', 20)->nullable()->after('deposit_amount');
            $table->unsignedInteger('no_show_fee_amount')->nullable()->after('no_show_fee_type');
            $table->string('late_cancellation_fee_type', 20)->nullable()->after('no_show_fee_amount');
            $table->unsignedInteger('late_cancellation_fee_amount')->nullable()->after('late_cancellation_fee_type');
        });

        Schema::table('bookings_payments', function (Blueprint $table) {
            // Towards the price, or a fee on top of it
            $table->string('purpose', 30)->default('booking')->after('booking_id');
            // Why a charge to a saved card didn't go through
            $table->string('failure_message', 255)->nullable()->after('note');
        });

        Schema::create('bookings_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('bookings_payments')->cascadeOnDelete();
            // In minor units
            $table->unsignedInteger('amount');
            $table->string('status', 20);
            $table->string('stripe_refund_id', 255)->nullable()->unique();
            // The platform's share given back, in minor units
            $table->unsignedInteger('application_fee_refunded')->default(0);
            $table->string('reason', 255)->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bookings_saved_cards', function (Blueprint $table) {
            $table->id();
            // Random, for the link a customer saves their card through
            $table->ulid('public_id')->unique();
            $table->foreignId('booking_id')->constrained('bookings_bookings')->cascadeOnDelete();
            $table->string('status', 20);
            $table->string('stripe_checkout_session_id', 255)->nullable()->unique();
            $table->text('checkout_url')->nullable();
            // On the business's Stripe account
            $table->string('stripe_customer_id', 255)->nullable();
            $table->string('stripe_payment_method_id', 255)->nullable();
            $table->string('brand', 30)->nullable();
            $table->string('last4', 4)->nullable();
            $table->timestamp('saved_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings_saved_cards');
        Schema::dropIfExists('bookings_refunds');

        Schema::table('bookings_payments', function (Blueprint $table) {
            $table->dropColumn(['purpose', 'failure_message']);
        });

        Schema::table('bookings_services', function (Blueprint $table) {
            $table->dropColumn(['no_show_fee_type', 'no_show_fee_amount', 'late_cancellation_fee_type', 'late_cancellation_fee_amount']);
        });
    }
};
