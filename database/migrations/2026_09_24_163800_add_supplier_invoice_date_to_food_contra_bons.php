<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_contra_bons', function (Blueprint $table) {
            if (! Schema::hasColumn('food_contra_bons', 'supplier_invoice_date')) {
                $table->date('supplier_invoice_date')->nullable()->after('supplier_invoice_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('food_contra_bons', function (Blueprint $table) {
            if (Schema::hasColumn('food_contra_bons', 'supplier_invoice_date')) {
                $table->dropColumn('supplier_invoice_date');
            }
        });
    }
};
