<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('leave_types')
            ->where('name', 'Cuti Khusus')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('leave_types')->insert([
            'name' => 'Cuti Khusus',
            'max_days' => 0,
            'requires_document' => false,
            'description' => 'Cuti khusus tanpa potong saldo. Cukup isi tanggal, alasan, dan approver.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('leave_types')
            ->where('name', 'Cuti Khusus')
            ->delete();
    }
};
