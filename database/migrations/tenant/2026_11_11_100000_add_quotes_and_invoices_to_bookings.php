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
        Schema::create('bookings_quotes', function (Blueprint $table) {
            $table->id();
            // Random, for the link a customer accepts it through
            $table->ulid('public_id')->unique();
            // In order, e.g. QUO-0001
            $table->string('number', 20)->unique();
            $table->foreignId('booking_id')->constrained('bookings_bookings')->cascadeOnDelete();
            $table->string('status', 20);
            // In minor units. The tax rate is the business's when it was written.
            $table->unsignedInteger('subtotal');
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->unsignedInteger('tax')->default(0);
            $table->unsignedInteger('total');
            $table->string('currency', 3);
            $table->text('notes')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bookings_invoices', function (Blueprint $table) {
            $table->id();
            // Random, for the link a customer sees and pays it through
            $table->ulid('public_id')->unique();
            // In order, e.g. INV-0001
            $table->string('number', 20)->unique();
            $table->foreignId('booking_id')->constrained('bookings_bookings')->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained('bookings_quotes')->nullOnDelete();
            $table->string('status', 20);
            // In minor units. The tax rate is the business's when it was issued.
            $table->unsignedInteger('subtotal');
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->unsignedInteger('tax')->default(0);
            $table->unsignedInteger('total');
            $table->string('currency', 3);
            $table->text('notes')->nullable();
            $table->date('issued_on');
            $table->date('due_on')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // The lines on a quote or an invoice
        Schema::create('bookings_line_items', function (Blueprint $table) {
            $table->id();
            $table->morphs('document');
            $table->string('description', 255);
            $table->decimal('quantity', 10, 2);
            // In minor units
            $table->integer('unit_price');
            $table->integer('total');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings_line_items');
        Schema::dropIfExists('bookings_invoices');
        Schema::dropIfExists('bookings_quotes');
    }
};
