<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ExtraOffService;
use Carbon\Carbon;

class DetectExtraOff extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'extra-off:detect 
                            {--date= : Date to check (Y-m-d format, defaults to yesterday)}
                            {--force : Force detection even if already processed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect employees who worked without scheduled shift and give them extra off';

    protected $extraOffService;

    /**
     * Create a new command instance.
     */
    public function __construct(ExtraOffService $extraOffService)
    {
        parent::__construct();
        $this->extraOffService = $extraOffService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->option('date');
        $force = $this->option('force');

        // Validate date format if provided
        if ($date && !$this->isValidDate($date)) {
            $this->error('Invalid date format. Please use Y-m-d format (e.g., 2025-01-15)');
            return 1;
        }

        // Use provided date or default to yesterday
        $checkDate = $date ?: Carbon::yesterday()->format('Y-m-d');

        $this->info("Detecting extra off for date: {$checkDate}");

        try {
            $results = $this->extraOffService->detectUnscheduledWork($checkDate);

            $this->displayResults($results);

            // Log the results
            \Log::info('Extra off detection completed', [
                'date' => $checkDate,
                'results' => $results
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error('Error during detection: ' . $e->getMessage());
            \Log::error('Extra off detection failed', [
                'date' => $checkDate,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Display the results in a formatted table
     */
    private function displayResults($results)
    {
        $this->info("\n=== Extra Off Detection Results ===");
        $this->info("Date: {$results['date']}");
        $this->info("Detected: {$results['detected']} employees");
        $this->info("Processed: {$results['processed']} employees");

        if (!empty($results['errors'])) {
            $this->warn("\nErrors encountered:");
            foreach ($results['errors'] as $error) {
                if (isset($error['user_id'])) {
                    $this->warn("- User ID {$error['user_id']} ({$error['nama']}): {$error['error']}");
                } else {
                    $this->warn("- {$error['error']}");
                }
            }
        }

        if ($results['processed'] > 0) {
            $this->info("\n✅ Successfully processed {$results['processed']} employees");
        } else {
            $this->info("\nℹ️  No employees found who worked without scheduled shift");
        }
    }

    /**
     * Validate date format
     */
    private function isValidDate($date)
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
