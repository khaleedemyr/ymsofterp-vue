<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('food_floor_order_approval_flows')) {
            return;
        }

        Schema::table('food_floor_order_approval_flows', function (Blueprint $table) {
            $table->index(['food_floor_order_id', 'approval_level'], 'ffo_af_order_level_idx');
            $table->index(['approver_id', 'status'], 'ffo_af_approver_status_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('food_floor_order_approval_flows')) {
            return;
        }

        Schema::table('food_floor_order_approval_flows', function (Blueprint $table) {
            $table->dropIndex('ffo_af_order_level_idx');
            $table->dropIndex('ffo_af_approver_status_idx');
        });
    }
};
