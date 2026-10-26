<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_lead_targets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_user_id');
            $table->string('segment_1');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('target_amount', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['sales_user_id', 'segment_1', 'year', 'month'], 'slt_user_segment_period_uq');
            $table->index(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_lead_targets');
    }
};
