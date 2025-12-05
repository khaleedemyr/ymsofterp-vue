<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlet_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('retail_sales_id')->nullable()->after('gr_id');
            $table->foreign('retail_sales_id')->references('id')->on('retail_warehouse_sales')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('outlet_payments', function (Blueprint $table) {
            $table->dropForeign(['retail_sales_id']);
            $table->dropColumn('retail_sales_id');
        });
    }
};
