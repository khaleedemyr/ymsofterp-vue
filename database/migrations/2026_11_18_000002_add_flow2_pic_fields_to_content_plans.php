<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_plans', function (Blueprint $table) {
            $table->date('pic_submit_date')->nullable()->after('priority');
            $table->text('task')->nullable()->after('pic_submit_date');
            $table->text('pic_remarks')->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('content_plans', function (Blueprint $table) {
            $table->dropColumn(['pic_submit_date', 'task', 'pic_remarks']);
        });
    }
};
