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
        Schema::create('customer_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('estate_id')->constrained('estates')->onDelete('cascade');
            $table->json('plots')->nullable(); // store array of plot IDs
            $table->decimal('total_price', 15, 2)->default(0);
            $table->integer('installment_months')->default(1);

            // Payment status: outstanding | fully_paid | failed
            $table->enum('payment_status', ['outstanding', 'fully_paid', 'failed'])->default('outstanding');

            // Acquisition status: held | released | transferred
            $table->enum('acquisition_status', ['held', 'released', 'transferred'])->default('held');

            $table->timestamp('payment_verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_properties');
    }
};
