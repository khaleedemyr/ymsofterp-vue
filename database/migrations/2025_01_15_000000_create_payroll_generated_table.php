<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payroll_generated', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('outlet_id');
            $table->integer('month');
            $table->integer('year');
            $table->decimal('service_charge', 15, 2)->default(0);
            $table->longText('payroll_data'); // JSON data payroll
            $table->enum('status', ['draft', 'generated', 'locked'])->default('generated');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['outlet_id', 'month', 'year']);
            $table->foreign('outlet_id')->references('id_outlet')->on('tbl_data_outlet')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_generated');
    }
};
