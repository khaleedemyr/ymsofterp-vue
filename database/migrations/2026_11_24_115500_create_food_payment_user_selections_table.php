<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_payment_user_selections', function (Blueprint $table) {
            $table->increments('id');
            // users.id = int(10) unsigned
            $table->unsignedInteger('user_id');
            // food_payments.id = int(11) signed
            $table->integer('food_payment_id');
            $table->timestamps();

            $table->unique(['user_id', 'food_payment_id'], 'fp_user_selection_unique');
            $table->index('user_id');
            $table->index('food_payment_id');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('food_payment_id')
                ->references('id')
                ->on('food_payments')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_payment_user_selections');
    }
};
