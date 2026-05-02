<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\KalenderPerusahaan;
use App\Models\Jabatan;
use App\Models\DataLevel;
use App\Models\HolidayAttendanceCompensation;
use Illuminate\Support\Collection;

class HolidayAttendanceService
{
    /**
     * Process holiday attendance for a specific date
     * 
     * @param string $date Date in Y-m-d format
     * @return array Results of processing
     */
    public function processHolidayAttendance($date)
    {
        $results = [
            'processed' => 0,
            'extra_off_given' => 0,
            'bonus_paid' => 0,
            'errors' => []
        ];

        try {
            // Check if the date is a holiday
            if (!$this->isHoliday($date)) {
                return $results;
            }

            // Get all employees who worked on this holiday
            $employeesWhoWorked = $this->getEmployeesWhoWorkedOnHoliday($date);

            foreach ($employeesWhoWorked as $employee) {
                try {
                    $this->processEmployeeHolidayAttendance($employee, $date);
                    $results['processed']++;
                    // Kerja di hari libur selalu mengisi saldo PH (compensation_type bonus), bukan Extra Off.
                    $results['bonus_paid']++;

                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'user_id' => $employee->user_id,
                        'nama' => $employee->nama_lengkap,
                        'error' => $e->getMessage()
                    ];
                }
            }

        } catch (\Exception $e) {
            $results['errors'][] = [
                'general_error' => $e->getMessage()
            ];
        }

