<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sop_development_completion_approval_flows')) {
            Schema::create('sop_development_completion_approval_flows', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sop_development_completion_id')->index('sdc_af_completion_id_idx');
                $table->unsignedInteger('approver_id')->index();
                $table->unsignedSmallInteger('approval_level');
                $table->string('status', 32)->default('PENDING');
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->text('comments')->nullable();
                $table->timestamps();

                $table->index(['sop_development_completion_id', 'status'], 'sdc_af_completion_status_idx');
                $table->index(['sop_development_completion_id', 'approval_level'], 'sdc_af_completion_level_idx');
            });
        }

        if (Schema::hasTable('sop_development_completions') && Schema::hasColumn('sop_development_completions', 'approver_id')) {
            $rows = DB::table('sop_development_completions')
                ->whereNotNull('approver_id')
                ->whereIn('status', ['pending', 'approved', 'rejected'])
                ->get(['id', 'approver_id', 'status', 'approval_notes', 'approved_at', 'rejected_at']);

            foreach ($rows as $row) {
                $exists = DB::table('sop_development_completion_approval_flows')
                    ->where('sop_development_completion_id', $row->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $flowStatus = match ($row->status) {
                    'approved' => 'APPROVED',
                    'rejected' => 'REJECTED',
                    default => 'PENDING',
                };

                DB::table('sop_development_completion_approval_flows')->insert([
                    'sop_development_completion_id' => $row->id,
                    'approver_id' => $row->approver_id,
                    'approval_level' => 1,
                    'status' => $flowStatus,
                    'approved_at' => $row->approved_at,
                    'rejected_at' => $row->rejected_at,
                    'comments' => $row->approval_notes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Schema::table('sop_development_completions', function (Blueprint $table) {
                $table->dropColumn('approver_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('sop_development_completions')) {
            Schema::dropIfExists('sop_development_completion_approval_flows');

            return;
        }

        if (! Schema::hasColumn('sop_development_completions', 'approver_id')) {
            Schema::table('sop_development_completions', function (Blueprint $table) {
                $table->unsignedInteger('approver_id')->nullable()->index()->after('file_original_name');
            });
        }

        Schema::dropIfExists('sop_development_completion_approval_flows');
    }
};
