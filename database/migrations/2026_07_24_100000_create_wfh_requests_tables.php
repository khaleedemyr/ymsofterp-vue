<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wfh_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->unsignedBigInteger('user_id');
            $table->date('wfh_date');
            $table->text('reason');
            $table->string('status', 20)->default('SUBMITTED');
            $table->unsignedInteger('outlet_id')->nullable();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->string('shift_name')->nullable();
            $table->time('time_start')->nullable();
            $table->time('time_end')->nullable();
            $table->string('sn')->nullable();
            $table->string('pin')->nullable();
            $table->timestamp('att_log_written_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'wfh_date']);
            $table->index(['status']);
            $table->index(['created_by']);
        });

        Schema::create('wfh_request_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wfh_request_id');
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->string('description', 500);
            $table->timestamps();

            $table->foreign('wfh_request_id')->references('id')->on('wfh_requests')->onDelete('cascade');
            $table->index(['wfh_request_id', 'sort_order']);
        });

        Schema::create('wfh_request_approval_flows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wfh_request_id');
            $table->unsignedBigInteger('approver_id');
            $table->unsignedInteger('approval_level');
            $table->string('status', 20)->default('PENDING');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->foreign('wfh_request_id', 'fk_wfh_flow_request')
                ->references('id')
                ->on('wfh_requests')
                ->onDelete('cascade');
            $table->index(['wfh_request_id', 'approval_level'], 'idx_wfh_flow_request_level');
            $table->index(['approver_id', 'status'], 'idx_wfh_flow_approver_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wfh_request_approval_flows');
        Schema::dropIfExists('wfh_request_tasks');
        Schema::dropIfExists('wfh_requests');
    }
};
