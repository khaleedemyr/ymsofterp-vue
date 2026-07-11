<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('food_floor_order_approval_flows')) {
            return;
        }

        Schema::create('food_floor_order_approval_flows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('food_floor_order_id');
            $table->unsignedBigInteger('approver_id');
            $table->unsignedInteger('approval_level');
            $table->string('status', 20)->default('PENDING');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->index(['food_floor_order_id', 'approval_level'], 'ffo_af_order_level_idx');
            $table->index(['approver_id', 'status'], 'ffo_af_approver_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_floor_order_approval_flows');
    }
};
