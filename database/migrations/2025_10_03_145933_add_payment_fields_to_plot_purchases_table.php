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
        Schema::table('plot_purchases', function (Blueprint $table) {
            $table->string('payment_reference')->unique()->after('id');
            $table->string('payment_link')->nullable()->after('payment_reference');
            $table->string('payment_status')->default('pending')->after('payment_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plot_purchases', function (Blueprint $table) {
            //
        });
    }
};
