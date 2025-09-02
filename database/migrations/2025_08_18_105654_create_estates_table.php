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
        Schema::create('estates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('town_or_city');
            $table->string('state');
            $table->string('cordinates')->nullable(); // typo fixed? maybe coordinates
            $table->string('zoning')->nullable();
            $table->string('size')->nullable();
            $table->string('direction')->nullable();
            $table->text('description')->nullable();
            $table->string('map_background_image')->nullable();
            $table->string('preview_display_image')->nullable();
            $table->boolean('has_cerificate_of_occupancy')->default(false);
            $table->json('amenities')->nullable(); // stores array of strings
            $table->tinyInteger('rating')->default(4)->checkBetween(1, 5); // Laravel 11+
            $table->enum('status', ['draft', 'publish', 'unpublish'])->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estates');
    }
};
