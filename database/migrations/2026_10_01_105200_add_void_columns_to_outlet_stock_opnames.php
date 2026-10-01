<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlet_stock_opnames', function (Blueprint $table) {
            if (! Schema::hasColumn('outlet_stock_opnames', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('outlet_stock_opnames', 'voided_by')) {
                $table->unsignedBigInteger('voided_by')->nullable()->after('voided_at');
            }
            if (! Schema::hasColumn('outlet_stock_opnames', 'void_reason')) {
                $table->text('void_reason')->nullable()->after('voided_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('outlet_stock_opnames', function (Blueprint $table) {
            if (Schema::hasColumn('outlet_stock_opnames', 'void_reason')) {
                $table->dropColumn('void_reason');
            }
            if (Schema::hasColumn('outlet_stock_opnames', 'voided_by')) {
                $table->dropColumn('voided_by');
            }
            if (Schema::hasColumn('outlet_stock_opnames', 'voided_at')) {
                $table->dropColumn('voided_at');
            }
        });
    }
};
