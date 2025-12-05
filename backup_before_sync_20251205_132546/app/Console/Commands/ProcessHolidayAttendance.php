<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\HolidayAttendanceService;
use Carbon\Carbon;

class ProcessHolidayAttendance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:process-holiday {date?} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process holiday attendance for a specific date or yesterday';

    protected $holidayAttendanceService;

    /**
     * Create a new command instance.
     */
    public function __construct(HolidayAttendanceService $holidayAttendanceService)
    {
        parent::__construct();
        $this->holidayAttendanceService = $holidayAttendanceService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->argument('date');
        $force = $this->option('force');

        // If no date provided, use yesterday
        if (!$date) {
            $date = Carbon::yesterday()->format('Y-m-d');
        }

        // Validate date format
        try {
            $date = Carbon::createFromFormat('Y-m-d', $date)->format('Y-m-d');
        } catch (\Exception $e) {
            $this->error('Invalid date format. Please use Y-m-d format (e.g., 2024-01-15)');
            return 1;
        }

        $this->info("Processing holiday attendance for date: {$date}");

        // Check if it's a holiday
        if (!$this->holidayAttendanceService->isHoliday($date)) {
            $this->warn("Date {$date} is not a holiday. Skipping...");
            return 0;
        }

        $this->info("Date {$date} is a holiday. Processing attendance...");

        // Get employees who worked on this holiday
        $employees = $this->holidayAttendanceService->getEmployeesWhoWorkedOnHoliday($date);

        if ($employees->isEmpty()) {
            $this->info("No employees worked on holiday {$date}");
            return 0;
        }

        $this->info("Found {$employees->count()} employees who worked on holiday {$date}");

        // Show employees who will be processed
        $this->table(
            ['Name', 'Position', 'Level', 'Compensation Type', 'Amount'],
            $employees->map(function ($employee) {
                $compensation = $this->holidayAttendanceService->getEmployeeCompensation($employee->id_jabatan);
                return [
                    $employee->nama_lengkap,
                    $employee->nama_jabatan,
                    $employee->nama_level,
                    $compensation['type'] === 'extra_off' ? 'Extra Off Day' : 'Holiday Bonus',
                    $compensation['type'] === 'extra_off' ? '1 day' : 'Rp ' . number_format($compensation['amount'])
                ];
            })
        );

        if (!$force && !$this->confirm('Do you want to process these compensations?')) {
            $this->info('Processing cancelled.');
            return 0;
        }

        // Process holiday attendance
        $results = $this->holidayAttendanceService->processHolidayAttendance($date);

        // Display results
        $this->info("Processing completed!");
        $this->info("Total processed: {$results['processed']}");
        $this->info("Extra off days given: {$results['extra_off_given']}");
        $this->info("Bonuses paid: {$results['bonus_paid']}");

        if (!empty($results['errors'])) {
            $this->error("Errors encountered:");
            foreach ($results['errors'] as $error) {
                if (isset($error['general_error'])) {
                    $this->error("- {$error['general_error']}");
                } else {
                    $this->error("- {$error['nama']} (ID: {$error['user_id']}): {$error['error']}");
                }
            }
        }

        return 0;
    }
}
