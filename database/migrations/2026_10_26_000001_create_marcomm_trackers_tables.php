<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marcomm_trackers', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('job_category');
            $table->unsignedBigInteger('pic_user_id');
            $table->longText('task')->nullable();
            $table->date('due_date')->nullable();
            // Selectable job progress status
            $table->string('job_status')->default('on_progress'); // on_progress|rev_1|rev_2
            // Remarks per job status (JSON object)
            $table->json('status_remarks')->nullable();
            $table->json('output_links')->nullable();
            $table->text('remarks')->nullable();
            // Workflow: draft → submitted (has approvers) → approved / rejected
            $table->string('workflow_status')->default('draft');
            $table->unsignedBigInteger('created_by');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('job_category');
            $table->index('pic_user_id');
            $table->index('job_status');
            $table->index('workflow_status');
            $table->index('due_date');
            $table->index('created_by');
        });

        Schema::create('marcomm_tracker_approval_flows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marcomm_tracker_id');
            $table->unsignedBigInteger('approver_id');
            $table->unsignedInteger('approval_level')->default(1);
            $table->string('status')->default('PENDING'); // PENDING|APPROVED|REJECTED
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['marcomm_tracker_id', 'approval_level'], 'mt_af_tracker_level');
            $table->index(['approver_id', 'status'], 'mt_af_approver_status');
            $table->foreign('marcomm_tracker_id', 'mt_af_tracker_fk')
                ->references('id')->on('marcomm_trackers')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marcomm_tracker_approval_flows');
        Schema::dropIfExists('marcomm_trackers');
    }
};
