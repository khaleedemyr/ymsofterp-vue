<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_leads', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_leads', 'room_requirement')) {
                $table->string('room_requirement', 50)->nullable()->after('outlet_id');
            }
            if (! Schema::hasColumn('sales_leads', 'attachment')) {
                $table->string('attachment')->nullable()->after('closing_remarks');
            }
            if (! Schema::hasColumn('sales_leads', 'attachment_original_name')) {
                $table->string('attachment_original_name')->nullable()->after('attachment');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_leads', function (Blueprint $table) {
            $cols = array_values(array_filter([
                Schema::hasColumn('sales_leads', 'room_requirement') ? 'room_requirement' : null,
                Schema::hasColumn('sales_leads', 'attachment') ? 'attachment' : null,
                Schema::hasColumn('sales_leads', 'attachment_original_name') ? 'attachment_original_name' : null,
            ]));
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
