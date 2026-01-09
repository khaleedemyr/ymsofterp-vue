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
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('default_counter_account_id')->nullable()->after('budget_limit');
            $table->foreign('default_counter_account_id')
                  ->references('id')`
                  ->on('chart_of_accounts')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropForeign(['default_counter_account_id']);
            $table->dropColumn('default_counter_account_id');
        });
    }
};
