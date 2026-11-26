<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_lead_reos', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_lead_reos', 'maintenance')) {
                $table->text('maintenance')->nullable()->after('kitchen_maintenance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_lead_reos', function (Blueprint $table) {
            if (Schema::hasColumn('sales_lead_reos', 'maintenance')) {
                $table->dropColumn('maintenance');
            }
        });
    }
};
