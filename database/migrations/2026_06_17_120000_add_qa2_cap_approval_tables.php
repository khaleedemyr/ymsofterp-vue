<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('qa2_audits')) {
            Schema::table('qa2_audits', function (Blueprint $table) {
                if (! Schema::hasColumn('qa2_audits', 'cap_submission_status')) {
                    $table->string('cap_submission_status', 30)->nullable()->after('status');
                }
                if (! Schema::hasColumn('qa2_audits', 'cap_submitted_at')) {
                    $table->dateTime('cap_submitted_at')->nullable()->after('cap_submission_status');
                }
                if (! Schema::hasColumn('qa2_audits', 'cap_submitted_by')) {
                    $table->unsignedBigInteger('cap_submitted_by')->nullable()->after('cap_submitted_at');
                }
            });
        }

        if (! Schema::hasTable('qa2_audit_cap_approval_flows')) {
            Schema::create('qa2_audit_cap_approval_flows', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('audit_id');
                $table->unsignedBigInteger('approver_id');
                $table->unsignedInteger('approval_level');
                $table->string('status', 20)->default('PENDING');
                $table->text('comments')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->dateTime('rejected_at')->nullable();
                $table->timestamps();

                $table->index(['audit_id', 'approval_level']);
                $table->index(['approver_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('qa2_audit_cap_approval_flows');

        if (Schema::hasTable('qa2_audits')) {
            Schema::table('qa2_audits', function (Blueprint $table) {
                if (Schema::hasColumn('qa2_audits', 'cap_submitted_by')) {
                    $table->dropColumn('cap_submitted_by');
                }
                if (Schema::hasColumn('qa2_audits', 'cap_submitted_at')) {
                    $table->dropColumn('cap_submitted_at');
                }
                if (Schema::hasColumn('qa2_audits', 'cap_submission_status')) {
                    $table->dropColumn('cap_submission_status');
                }
            });
        }
    }
};
