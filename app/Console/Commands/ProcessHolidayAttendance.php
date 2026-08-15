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
    protected $signature = 'attendance:process-holiday
                            {date? : Single date Y-m-d}
                            {--from= : Start date Y-m-d (inclusive)}
                            {--to= : End date Y-m-d (inclusive)}
                            {--days=14 : Lookback days from today when no date/from given}
                            {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process holiday attendance for a date or recent holiday dates';

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
        $force = $this->option('force');

        try {
            [$from, $to] = $this->resolveDateRange();
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }

        $holidayDates = $this->holidayAttendanceService->getHolidayDatesBetween($from, $to);

        if (empty($holidayDates)) {
            $this->info("No holidays between {$from} and {$to}. Skipping...");
            return 0;
        }

        $this->info('Holidays to process: '.implode(', ', $holidayDates));

        if (!$force && $this->input->isInteractive() && ! $this->confirm('Process holiday attendance for these dates?')) {
            $this->info('Processing cancelled.');
            return 0;
        }

        $results = $this->holidayAttendanceService->processHolidayAttendanceRange($from, $to);

        $this->info('Processing completed!');
        $this->info('Dates: '.implode(', ', $results['dates']));
        $this->info("Total processed: {$results['processed']}");
        $this->info("Saldo PH dicatat (kredit hari): {$results['bonus_paid']}");

        if (!empty($results['errors'])) {
            $this->error('Errors encountered:');
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

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveDateRange(): array
    {
        $single = $this->argument('date');
        $from = $this->option('from');
        $to = $this->option('to');
        $days = max(0, (int) $this->option('days'));

        if ($single) {
            $date = $this->parseDate($single, 'date');
            return [$date, $date];
        }

        if ($from || $to) {
            $fromDate = $this->parseDate($from ?: Carbon::today()->subDays($days)->format('Y-m-d'), 'from');
            $toDate = $this->parseDate($to ?: Carbon::today()->format('Y-m-d'), 'to');
            return [$fromDate, $toDate];
        }

        return [
            Carbon::today()->subDays($days)->format('Y-m-d'),
            Carbon::today()->format('Y-m-d'),
        ];
    }

    private function parseDate($value, string $label): string
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $value)->format('Y-m-d');
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Invalid {$label} format. Please use Y-m-d (e.g., 2024-01-15)");
        }
    }
}
