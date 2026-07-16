<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_work_reports', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->date('work_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedInteger('outlet_id');
            $table->string('outlet_name')->nullable();
            $table->unsignedBigInteger('executor_id');
            $table->string('source_type', 20); // proactive|ticket|whatsapp
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->string('wa_contact_name')->nullable();
            $table->string('wa_phone', 40)->nullable();
            $table->dateTime('wa_reported_at')->nullable();
            $table->text('wa_summary')->nullable();
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('draft'); // draft|submitted
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['outlet_id', 'work_date']);
            $table->index(['source_type', 'status']);
            $table->index('ticket_id');
            $table->index('executor_id');
        });

        Schema::create('it_work_report_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('it_work_report_id');
            $table->string('device_type', 40);
            $table->string('device_label');
            $table->string('identifier')->nullable();
            $table->json('scopes');
            $table->text('notes')->nullable();
            $table->string('result', 30)->nullable(); // ok|issue_found|needs_followup
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('it_work_report_id')
                ->references('id')
                ->on('it_work_reports')
                ->cascadeOnDelete();
            $table->index('device_type');
        });

        Schema::create('it_work_report_evidences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('it_work_report_id');
            $table->string('kind', 30); // wa_screenshot|work|other
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('caption')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->foreign('it_work_report_id')
                ->references('id')
                ->on('it_work_reports')
                ->cascadeOnDelete();
            $table->index(['it_work_report_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_work_report_evidences');
        Schema::dropIfExists('it_work_report_items');
        Schema::dropIfExists('it_work_reports');
    }
};