        return $results;
    }

    /**
     * Check if a date is a holiday
     * 
     * @param string $date
     * @return bool
     */
    public function isHoliday($date)
    {
        return KalenderPerusahaan::where('tgl_libur', $date)->exists();
    }

    /**
     * Get employees who worked on a holiday
     * 
     * @param string $date
     * @return \Illuminate\Support\Collection
     */
    public function getEmployeesWhoWorkedOnHoliday($date)
    {
        return DB::table('att_log as a')
            ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
            ->join('user_pins as up', function($q) {
                $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
            })
            ->join('users as u', 'up.user_id', '=', 'u.id')
            ->join('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->join('tbl_data_level as l', 'j.id_level', '=', 'l.id')
            ->where('a.scan_date', '>=', $date . ' 00:00:00')
            ->where('a.scan_date', '<', date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00')
            ->where('a.inoutmode', 1) // Check-in only (following AttendanceController logic)
            ->where('u.status', 'A') // Active employees only
            ->select([
                'u.id as user_id',
                'u.nama_lengkap',
                'u.id_jabatan',
                'j.nama_jabatan',
                'j.id_level',
                'l.nama_level',
                'l.nilai_public_holiday',
                'o.id_outlet',
                'o.nama_outlet',
                DB::raw('MIN(a.scan_date) as first_checkin')
            ])
            ->groupBy('u.id', 'u.nama_lengkap', 'u.id_jabatan', 'j.nama_jabatan', 'j.id_level', 'l.nama_level', 'l.nilai_public_holiday', 'o.id_outlet', 'o.nama_outlet')
            ->get();
    }

    /**
     * Process holiday attendance for a specific employee
     * 
     * @param object $employee
     * @param string $date
     * @return void
     */
    public function processEmployeeHolidayAttendance($employee, $date)
    {
        // Check if already processed for this date
        $alreadyProcessed = DB::table('holiday_attendance_compensations')
            ->where('user_id', $employee->user_id)
            ->where('holiday_date', $date)
            ->exists();

        if ($alreadyProcessed) {
            return; // Skip if already processed
        }

        // Check if already processed in Extra Off system
        $alreadyProcessedExtraOff = DB::table('extra_off_transactions')
            ->where('user_id', $employee->user_id)
            ->where('source_date', $date)
            ->whereIn('source_type', ['unscheduled_work', 'overtime_work'])
            ->where('transaction_type', 'earned')
            ->exists();

        if ($alreadyProcessedExtraOff) {
            \Log::info("Skipping holiday processing - already processed in Extra Off system", [
                'user_id' => $employee->user_id,
                'employee_name' => $employee->nama_lengkap,
                'date' => $date
            ]);
            return; // Skip if already processed in Extra Off system
        }

        $compensation = $this->getEmployeeCompensation($employee->id_jabatan);
        $this->giveHolidayBonus($employee->user_id, $date, $compensation['amount'], $employee->nama_lengkap);
    }

    /**
     * Besaran saldo PH (hari) untuk kerja di hari libur.
     * Selalu jalur saldo PH (bukan Extra Off). Extra Off adalah modul terpisah.
     *
     * @param int $jabatanId
     * @return array{type: string, amount: int, description: string}
     */
    public function getEmployeeCompensation($jabatanId)
    {
        $jabatan = Jabatan::with('level')->find($jabatanId);

        if (! $jabatan || ! $jabatan->level) {
            throw new \Exception("Job level not found for jabatan ID: {$jabatanId}");
        }

        $nilaiPublicHoliday = (int) $jabatan->level->nilai_public_holiday;
        // Master level > 0: pakai nilai tersebut (hari PH). Master = 0: minimal 1 hari PH per kejadian kerja libur.
        $amount = $nilaiPublicHoliday > 0 ? $nilaiPublicHoliday : 1;

        return [
            'type' => 'bonus',
            'amount' => $amount,
            'description' => 'Saldo PH (kerja di hari libur)',
        ];
    }

    /**
     * Legacy: mencatat kompensasi sebagai Extra Off di tabel yang sama.
     * Pemrosesan otomatis kerja-hari-libur tidak lagi memakai ini — pakai {@see giveHolidayBonus}.
     *
     * @deprecated Gunakan saldo PH ({@see giveHolidayBonus}) untuk kerja di hari libur.
     */
    public function giveExtraOffDay($userId, $holidayDate, $employeeName)
    {
        DB::table('holiday_attendance_compensations')->insert([
            'user_id' => $userId,
            'holiday_date' => $holidayDate,
            'compensation_type' => 'extra_off',
            'compensation_amount' => 1,
            'compensation_description' => 'Extra Off Day for working on holiday',
            'status' => 'pending', // Employee can use it anytime
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Log the action
        \Log::info("Extra off day given to employee", [
            'user_id' => $userId,
            'employee_name' => $employeeName,
            'holiday_date' => $holidayDate,
            'compensation_type' => 'extra_off'
        ]);
    }

    /**
     * Catat saldo PH (hari) untuk karyawan yang kerja di hari libur nasional/perusahaan.
     * Disimpan sebagai compensation_type "bonus" agar konsisten dengan pemakaian saldo PH di aplikasi.
     */
    public function giveHolidayBonus($userId, $holidayDate, $amount, $employeeName)
    {
        DB::table('holiday_attendance_compensations')->insert([
            'user_id' => $userId,
            'holiday_date' => $holidayDate,
            'compensation_type' => 'bonus',
            'compensation_amount' => $amount,
            'compensation_description' => 'Saldo PH untuk kerja di hari libur',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \Log::info('Saldo PH (hari libur) diberikan ke karyawan', [
            'user_id' => $userId,
            'employee_name' => $employeeName,
            'holiday_date' => $holidayDate,
            'compensation_type' => 'bonus',
            'days' => $amount,
        ]);
    }

    /**
     * Tambah saldo PH (hari) secara manual oleh admin (tanpa syarat tanggal ada di kalender libur).
     *
     * @return int inserted row id
     */
    public function injectManualPublicHolidayBalance(int $userId, float $days, string $referenceDate, ?string $notes, ?int $actorUserId): int
    {
        if ($days <= 0) {
            throw new \InvalidArgumentException('Jumlah hari harus lebih dari 0');
        }

        $description = '[Manual inject saldo PH]';
        if ($actorUserId) {
            $description .= ' user_id admin: '.$actorUserId;
        }
        if ($notes !== null && $notes !== '') {
            $description .= ' — '.$notes;
        }

        $id = (int) DB::table('holiday_attendance_compensations')->insertGetId([
            'user_id' => $userId,
            'holiday_date' => $referenceDate,
            'compensation_type' => 'bonus',
            'compensation_amount' => $days,
            'compensation_description' => $description,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \Log::info('Manual inject saldo PH', [
            'compensation_id' => $id,
            'user_id' => $userId,
            'days' => $days,
            'reference_date' => $referenceDate,
            'actor_user_id' => $actorUserId,
        ]);

        return $id;
    }

    /**
     * Kompensasi PH (extra_off + bonus) yang masih punya sisa saldo — sama logika dengan API my-extra-off-days.
     * Tidak difilter per bulan; dipakai modal izin & ringkasan saldo tersedia.
     */
    public function getAvailablePublicHolidayExtraOffRecords(int $userId): Collection
    {
        $rows = HolidayAttendanceCompensation::where('user_id', $userId)
            ->whereIn('compensation_type', ['extra_off', 'bonus'])
            ->where('status', 'approved')
            ->with('holiday')
            ->orderBy('holiday_date', 'desc')
            ->get();

        return $rows->map(function ($day) {
            $availableAmount = $day->compensation_amount - ($day->used_amount ?? 0);

            return [
                ...$day->toArray(),
                'available_amount' => max(0, $availableAmount),
                'used_amount' => $day->used_amount ?? 0,
            ];
        })->filter(function ($day) {
            return $day['available_amount'] > 0;
        })->values();
    }

    public function getTotalAvailablePublicHolidayExtraOffDays(int $userId): float
    {
        return (float) $this->getAvailablePublicHolidayExtraOffRecords($userId)
            ->sum(fn ($row) => (float) $row['available_amount']);
    }

    /**
     * Get employee's holiday attendance history
     * 
     * @param int $userId
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    public function getEmployeeHolidayHistory($userId, $limit = 10)
    {
        return DB::table('holiday_attendance_compensations as hac')
            ->join('users as u', 'hac.user_id', '=', 'u.id')
            ->leftJoin('tbl_kalender_perusahaan as kp', 'hac.holiday_date', '=', 'kp.tgl_libur')
            ->where('hac.user_id', $userId)
            ->select([
                'hac.*',
                'u.nama_lengkap',
                'kp.keterangan as holiday_name',
            ])
            ->orderBy('hac.holiday_date', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get all holiday attendance compensations for admin view
     *
     * @param  array  $filters
     * @return \Illuminate\Support\Collection
     */
    public function getAllHolidayCompensations($filters = [])
    {
        $query = DB::table('holiday_attendance_compensations as hac')
            ->join('users as u', 'hac.user_id', '=', 'u.id')
            ->leftJoin('tbl_kalender_perusahaan as kp', 'hac.holiday_date', '=', 'kp.tgl_libur')
            ->join('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->join('tbl_data_level as l', 'j.id_level', '=', 'l.id')
            ->leftJoin('tbl_data_divisi as d', 'u.division_id', '=', 'd.id')
            ->leftJoin('tbl_data_outlet as o', 'u.id_outlet', '=', 'o.id_outlet')
            ->select([
                'hac.*',
                'u.nama_lengkap',
                'u.nik',
                'kp.keterangan as holiday_name',
                'j.nama_jabatan',
                'l.nama_level',
                'd.nama_divisi',
                'o.nama_outlet'
            ]);

        // Apply filters
        if (!empty($filters['start_date'])) {
            $query->where('hac.holiday_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('hac.holiday_date', '<=', $filters['end_date']);
        }

        if (!empty($filters['compensation_type'])) {
            $query->where('hac.compensation_type', $filters['compensation_type']);
        }

        if (!empty($filters['status'])) {
            $query->where('hac.status', $filters['status']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('hac.user_id', $filters['user_id']);
        }

        return $query->orderBy('hac.holiday_date', 'desc')
            ->orderBy('u.nama_lengkap')
            ->get();
    }

    /**
     * Use extra off day (for employee)
     * 
     * @param int $userId
     * @param int $compensationId
     * @param string $useDate
     * @return bool
     */
    public function useExtraOffDay($userId, $compensationId, $useDate)
    {
        $compensation = DB::table('holiday_attendance_compensations')
            ->where('id', $compensationId)
            ->where('user_id', $userId)
            ->where('compensation_type', 'extra_off')
            ->where('status', 'pending')
            ->first();

        if (!$compensation) {
            throw new \Exception('Extra off day not found or already used');
        }

        // Update status to used
        DB::table('holiday_attendance_compensations')
            ->where('id', $compensationId)
            ->update([
                'status' => 'used',
                'used_date' => $useDate,
                'updated_at' => now()
            ]);

        \Log::info("Extra off day used by employee", [
            'user_id' => $userId,
            'compensation_id' => $compensationId,
            'use_date' => $useDate
        ]);

        return true;
    }

    /**
     * Use partial Public Holiday balance
     * 
     * @param int $userId
     * @param int $compensationId
     * @param float $useAmount
     * @param string $useDate
     * @return bool
     */
    public function usePartialPublicHolidayBalance($userId, $compensationId, $useAmount, $useDate)
    {
        $compensation = DB::table('holiday_attendance_compensations')
            ->where('id', $compensationId)
            ->where('user_id', $userId)
            ->where('compensation_type', 'bonus')
            ->where('status', 'approved')
            ->first();

        if (!$compensation) {
            throw new \Exception('Public Holiday balance not found or not approved');
        }

        $currentUsedAmount = $compensation->used_amount ?? 0;
        $availableAmount = $compensation->compensation_amount - $currentUsedAmount;

        if ($useAmount > $availableAmount) {
            throw new \Exception('Insufficient Public Holiday balance. Available: ' . $availableAmount . ' days');
        }

        // Update used_amount
        $newUsedAmount = $currentUsedAmount + $useAmount;
        
        DB::table('holiday_attendance_compensations')
            ->where('id', $compensationId)
            ->update([
                'used_amount' => $newUsedAmount,
                'used_date' => $useDate,
                'updated_at' => now()
            ]);

        \Log::info("Partial Public Holiday balance used by employee", [
            'user_id' => $userId,
            'compensation_id' => $compensationId,
            'use_amount' => $useAmount,
            'use_date' => $useDate,
            'remaining_amount' => $compensation->compensation_amount - $newUsedAmount
        ]);

        return true;
    }

    /**
     * Use Public Holiday balance with automatic record selection
     * This method will automatically select the best records to use based on strategy
     * 
     * @param int $userId
     * @param float $useAmount
     * @param string $useDate
     * @param string $strategy Strategy to use: 'fifo' (First In First Out) or 'lifo' (Last In First Out)
     * @return array
     */
    public function usePublicHolidayBalanceAuto($userId, $useAmount, $useDate, $strategy = 'fifo')
    {
        // Get all available Public Holiday balance records (both bonus and extra_off)
        $availableRecords = DB::table('holiday_attendance_compensations')
            ->where('user_id', $userId)
            ->whereIn('compensation_type', ['bonus', 'extra_off'])
            ->where('status', 'approved')
            ->orderBy($strategy === 'fifo' ? 'created_at' : 'created_at', $strategy === 'fifo' ? 'asc' : 'desc')
            ->get();

        $totalAvailable = 0;
        $recordsToUse = [];
        $remainingAmount = $useAmount;

        // Calculate total available balance
        foreach ($availableRecords as $record) {
            $usedAmount = $record->used_amount ?? 0;
            
            // For extra_off type, each record is either fully available or fully used
            if ($record->compensation_type === 'extra_off') {
                $availableAmount = ($record->used_date === null) ? 1 : 0; // 1 day if not used, 0 if used
            } else {
                // For bonus type, calculate based on compensation_amount and used_amount
                $availableAmount = $record->compensation_amount - $usedAmount;
            }
            
            if ($availableAmount > 0) {
                $totalAvailable += $availableAmount;
                
                if ($remainingAmount > 0) {
                    $useFromThisRecord = min($remainingAmount, $availableAmount);
                    $recordsToUse[] = [
                        'id' => $record->id,
                        'compensation_type' => $record->compensation_type,
                        'available_amount' => $availableAmount,
                        'use_amount' => $useFromThisRecord
                    ];
                    $remainingAmount -= $useFromThisRecord;
                }
            }
        }

        if ($totalAvailable < $useAmount) {
            throw new \Exception('Insufficient Public Holiday balance. Available: ' . $totalAvailable . ' days, Requested: ' . $useAmount . ' days');
        }

        // Update the selected records
        $usedRecords = [];
        foreach ($recordsToUse as $recordToUse) {
            $record = DB::table('holiday_attendance_compensations')
                ->where('id', $recordToUse['id'])
                ->first();

            if ($recordToUse['compensation_type'] === 'extra_off') {
                // For extra_off type, mark as used (set used_date and status)
                DB::table('holiday_attendance_compensations')
                    ->where('id', $recordToUse['id'])
                    ->update([
                        'status' => 'used',
                        'used_date' => $useDate,
                        'updated_at' => now()
                    ]);

                $usedRecords[] = [
                    'compensation_id' => $recordToUse['id'],
                    'compensation_type' => 'extra_off',
                    'used_amount' => $recordToUse['use_amount'],
                    'remaining_amount' => 0 // extra_off is fully used
                ];
            } else {
                // For bonus type, update used_amount
                $currentUsedAmount = $record->used_amount ?? 0;
                $newUsedAmount = $currentUsedAmount + $recordToUse['use_amount'];

                DB::table('holiday_attendance_compensations')
                    ->where('id', $recordToUse['id'])
                    ->update([
                        'used_amount' => $newUsedAmount,
                        'used_date' => $useDate,
                        'updated_at' => now()
                    ]);

                $usedRecords[] = [
                    'compensation_id' => $recordToUse['id'],
                    'compensation_type' => 'bonus',
                    'used_amount' => $recordToUse['use_amount'],
                    'remaining_amount' => $record->compensation_amount - $newUsedAmount
                ];
            }
        }

        \Log::info("Public Holiday balance used automatically", [
            'user_id' => $userId,
            'use_amount' => $useAmount,
            'use_date' => $useDate,
            'strategy' => $strategy,
            'used_records' => $usedRecords
        ]);

        return [
            'success' => true,
            'used_records' => $usedRecords,
            'total_used' => $useAmount
        ];
    }
}
