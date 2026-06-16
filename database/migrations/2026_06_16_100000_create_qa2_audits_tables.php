<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa2_audits', function (Blueprint $table) {
            $table->id();
            $table->string('audit_number', 40)->unique();
            $table->dateTime('audit_datetime');
            $table->unsignedBigInteger('outlet_id');
            $table->unsignedBigInteger('template_id');
            $table->unsignedBigInteger('created_by');
            $table->dateTime('audit_time_start')->nullable();
            $table->dateTime('audit_time_end')->nullable();
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['outlet_id', 'status']);
            $table->index(['template_id']);
            $table->index(['created_by']);
        });

        Schema::create('qa2_audit_auditors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('audit_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->unique(['audit_id', 'user_id']);
            $table->index(['user_id']);
        });

        Schema::create('qa2_audit_auditees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('audit_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->unique(['audit_id', 'user_id']);
            $table->index(['user_id']);
        });

        Schema::create('qa2_audit_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('audit_id');
            $table->unsignedBigInteger('template_item_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('subcategory_id')->nullable();
            $table->unsignedBigInteger('parameter_id');
            $table->string('parameter_code', 60)->nullable();
            $table->text('parameter_text');
            $table->integer('sort_order')->default(1);
            $table->string('result', 5)->nullable();
            $table->text('comment')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->index(['audit_id', 'sort_order']);
            $table->index(['audit_id', 'result']);
        });

        Schema::create('qa2_audit_item_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('audit_item_id');
            $table->unsignedBigInteger('uploaded_by');
            $table->string('media_type', 15)->default('photo');
            $table->string('file_path', 255);
            $table->timestamps();

            $table->index(['audit_item_id']);
        });

        Schema::create('qa2_audit_caps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('audit_item_id')->unique();
            $table->unsignedBigInteger('filled_by')->nullable();
            $table->text('action_plan')->nullable();
            $table->date('target_date')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamps();
        });

        Schema::create('qa2_audit_cap_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cap_id');
            $table->unsignedBigInteger('uploaded_by');
            $table->string('media_type', 15)->default('photo');
            $table->string('file_path', 255);
            $table->timestamps();

            $table->index(['cap_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa2_audit_cap_media');
        Schema::dropIfExists('qa2_audit_caps');
        Schema::dropIfExists('qa2_audit_item_media');
        Schema::dropIfExists('qa2_audit_items');
        Schema::dropIfExists('qa2_audit_auditees');
        Schema::dropIfExists('qa2_audit_auditors');
        Schema::dropIfExists('qa2_audits');
    }
};
