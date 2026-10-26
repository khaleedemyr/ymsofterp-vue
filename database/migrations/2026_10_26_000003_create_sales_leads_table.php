<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_leads', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('company_name');
            $table->string('segment_1')->nullable();
            $table->string('segment_2')->nullable();
            $table->string('type_of_event')->nullable();
            $table->date('event_date')->nullable();
            $table->unsignedInteger('pax')->nullable();
            $table->unsignedBigInteger('outlet_id')->nullable();
            $table->string('status')->default('new_leads');
            $table->decimal('revenue', 15, 2)->nullable();
            $table->unsignedBigInteger('sales_user_id'); // auto: creator
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('company_name');
            $table->index('segment_1');
            $table->index('segment_2');
            $table->index('type_of_event');
            $table->index('event_date');
            $table->index('outlet_id');
            $table->index('status');
            $table->index('sales_user_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_leads');
    }
};
