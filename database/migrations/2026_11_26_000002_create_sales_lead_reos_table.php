<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_lead_reos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_lead_id')->unique();
            $table->string('number')->unique();
            $table->string('company_name');
            $table->text('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->date('date_of_issue')->nullable();
            $table->string('venue_setup')->nullable();
            $table->unsignedBigInteger('sales_person_user_id')->nullable();
            $table->string('sales_person_name')->nullable();
            $table->text('sign_board')->nullable();
            $table->json('revenue_items')->nullable();
            $table->decimal('catering_fee_percent', 5, 2)->default(30);
            $table->decimal('subtotal_rate', 15, 2)->default(0);
            $table->decimal('subtotal_nett', 15, 2)->default(0);
            $table->decimal('catering_fee_amount', 15, 2)->default(0);
            $table->decimal('total_restaurant_revenue', 15, 2)->default(0);
            $table->decimal('total_transaction', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->unsignedInteger('pax')->nullable();
            $table->date('date_of_event')->nullable();
            $table->json('time_of_event')->nullable();
            $table->text('menu_items')->nullable();
            $table->text('kitchen_maintenance')->nullable();
            $table->text('marcom')->nullable();
            $table->text('billing_instruction')->nullable();
            $table->string('prepared_by_name')->nullable();
            $table->string('prepared_by_title')->nullable();
            $table->string('acknowledge_by_1_name')->nullable();
            $table->string('acknowledge_by_1_title')->nullable();
            $table->string('acknowledge_by_2_name')->nullable();
            $table->string('acknowledge_by_2_title')->nullable();
            $table->string('approved_by_name')->nullable();
            $table->string('approved_by_title')->nullable();
            $table->string('status', 30)->default('DRAFT'); // DRAFT, SUBMITTED, APPROVED, REJECTED
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('sales_lead_id')->references('id')->on('sales_leads')->cascadeOnDelete();
            $table->index('status');
        });

        Schema::create('sales_lead_reo_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reo_id');
            $table->unsignedBigInteger('approver_id');
            $table->unsignedInteger('approval_level')->default(1);
            $table->string('status', 20)->default('PENDING'); // PENDING, APPROVED, REJECTED
            $table->string('signature_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->foreign('reo_id')->references('id')->on('sales_lead_reos')->cascadeOnDelete();
            $table->index(['approver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_lead_reo_approvals');
        Schema::dropIfExists('sales_lead_reos');
    }
};
