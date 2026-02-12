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
        Schema::create('carts', function (Blueprint $table) {
             $table->id();

            // Cart grouping ID (same for multiple items added together)
            $table->string('cart_id')->index();

            // Relationships
            $table->foreignId('estate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plot_id')->constrained()->cascadeOnDelete();

            // Price snapshot when added to cart
            $table->decimal('price', 12, 2);

            // User (nullable for guest)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Guest tracking
            $table->string('temporary_user_id')->nullable()->index();

            // Cart status
            $table->string('cart_status')->default('active');
            // Example values: active, checked_out, expired, removed

            $table->timestamps();

            // Optional performance indexes
            $table->index(['user_id', 'cart_status']);
            $table->index(['temporary_user_id', 'cart_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
