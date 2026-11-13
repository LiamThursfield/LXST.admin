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
        // A menu, price list or product list, and when it applies
        Schema::create('catalogue_catalogues', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_published')->default(false);
            // ISO days of the week (1 is Monday). Null is every day.
            $table->json('available_days')->nullable();
            // A time range on those days. An end before the start runs past midnight.
            $table->time('available_from')->nullable();
            $table->time('available_until')->nullable();
            // A date range, e.g. a seasonal menu
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('catalogue_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogue_id')->constrained('catalogue_catalogues')->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('catalogue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('catalogue_sections')->cascadeOnDelete();
            // An item can show a bookable service, whose price it then uses
            $table->foreignId('service_id')->nullable()->constrained('bookings_services')->nullOnDelete();
            $table->foreignId('image_file_id')->nullable()->constrained('file_manager_files')->nullOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            // In minor units, e.g. pence. Null when it has no price of its own.
            $table->unsignedInteger('price')->nullable();
            // Off for "sold out for now", whatever the stock says
            $table->boolean('is_available')->default(true);
            // Stock is counted on the item, or on each variation when it has them
            $table->boolean('tracks_stock')->default(false);
            $table->integer('stock')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        // Sizes and the like, each with its own price, e.g. small and large, or a glass and a bottle
        Schema::create('catalogue_item_variations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('catalogue_items')->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedInteger('price');
            $table->integer('stock')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('catalogue_tags', function (Blueprint $table) {
            $table->id();
            // The built-in tag it was added from, e.g. "milk" or "vegan". Null for the tenant's own.
            $table->string('preset', 40)->nullable()->unique();
            $table->string('kind', 20);
            $table->string('name', 60);
            $table->string('icon', 60)->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('catalogue_item_tag', function (Blueprint $table) {
            $table->foreignId('item_id')->constrained('catalogue_items')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('catalogue_tags')->cascadeOnDelete();
            $table->primary(['item_id', 'tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalogue_item_tag');
        Schema::dropIfExists('catalogue_tags');
        Schema::dropIfExists('catalogue_item_variations');
        Schema::dropIfExists('catalogue_items');
        Schema::dropIfExists('catalogue_sections');
        Schema::dropIfExists('catalogue_catalogues');
    }
};
