<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('it_work_report_evidences', function (Blueprint $table) {
            $table->unsignedBigInteger('it_work_report_item_id')->nullable()->after('it_work_report_id');
            $table->index('it_work_report_item_id');
            $table->foreign('it_work_report_item_id')
                ->references('id')
                ->on('it_work_report_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('it_work_report_evidences', function (Blueprint $table) {
            $table->dropForeign(['it_work_report_item_id']);
            $table->dropColumn('it_work_report_item_id');
        });
    }
};
