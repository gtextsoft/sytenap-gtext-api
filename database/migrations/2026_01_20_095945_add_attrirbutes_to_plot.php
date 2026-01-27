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
            ALTER TABLE `plots`
            ADD COLUMN `geom` GEOMETRY NULL,
            ADD COLUMN `Price` INT NULL,
            ADD COLUMN `Area` INT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE `plots`
            DROP COLUMN `geom`,
            DROP COLUMN `Price`,
            DROP COLUMN `Area`
        ");
    }
};
