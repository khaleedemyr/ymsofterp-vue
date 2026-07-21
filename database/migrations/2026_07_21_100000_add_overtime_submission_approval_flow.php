<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('overtime_submissions') && ! Schema::hasColumn('overtime_submissions', 'status')) {
            Schema::table('overtime_submissions', function (Blueprint $table) {
                $table->string('status', 20)->default('SUBMITTED')->after('notes');
            });

            DB::table('overtime_submissions')->whereNull('deleted_at')->update(['status' => 'APPROVED']);
        }

        if (! Schema::hasTable('overtime_submission_approval_flows')) {
            Schema::create('overtime_submission_approval_flows', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('overtime_submission_id');
                $table->unsignedBigInteger('approver_id');
                $table->unsignedInteger('approval_level')->default(1);
                $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->text('comments')->nullable();
                $table->timestamps();

                $table->foreign('overtime_submission_id', 'fk_ot_flow_submission')
                    ->references('id')
                    ->on('overtime_submissions')
                    ->onDelete('cascade');
                $table->index(['approver_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_submission_approval_flows');

        if (Schema::hasTable('overtime_submissions') && Schema::hasColumn('overtime_submissions', 'status')) {
            Schema::table('overtime_submissions', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
