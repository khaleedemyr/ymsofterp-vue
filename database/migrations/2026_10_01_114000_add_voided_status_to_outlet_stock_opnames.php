<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `outlet_stock_opnames` MODIFY COLUMN `status` ENUM('DRAFT','SUBMITTED','APPROVED','REJECTED','COMPLETED','VOIDED') NOT NULL DEFAULT 'DRAFT'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `outlet_stock_opnames` MODIFY COLUMN `status` ENUM('DRAFT','SUBMITTED','APPROVED','REJECTED','COMPLETED') NOT NULL DEFAULT 'DRAFT'");
    }
};
