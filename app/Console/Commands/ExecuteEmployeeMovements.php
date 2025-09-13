<?php

namespace App\Console\Commands;

use App\Models\EmployeeMovement;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExecuteEmployeeMovements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'employee-movements:execute';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute approved employee movements on their effective date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = now()->toDateString();
        
        // Ambil semua employee movements yang sudah approved dan effective date-nya hari ini
        $movements = EmployeeMovement::where('status', 'approved')
            ->whereDate('employment_effective_date', $today)
            ->get();

        if ($movements->isEmpty()) {
            $this->info('No employee movements to execute today.');
            return;
        }

        $this->info("Found {$movements->count()} employee movements to execute today.");

        $successCount = 0;
        $errorCount = 0;

        foreach ($movements as $movement) {
            try {
                $this->executeEmployeeMovement($movement);
                $successCount++;
                $this->info("✓ Executed movement for {$movement->employee_name}");
            } catch (\Exception $e) {
                $errorCount++;
                $this->error("✗ Failed to execute movement for {$movement->employee_name}: {$e->getMessage()}");
                Log::error('Failed to execute employee movement', [
                    'movement_id' => $movement->id,
                    'employee_name' => $movement->employee_name,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $this->info("Execution completed. Success: {$successCount}, Errors: {$errorCount}");
    }

    /**
     * Execute employee movement changes
     */
    private function executeEmployeeMovement($movement)
    {
        DB::beginTransaction();

        try {
            $employee = User::find($movement->employee_id);
            if (!$employee) {
                throw new \Exception('Employee not found');
            }

            $now = now();

            // 1. Jika position diubah, ubah id_jabatan di users
            if ($movement->position_change && $movement->position_to) {
                $employee->update(['id_jabatan' => $movement->position_to]);
            }

            // 2. Jika level diubah, ubah id_level di tbl_data_jabatan
            if ($movement->level_change && $movement->level_to) {
                $newJabatan = DB::table('tbl_data_jabatan')
                    ->where('id_jabatan', $movement->position_to)
                    ->first();
                
                if ($newJabatan) {
                    DB::table('tbl_data_jabatan')
                        ->where('id_jabatan', $movement->position_to)
                        ->update(['id_level' => $movement->level_to]);
                }
            }

            // 3. Jika salary diubah, ubah gaji dan tunjangan di payroll_master
            if ($movement->salary_change && $movement->salary_to) {
                $payrollData = DB::table('payroll_master')
                    ->where('user_id', $employee->id)
                    ->first();

                if ($payrollData) {
                    DB::table('payroll_master')
                        ->where('user_id', $employee->id)
                        ->update([
                            'gaji' => $movement->gaji_pokok_to,
                            'tunjangan' => $movement->tunjangan_to,
                            'updated_at' => $now
                        ]);
                } else {
                    DB::table('payroll_master')->insert([
                        'user_id' => $employee->id,
                        'outlet_id' => $employee->id_outlet,
                        'division_id' => $employee->division_id,
                        'gaji' => $movement->gaji_pokok_to,
                        'tunjangan' => $movement->tunjangan_to,
                        'ot' => 0,
                        'um' => 0,
                        'ph' => 0,
                        'sc' => 0,
                        'bpjs_jkn' => 0,
                        'bpjs_tk' => 0,
                        'lb' => 0,
                        'created_at' => $now,
                        'updated_at' => $now
                    ]);
                }
            }

            // 4. Jika division diubah, ubah division_id di users
            if ($movement->division_change && $movement->division_to) {
                $employee->update(['division_id' => $movement->division_to]);
            }

            // 5. Jika unit/property diubah, ubah id_outlet di users
            if ($movement->unit_property_change && $movement->unit_property_to) {
                $employee->update(['id_outlet' => $movement->unit_property_to]);
            }

            // 6. Jika employment type adalah Termination, set status='N'
            if ($movement->employment_type === 'termination') {
                $employee->update(['status' => 'N']);
            }

            // Update movement status menjadi executed
            $movement->update(['status' => 'executed']);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            $movement->update(['status' => 'rejected']);
            throw $e;
        }
    }
}
