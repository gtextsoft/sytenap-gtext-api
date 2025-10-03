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
        Schema::create('plot_purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('estate_id');
            $table->unsignedBigInteger('user_id')->nullable(); 
            $table->json('plots'); // store array of purchased plots
            $table->decimal('total_price', 15, 2);
            $table->integer('installment_months')->default(1);
            $table->decimal('monthly_payment', 15, 2);
            $table->json('payment_schedule'); // store breakdown per month
            $table->timestamps();

            $table->foreign('estate_id')->references('id')->on('estates')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plot_purchases');
    }
};
