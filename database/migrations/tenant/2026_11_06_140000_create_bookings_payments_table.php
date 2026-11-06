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
            $table->string('payment_requirement', 20)->default('none')->after('requires_approval');
            $table->string('deposit_type', 20)->nullable()->after('payment_requirement');
            // In minor units for a fixed deposit, or a whole percentage of the price
            $table->unsignedInteger('deposit_amount')->nullable()->after('deposit_type');
        });

        Schema::create('bookings_payments', function (Blueprint $table) {
            $table->id();
            // Random, for the link a customer comes back to after paying
            $table->ulid('public_id')->unique();
            $table->foreignId('booking_id')->constrained('bookings_bookings')->cascadeOnDelete();
            // In minor units
            $table->unsignedInteger('amount');
            $table->string('currency', 3);
            $table->string('status', 20);
            $table->string('method', 20);
            // What the platform took, for online payments
            $table->unsignedInteger('application_fee')->default(0);
            $table->string('stripe_checkout_session_id', 255)->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->string('stripe_payment_intent_id', 255)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings_payments');

        Schema::table('bookings_services', function (Blueprint $table) {
            $table->dropColumn(['payment_requirement', 'deposit_type', 'deposit_amount']);
        });
    }
};
