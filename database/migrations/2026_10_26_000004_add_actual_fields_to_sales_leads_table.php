<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_leads', function (Blueprint $table) {
            $table->unsignedInteger('real_pax')->nullable()->after('pax');
            $table->decimal('real_revenue', 15, 2)->nullable()->after('revenue');
            $table->string('bill_photo')->nullable()->after('real_revenue');
            $table->text('closing_remarks')->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('sales_leads', function (Blueprint $table) {
            $table->dropColumn(['real_pax', 'real_revenue', 'bill_photo', 'closing_remarks']);
        });
    }
};
