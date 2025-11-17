<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            // Remove unique constraint on user_id so one agent can have many referrals
            $table->dropUnique(['user_id']);

            // Add referred_user_id to track who the agent referred
            $table->unsignedBigInteger('referred_user_id')->nullable()->after('user_id');
            $table->foreign('referred_user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropForeign(['referred_user_id']);
            $table->dropColumn('referred_user_id');
            $table->unique('user_id'); // restore original state
        });
    }
};
