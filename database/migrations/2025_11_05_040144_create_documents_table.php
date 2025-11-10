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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade'); // admin who uploaded
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // client
            $table->foreignId('plot_id')->nullable()->constrained('plots')->onDelete('set null');
            $table->foreignId('estate_id')->nullable()->constrained('estates')->onDelete('set null');
            $table->string('title');
            $table->string('document_type')->nullable();
            $table->string('file_url');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
