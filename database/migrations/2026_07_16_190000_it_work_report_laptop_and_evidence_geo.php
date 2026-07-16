<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('it_work_report_items', function (Blueprint $table) {
            $table->string('laptop_user_name')->nullable()->after('identifier');
        });

        Schema::table('it_work_report_evidences', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('caption');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('address', 500)->nullable()->after('longitude');
            $table->string('maps_url', 500)->nullable()->after('address');
            $table->timestamp('captured_at')->nullable()->after('maps_url');
        });
    }

    public function down(): void
    {
        Schema::table('it_work_report_items', function (Blueprint $table) {
            $table->dropColumn('laptop_user_name');
        });

        Schema::table('it_work_report_evidences', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'address', 'maps_url', 'captured_at']);
        });
    }
};
