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
        Schema::create('crm_addresses', function (Blueprint $table) {
            $table->id();
            // Every address belongs to something (an organisation unit, a contact...)
            $table->morphs('addressable');
            $table->string('postcode', 10);
            $table->string('line_1');
            $table->string('line_2')->nullable();
            $table->string('line_3')->nullable();
            $table->string('town_city')->nullable();
            $table->string('county')->nullable();
            $table->string('country')->nullable();
            $table->timestamps();

            $table->index('postcode');
            $table->unique(['addressable_type', 'addressable_id', 'postcode', 'line_1'], 'crm_addresses_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_addresses');
    }
};
