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
        Schema::create('plots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('estate_id');
            $table->string('plot_id')->unique(); // e.g. BERYL-001
            $table->string('coordinate')->nullable(); // "lat,lng"
            $table->enum('status', ['available','allocated','sold'])->default('available');
            $table->timestamps();

            $table->foreign('estate_id')->references('id')->on('estates')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plots');
    }
};
