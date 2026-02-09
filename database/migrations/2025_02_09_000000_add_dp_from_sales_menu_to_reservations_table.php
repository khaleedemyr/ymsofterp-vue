<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->decimal('dp', 15, 2)->nullable()->after('special_requests');
            $table->boolean('from_sales')->default(false)->after('dp')->comment('1 = dari sales, 0 = bukan');
            $table->longText('menu')->nullable()->after('from_sales');
        });
    }

    public function down()
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['dp', 'from_sales', 'menu']);
        });
    }
};
