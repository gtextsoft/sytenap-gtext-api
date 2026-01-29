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
        // Generic geometry column (Polygon or MultiPolygon)
        DB::statement("
            ALTER TABLE `estates`
            ADD COLUMN `geom` GEOMETRY NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE `estates`
            DROP COLUMN `geom`
        ");
    }
};
