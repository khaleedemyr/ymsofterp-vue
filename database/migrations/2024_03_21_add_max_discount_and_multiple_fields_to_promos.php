<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->decimal('max_discount', 10, 2)->nullable()->after('value');
            $table->string('is_multiple', 3)->default('No')->after('max_discount');
        });
    }

    public function down()
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->dropColumn(['max_discount', 'is_multiple']);
        });
    }
}; 