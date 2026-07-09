<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sop_development_completions')) {
            return;
        }

        Schema::create('sop_development_completions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date');
            $table->string('file_path')->nullable();
            $table->string('file_original_name')->nullable();
            $table->unsignedInteger('approver_id')->nullable()->index();
            $table->string('status', 20)->default('draft')->comment('draft, pending, approved, rejected');
            $table->text('approval_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->unsignedSmallInteger('resubmission_count')->default(0);
            $table->timestamps();

            $table->index(['status', 'approver_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sop_development_completions');
    }
};
