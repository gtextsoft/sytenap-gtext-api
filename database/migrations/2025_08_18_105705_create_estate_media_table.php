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
        Schema::create('estate_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estate_id')->constrained('estates')->onDelete('cascade');
            
            // multiple photos stored as JSON array of URLs
            $table->json('photos')->nullable();

            // multiple 3D model images (URLs)
            $table->json('third_dimension_model_images')->nullable();

            // single 3D model video
            $table->string('third_dimension_model_video')->nullable();

            // single virtual tour video
            $table->string('virtual_tour_video_url')->nullable();

            $table->enum('status', ['draft', 'publish', 'unpublish'])->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estate_media');
    }
};
