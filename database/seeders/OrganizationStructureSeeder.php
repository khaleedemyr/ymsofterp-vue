<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrganizationStructureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        DB::table('users')->where('id', '>', 0)->delete();
        DB::table('tbl_data_jabatan')->where('id_jabatan', '>', 0)->delete();

        // Insert sample job positions with hierarchy
        $jabatans = [
            // Top Level (Root)
            ['id_jabatan' => 1, 'nama_jabatan' => 'CEO', 'id_atasan' => null, 'id_divisi' => 1, 'id_sub_divisi' => null, 'id_level' => 1, 'status' => 'A'],
            
            // Level 2 - Direct reports to CEO
            ['id_jabatan' => 2, 'nama_jabatan' => 'Direktur Operasional', 'id_atasan' => 1, 'id_divisi' => 1, 'id_sub_divisi' => null, 'id_level' => 2, 'status' => 'A'],
            ['id_jabatan' => 3, 'nama_jabatan' => 'Direktur Keuangan', 'id_atasan' => 1, 'id_divisi' => 2, 'id_sub_divisi' => null, 'id_level' => 2, 'status' => 'A'],
            ['id_jabatan' => 4, 'nama_jabatan' => 'Direktur Marketing', 'id_atasan' => 1, 'id_divisi' => 3, 'id_sub_divisi' => null, 'id_level' => 2, 'status' => 'A'],
            
            // Level 3 - Reports to Direktur Operasional
            ['id_jabatan' => 5, 'nama_jabatan' => 'Manager HRD', 'id_atasan' => 2, 'id_divisi' => 1, 'id_sub_divisi' => 1, 'id_level' => 3, 'status' => 'A'],
            ['id_jabatan' => 6, 'nama_jabatan' => 'Manager IT', 'id_atasan' => 2, 'id_divisi' => 1, 'id_sub_divisi' => 2, 'id_level' => 3, 'status' => 'A'],
            ['id_jabatan' => 7, 'nama_jabatan' => 'Manager Produksi', 'id_atasan' => 2, 'id_divisi' => 1, 'id_sub_divisi' => 3, 'id_level' => 3, 'status' => 'A'],
            
            // Level 3 - Reports to Direktur Keuangan
            ['id_jabatan' => 8, 'nama_jabatan' => 'Manager Akuntansi', 'id_atasan' => 3, 'id_divisi' => 2, 'id_sub_divisi' => 4, 'id_level' => 3, 'status' => 'A'],
            ['id_jabatan' => 9, 'nama_jabatan' => 'Manager Keuangan', 'id_atasan' => 3, 'id_divisi' => 2, 'id_sub_divisi' => 5, 'id_level' => 3, 'status' => 'A'],
            
            // Level 3 - Reports to Direktur Marketing
            ['id_jabatan' => 10, 'nama_jabatan' => 'Manager Sales', 'id_atasan' => 4, 'id_divisi' => 3, 'id_sub_divisi' => 6, 'id_level' => 3, 'status' => 'A'],
            ['id_jabatan' => 11, 'nama_jabatan' => 'Manager Marketing', 'id_atasan' => 4, 'id_divisi' => 3, 'id_sub_divisi' => 7, 'id_level' => 3, 'status' => 'A'],
            
            // Level 4 - Staff positions
            ['id_jabatan' => 12, 'nama_jabatan' => 'Staff HRD', 'id_atasan' => 5, 'id_divisi' => 1, 'id_sub_divisi' => 1, 'id_level' => 4, 'status' => 'A'],
            ['id_jabatan' => 13, 'nama_jabatan' => 'Staff IT', 'id_atasan' => 6, 'id_divisi' => 1, 'id_sub_divisi' => 2, 'id_level' => 4, 'status' => 'A'],
            ['id_jabatan' => 14, 'nama_jabatan' => 'Staff Produksi', 'id_atasan' => 7, 'id_divisi' => 1, 'id_sub_divisi' => 3, 'id_level' => 4, 'status' => 'A'],
            ['id_jabatan' => 15, 'nama_jabatan' => 'Staff Akuntansi', 'id_atasan' => 8, 'id_divisi' => 2, 'id_sub_divisi' => 4, 'id_level' => 4, 'status' => 'A'],
            ['id_jabatan' => 16, 'nama_jabatan' => 'Staff Keuangan', 'id_atasan' => 9, 'id_divisi' => 2, 'id_sub_divisi' => 5, 'id_level' => 4, 'status' => 'A'],
            ['id_jabatan' => 17, 'nama_jabatan' => 'Staff Sales', 'id_atasan' => 10, 'id_divisi' => 3, 'id_sub_divisi' => 6, 'id_level' => 4, 'status' => 'A'],
            ['id_jabatan' => 18, 'nama_jabatan' => 'Staff Marketing', 'id_atasan' => 11, 'id_divisi' => 3, 'id_sub_divisi' => 7, 'id_level' => 4, 'status' => 'A'],
        ];

        DB::table('tbl_data_jabatan')->insert($jabatans);

        // Insert sample users for different outlets
        $users = [
            // Outlet 1 - Pusat
            ['id' => 1, 'nama_lengkap' => 'John CEO', 'email' => 'john@company.com', 'id_jabatan' => 1, 'id_outlet' => 1, 'status' => 'A', 'avatar' => null],
            ['id' => 2, 'nama_lengkap' => 'Jane Direktur Ops', 'email' => 'jane@company.com', 'id_jabatan' => 2, 'id_outlet' => 1, 'status' => 'A', 'avatar' => null],
            ['id' => 3, 'nama_lengkap' => 'Bob Direktur Keuangan', 'email' => 'bob@company.com', 'id_jabatan' => 3, 'id_outlet' => 1, 'status' => 'A', 'avatar' => null],
            ['id' => 4, 'nama_lengkap' => 'Alice Manager HRD', 'email' => 'alice@company.com', 'id_jabatan' => 5, 'id_outlet' => 1, 'status' => 'A', 'avatar' => null],
            ['id' => 5, 'nama_lengkap' => 'Charlie Staff HRD', 'email' => 'charlie@company.com', 'id_jabatan' => 12, 'id_outlet' => 1, 'status' => 'A', 'avatar' => null],
            
            // Outlet 2 - Cabang
            ['id' => 6, 'nama_lengkap' => 'David Manager Cabang', 'email' => 'david@company.com', 'id_jabatan' => 2, 'id_outlet' => 2, 'status' => 'A', 'avatar' => null],
            ['id' => 7, 'nama_lengkap' => 'Eva Staff Cabang', 'email' => 'eva@company.com', 'id_jabatan' => 12, 'id_outlet' => 2, 'status' => 'A', 'avatar' => null],
        ];

        DB::table('users')->insert($users);

        // Insert sample divisions
        $divisions = [
            ['id' => 1, 'nama_divisi' => 'Operasional', 'status' => 'A'],
            ['id' => 2, 'nama_divisi' => 'Keuangan', 'status' => 'A'],
            ['id' => 3, 'nama_divisi' => 'Marketing', 'status' => 'A'],
        ];

        DB::table('tbl_data_divisi')->insert($divisions);

        // Insert sample sub divisions
        $subDivisions = [
            ['id' => 1, 'nama_sub_divisi' => 'HRD', 'id_divisi' => 1, 'status' => 'A'],
            ['id' => 2, 'nama_sub_divisi' => 'IT', 'id_divisi' => 1, 'status' => 'A'],
            ['id' => 3, 'nama_sub_divisi' => 'Produksi', 'id_divisi' => 1, 'status' => 'A'],
            ['id' => 4, 'nama_sub_divisi' => 'Akuntansi', 'id_divisi' => 2, 'status' => 'A'],
            ['id' => 5, 'nama_sub_divisi' => 'Keuangan', 'id_divisi' => 2, 'status' => 'A'],
            ['id' => 6, 'nama_sub_divisi' => 'Sales', 'id_divisi' => 3, 'status' => 'A'],
            ['id' => 7, 'nama_sub_divisi' => 'Marketing', 'id_divisi' => 3, 'status' => 'A'],
        ];

        DB::table('tbl_data_sub_divisi')->insert($subDivisions);

        // Insert sample levels
        $levels = [
            ['id' => 1, 'nama_level' => 'Executive', 'nilai_level' => 10, 'status' => 'A'],
            ['id' => 2, 'nama_level' => 'Director', 'nilai_level' => 9, 'status' => 'A'],
            ['id' => 3, 'nama_level' => 'Manager', 'nilai_level' => 8, 'status' => 'A'],
            ['id' => 4, 'nama_level' => 'Staff', 'nilai_level' => 7, 'status' => 'A'],
        ];

        DB::table('tbl_data_level')->insert($levels);

        // Insert sample outlets
        $outlets = [
            ['id_outlet' => 1, 'nama_outlet' => 'Outlet Pusat', 'lokasi' => 'Jakarta', 'status' => 'A'],
            ['id_outlet' => 2, 'nama_outlet' => 'Outlet Cabang', 'lokasi' => 'Bandung', 'status' => 'A'],
        ];

        DB::table('tbl_data_outlet')->insert($outlets);

        $this->command->info('Organization structure seeded successfully!');
    }
}
