<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_regional', function (Blueprint $table) {
            if (! Schema::hasColumn('user_regional', 'areas')) {
                $table->json('areas')->nullable()->after('area')
                    ->comment('Regional areas: Bar, Kitchen, Service (multi-select)');
            }
        });

        if (Schema::hasColumn('user_regional', 'areas')) {
            DB::table('user_regional')
                ->whereNull('areas')
                ->whereNotNull('area')
                ->orderBy('id')
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('user_regional')
                            ->where('id', $row->id)
                            ->update(['areas' => json_encode([(string) $row->area])]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('user_regional', function (Blueprint $table) {
            if (Schema::hasColumn('user_regional', 'areas')) {
                $table->dropColumn('areas');
            }
        });
    }
};
