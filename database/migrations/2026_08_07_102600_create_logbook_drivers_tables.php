<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logbook_drivers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->date('log_date');
            $table->unsignedInteger('outlet_id');
            $table->string('outlet_name')->nullable();
            $table->unsignedBigInteger('driver_id');
            $table->string('driver_name');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['outlet_id', 'log_date']);
            $table->index('driver_id');
            $table->index('log_date');
        });

        Schema::create('logbook_driver_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('logbook_driver_id');
            $table->time('log_time')->nullable();
            $table->text('description');
            $table->string('photo_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('logbook_driver_id')
                ->references('id')
                ->on('logbook_drivers')
                ->cascadeOnDelete();
            $table->index('logbook_driver_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logbook_driver_items');
        Schema::dropIfExists('logbook_drivers');
    }
};
