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
        Schema::create('outlet_food_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->bigInteger('outlet_food_good_receive_id');
            $table->bigInteger('outlet_id');
            $table->bigInteger('warehouse_outlet_id');
            $table->date('return_date');
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'approved', 'rejected'])->default('draft');
            $table->bigInteger('created_by');
            $table->bigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('outlet_food_good_receive_id')->references('id')->on('outlet_food_good_receives')->onDelete('cascade');
            $table->foreign('outlet_id')->references('id_outlet')->on('tbl_data_outlet')->onDelete('cascade');
            $table->foreign('warehouse_outlet_id')->references('id')->on('warehouse_outlets')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['outlet_id', 'return_date']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outlet_food_returns');
    }
};
