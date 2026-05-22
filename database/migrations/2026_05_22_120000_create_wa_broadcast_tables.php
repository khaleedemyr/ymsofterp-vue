<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_broadcast_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('status', 32)->default('draft'); // draft|building|scheduled|running|paused|completed|failed|cancelled
            $table->string('message_type', 32)->default('template'); // template|session_text
            $table->string('template_name', 128)->nullable();
            $table->string('template_language', 16)->default('id');
            $table->json('template_body_params')->nullable();
            $table->text('session_text')->nullable();
            $table->json('filter_definition');
            $table->unsignedInteger('recipient_count_estimated')->default(0);
            $table->unsignedInteger('recipient_count_total')->default(0);
            $table->unsignedInteger('recipient_count_sent')->default(0);
            $table->unsignedInteger('recipient_count_failed')->default(0);
            $table->unsignedInteger('recipient_count_skipped')->default(0);
            $table->unsignedInteger('daily_cap')->default(100000);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('phone_number_id', 64)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index('created_by_user_id');
        });

        Schema::create('wa_broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('wa_broadcast_campaigns')->cascadeOnDelete();
            $table->string('phone_normalized', 32);
            $table->string('wa_id', 32)->nullable();
            $table->unsignedBigInteger('member_apps_member_id')->nullable();
            $table->unsignedBigInteger('omni_contact_id')->nullable();
            $table->string('display_name', 255)->nullable();
            $table->string('source', 32); // member|omni_contact|manual
            $table->string('status', 32)->default('pending'); // pending|queued|sent|delivered|read|failed|skipped
            $table->string('skip_reason', 64)->nullable();
            $table->string('meta_message_id', 128)->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'phone_normalized']);
            $table->index(['campaign_id', 'status']);
        });

        Schema::create('wa_broadcast_daily_usage', function (Blueprint $table) {
            $table->id();
            $table->date('usage_date');
            $table->string('phone_number_id', 64)->default('');
            $table->unsignedInteger('sent_count')->default(0);
            $table->timestamps();

            $table->unique(['usage_date', 'phone_number_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_broadcast_daily_usage');
        Schema::dropIfExists('wa_broadcast_recipients');
        Schema::dropIfExists('wa_broadcast_campaigns');
    }
};
