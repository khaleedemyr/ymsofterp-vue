<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sub_categories') || Schema::hasColumn('sub_categories', 'coa_id')) {
            return;
        }

        Schema::table('sub_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('coa_id')->nullable()->after('category_id');

            if (Schema::hasTable('chart_of_accounts')) {
                $table->foreign('coa_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sub_categories') || !Schema::hasColumn('sub_categories', 'coa_id')) {
            return;
        }

        Schema::table('sub_categories', function (Blueprint $table) {
            try {
                $table->dropForeign(['coa_id']);
            } catch (\Throwable $th) {
            }

            $table->dropColumn('coa_id');
        });
    }
};
