<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_requisition_categories')) {
            return;
        }

        if (Schema::hasColumn('purchase_requisition_categories', 'active')) {
            return;
        }

        Schema::table('purchase_requisition_categories', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('description');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_requisition_categories')) {
            return;
        }

        if (! Schema::hasColumn('purchase_requisition_categories', 'active')) {
            return;
        }

        Schema::table('purchase_requisition_categories', function (Blueprint $table) {
            $table->dropColumn('active');
        });
    }
};
