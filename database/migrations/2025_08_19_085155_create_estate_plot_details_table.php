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
        Schema::create('estate_plot_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('estate_id');
            $table->integer('available_plot')->default(0);
            $table->decimal('available_acre', 10, 2)->default(0.00);
            $table->decimal('price_per_plot', 15, 2);
            $table->decimal('percentage_increase', 5, 2)->default(0.00)->comment('Percentage increase value');
            $table->json('installment_plan')->nullable()->comment('Array of installment options: 12 months, 6 months, 3 months etc');
            $table->decimal('promotion_price', 15, 2)->nullable();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('estate_id')->references('id')->on('estates')->onDelete('cascade');
            
            // Indexes for better query performance
            $table->index('estate_id');
            $table->index('available_plot');
            $table->index('price_per_plot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estate_plot_details');
    }
};
