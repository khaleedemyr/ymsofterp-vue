<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrganizationChartSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        DB::table('users')->where('id', '>', 0)->delete();
        DB::table('tbl_data_jabatan')->where('id_jabatan', '>', 0)->delete();

        // Insert sample job positions
        $jabatan = [
            ['id_jabatan' => 1, 'nama_jabatan' => 'CEO', 'id_atasan' => null, 'status' => 'A'],
            ['id_jabatan' => 2, 'nama_jabatan' => 'Direktur Operasional', 'id_atasan' => 1, 'status' => 'A'],
            ['id_jabatan' => 3, 'nama_jabatan' => 'Direktur Keuangan', 'id_atasan' => 1, 'status' => 'A'],
            ['id_jabatan' => 4, 'nama_jabatan' => 'Manager HRD', 'id_atasan' => 2, 'status' => 'A'],
            ['id_jabatan' => 5, 'nama_jabatan' => 'Manager IT', 'id_atasan' => 2, 'status' => 'A'],
            ['id_jabatan' => 6, 'nama_jabatan' => 'Manager Marketing', 'id_atasan' => 2, 'status' => 'A'],
            ['id_jabatan' => 7, 'nama_jabatan' => 'Staff HRD', 'id_atasan' => 4, 'status' => 'A'],
            ['id_jabatan' => 8, 'nama_jabatan' => 'Staff IT', 'id_atasan' => 5, 'status' => 'A'],
            ['id_jabatan' => 9, 'nama_jabatan' => 'Staff Marketing', 'id_atasan' => 6, 'status' => 'A'],
            ['id_jabatan' => 10, 'nama_jabatan' => 'Staff Keuangan', 'id_atasan' => 3, 'status' => 'A'],
        ];

        DB::table('tbl_data_jabatan')->insert($jabatan);

        // Insert sample users
        $users = [
            ['id' => 1, 'nama_lengkap' => 'Budi Santoso', 'id_jabatan' => 1, 'avatar' => null, 'status' => 'A'],
            ['id' => 2, 'nama_lengkap' => 'Siti Nurhaliza', 'id_jabatan' => 2, 'avatar' => null, 'status' => 'A'],
            ['id' => 3, 'nama_lengkap' => 'Ahmad Wijaya', 'id_jabatan' => 3, 'avatar' => null, 'status' => 'A'],
            ['id' => 4, 'nama_lengkap' => 'Maya Sari', 'id_jabatan' => 4, 'avatar' => null, 'status' => 'A'],
            ['id' => 5, 'nama_lengkap' => 'Rizki Pratama', 'id_jabatan' => 5, 'avatar' => null, 'status' => 'A'],
            ['id' => 6, 'nama_lengkap' => 'Dewi Lestari', 'id_jabatan' => 6, 'avatar' => null, 'status' => 'A'],
            ['id' => 7, 'nama_lengkap' => 'Fajar Nugroho', 'id_jabatan' => 7, 'avatar' => null, 'status' => 'A'],
            ['id' => 8, 'nama_lengkap' => 'Indah Permata', 'id_jabatan' => 8, 'avatar' => null, 'status' => 'A'],
            ['id' => 9, 'nama_lengkap' => 'Bambang Sutrisno', 'id_jabatan' => 9, 'avatar' => null, 'status' => 'A'],
            ['id' => 10, 'nama_lengkap' => 'Citra Dewi', 'id_jabatan' => 10, 'avatar' => null, 'status' => 'A'],
        ];

        DB::table('users')->insert($users);

        $this->command->info('Organization Chart sample data seeded successfully!');
    }
}
