<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_good_receive_item_serials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('good_receive_id');
            $table->unsignedBigInteger('good_receive_item_id');
            $table->unsignedBigInteger('po_id')->nullable();
            $table->unsignedBigInteger('po_item_id')->nullable();
            $table->unsignedBigInteger('pr_food_id')->nullable();
            $table->unsignedBigInteger('pr_food_item_id')->nullable();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('unit_id');
            $table->string('serial_number', 32)->unique();
            $table->string('gr_number')->nullable();
            $table->string('po_number')->nullable();
            $table->string('pr_number')->nullable();
            $table->decimal('source_qty_received', 18, 4)->default(0);
            $table->decimal('generated_qty_unit', 18, 4)->default(0);
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['good_receive_id', 'good_receive_item_id'], 'gr_item_serial_idx');
            $table->index(['item_id', 'unit_id'], 'item_unit_serial_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_good_receive_item_serials');
    }
};
