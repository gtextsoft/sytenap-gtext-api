<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->onDelete('cascade');
            $table->unsignedBigInteger('commission_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('description')->nullable();
            $table->timestamp('withdrawal_date')->useCurrent();
            $table->timestamps();
            
            $table->foreign('commission_id')->references('id')->on('agent_commissions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_withdrawals');
    }
};
