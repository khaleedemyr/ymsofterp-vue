<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\KalenderPerusahaan;
use App\Models\Jabatan;
use App\Models\DataLevel;

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

                    // Check what type of compensation was given
                    $compensation = $this->getEmployeeCompensation($employee->id_jabatan);
                    if ($compensation['type'] === 'extra_off') {
                        $results['extra_off_given']++;
                    } else {
                        $results['bonus_paid']++;
                    }

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
            ->where(DB::raw('DATE(a.scan_date)'), $date)
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

        $compensation = $this->getEmployeeCompensation($employee->id_jabatan);

        if ($compensation['type'] === 'extra_off') {
            $this->giveExtraOffDay($employee->user_id, $date, $employee->nama_lengkap);
        } else {
            $this->giveHolidayBonus($employee->user_id, $date, $compensation['amount'], $employee->nama_lengkap);
        }
    }

    /**
     * Get compensation type and amount for employee based on their job level
     * 
     * @param int $jabatanId
     * @return array
     */
    public function getEmployeeCompensation($jabatanId)
    {
        $jabatan = Jabatan::with('level')->find($jabatanId);
        
        if (!$jabatan || !$jabatan->level) {
            throw new \Exception("Job level not found for jabatan ID: {$jabatanId}");
        }

        $nilaiPublicHoliday = $jabatan->level->nilai_public_holiday;

        if ($nilaiPublicHoliday == 0) {
            return [
                'type' => 'extra_off',
                'amount' => 1, // 1 extra off day
                'description' => 'Extra Off Day'
            ];
        } else {
            return [
                'type' => 'bonus',
                'amount' => $nilaiPublicHoliday,
                'description' => 'Holiday Bonus'
            ];
        }
    }

    /**
     * Give extra off day to employee
     * 
     * @param int $userId
     * @param string $holidayDate
     * @param string $employeeName
     * @return void
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
     * Give holiday bonus to employee
     * 
     * @param int $userId
     * @param string $holidayDate
     * @param int $amount
     * @param string $employeeName
     * @return void
     */
    public function giveHolidayBonus($userId, $holidayDate, $amount, $employeeName)
    {
        DB::table('holiday_attendance_compensations')->insert([
            'user_id' => $userId,
            'holiday_date' => $holidayDate,
            'compensation_type' => 'bonus',
            'compensation_amount' => $amount,
            'compensation_description' => 'Holiday Bonus for working on holiday',
            'status' => 'approved', // Bonus is automatically approved
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Log the action
        \Log::info("Holiday bonus given to employee", [
            'user_id' => $userId,
            'employee_name' => $employeeName,
            'holiday_date' => $holidayDate,
            'compensation_type' => 'bonus',
            'amount' => $amount
        ]);
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
            ->join('tbl_kalender_perusahaan as kp', 'hac.holiday_date', '=', 'kp.tgl_libur')
            ->where('hac.user_id', $userId)
            ->select([
                'hac.*',
                'u.nama_lengkap',
                'kp.keterangan as holiday_name'
            ])
            ->orderBy('hac.holiday_date', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get all holiday attendance compensations for admin view
     * 
     * @param array $filters
     * @return \Illuminate\Support\Collection
     */
    public function getAllHolidayCompensations($filters = [])
    {
        $query = DB::table('holiday_attendance_compensations as hac')
            ->join('users as u', 'hac.user_id', '=', 'u.id')
            ->join('tbl_kalender_perusahaan as kp', 'hac.holiday_date', '=', 'kp.tgl_libur')
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
}
