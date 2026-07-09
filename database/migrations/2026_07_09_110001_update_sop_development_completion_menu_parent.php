<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Update menu SOP Development Completion ke Ops Management (parent_id 184).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('erp_menu')
            ->where('code', 'sop_development_completion')
            ->update([
                'parent_id' => 184,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('erp_menu')
            ->where('code', 'sop_development_completion')
            ->update([
                'parent_id' => null,
                'updated_at' => now(),
            ]);
    }
};
