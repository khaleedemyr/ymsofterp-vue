<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_plans', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('platform');
            $table->date('date_post')->nullable();
            $table->string('day_name')->nullable();
            $table->string('funnel')->nullable();
            $table->string('pillar')->nullable();
            $table->string('format')->nullable();
            $table->longText('objective')->nullable();
            $table->longText('brief')->nullable();
            $table->longText('reference')->nullable();
            $table->text('caption')->nullable();
            $table->string('job_status')->default('on_crafting'); // on_crafting|waiting_list|rev_1|rev_2
            $table->json('final_output_links')->nullable();
            $table->date('actual_post_date')->nullable();
            $table->json('actual_post_links')->nullable();
            $table->text('remarks')->nullable();
            $table->string('workflow_status')->default('draft'); // draft|submitted|approved|rejected
            $table->unsignedBigInteger('created_by');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('platform');
            $table->index('date_post');
            $table->index('funnel');
            $table->index('pillar');
            $table->index('job_status');
            $table->index('workflow_status');
            $table->index('created_by');
        });

        Schema::create('content_plan_approval_flows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('content_plan_id');
            $table->unsignedBigInteger('approver_id');
            $table->unsignedInteger('approval_level')->default(1);
            $table->string('status')->default('PENDING');
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['content_plan_id', 'approval_level'], 'cp_af_plan_level');
            $table->index(['approver_id', 'status'], 'cp_af_approver_status');
            $table->foreign('content_plan_id', 'cp_af_plan_fk')
                ->references('id')->on('content_plans')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_plan_approval_flows');
        Schema::dropIfExists('content_plans');
    }
};
