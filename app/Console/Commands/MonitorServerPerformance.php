<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonitorServerPerformance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:server-performance {--interval=5 : Interval in seconds} {--duration=60 : Duration in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor server performance in real-time (MySQL queries, processes, etc)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $interval = (int) $this->option('interval');
        $duration = (int) $this->option('duration');
        $endTime = time() + $duration;
        $iteration = 0;

        $this->info("Starting server performance monitoring...");
        $this->info("Interval: {$interval} seconds");
        $this->info("Duration: {$duration} seconds");
        $this->info("Press Ctrl+C to stop early");
        $this->newLine();

        while (time() < $endTime) {
            $iteration++;
            $this->line("=== Iteration #{$iteration} - " . date('Y-m-d H:i:s') . " ===");

            // 1. Check MySQL processes
            $this->checkMySQLProcesses();

            // 2. Check slow queries (if available)
            $this->checkSlowQueries();

            // 3. Check MySQL status
            $this->checkMySQLStatus();

            // 4. Check PHP processes
            $this->checkPHPProcesses();

            // 5. Check queue workers
            $this->checkQueueWorkers();

            $this->newLine();

            if (time() < $endTime) {
                sleep($interval);
            }
        }

        $this->info("Monitoring completed!");
    }

    protected function checkMySQLProcesses()
    {
        try {
            $processes = DB::select("
                SELECT 
                    id,
                    user,
                    host,
                    db,
                    command,
                    time,
                    state,
                    LEFT(info, 100) as query_preview
                FROM information_schema.processlist
                WHERE command != 'Sleep'
                ORDER BY time DESC
                LIMIT 10
            ");

            if (count($processes) > 0) {
                $this->warn("Active MySQL Processes:");
                $this->table(
                    ['ID', 'User', 'DB', 'Command', 'Time (s)', 'State', 'Query Preview'],
                    array_map(function ($p) {
                        return [
                            $p->id,
                            $p->user,
                            $p->db ?? 'N/A',
                            $p->command,
                            $p->time,
                            $p->state ?? 'N/A',
                            $p->query_preview ?? 'N/A'
                        ];
                    }, $processes)
                );

                // Check for long-running queries
                $longRunning = array_filter($processes, function ($p) {
                    return $p->time > 5;
                });

                if (count($longRunning) > 0) {
                    $this->error("⚠️  Found " . count($longRunning) . " long-running queries (>5s)!");
                }
            } else {
                $this->info("✓ No active MySQL processes");
            }
        } catch (\Exception $e) {
            $this->error("Error checking MySQL processes: " . $e->getMessage());
        }
    }

    protected function checkSlowQueries()
    {
        try {
            // Check if slow_log table exists and has data
            $count = DB::select("SELECT COUNT(*) as count FROM mysql.slow_log");
            $totalSlowQueries = $count[0]->count ?? 0;

            if ($totalSlowQueries > 0) {
                // Get recent slow queries
                $recentSlowQueries = DB::select("
                    SELECT 
                        sql_text,
                        query_time,
                        lock_time,
                        rows_examined,
                        rows_sent,
                        created_at
                    FROM mysql.slow_log 
                    WHERE sql_text NOT LIKE '%slow_log%'
                      AND sql_text NOT LIKE '%EXPLAIN%'
                    ORDER BY created_at DESC
                    LIMIT 5
                ");

                if (count($recentSlowQueries) > 0) {
                    $this->warn("Recent Slow Queries (Total: {$totalSlowQueries}):");
                    foreach ($recentSlowQueries as $query) {
                        $queryPreview = substr($query->sql_text, 0, 100) . '...';
                        $this->line("  Time: {$query->query_time}s | Rows: {$query->rows_examined} | {$queryPreview}");
                    }
                }
            } else {
                $this->info("✓ No slow queries in log (slow query log might be disabled)");
            }
        } catch (\Exception $e) {
            $this->warn("Could not check slow queries: " . $e->getMessage());
        }
    }

    protected function checkMySQLStatus()
    {
        try {
            $status = DB::select("
                SHOW STATUS WHERE Variable_name IN (
                    'Threads_connected',
                    'Threads_running',
                    'Slow_queries',
                    'Questions',
                    'Uptime'
                )
            ");

            $statusMap = [];
            foreach ($status as $s) {
                $statusMap[$s->Variable_name] = $s->Value;
            }

            $this->info("MySQL Status:");
            $this->line("  Threads Connected: " . ($statusMap['Threads_connected'] ?? 'N/A'));
            $this->line("  Threads Running: " . ($statusMap['Threads_running'] ?? 'N/A'));
            $this->line("  Slow Queries: " . ($statusMap['Slow_queries'] ?? 'N/A'));
            $this->line("  Total Questions: " . ($statusMap['Questions'] ?? 'N/A'));
            $this->line("  Uptime: " . ($statusMap['Uptime'] ?? 'N/A') . " seconds");

            // Warning if too many connections
            if (isset($statusMap['Threads_connected']) && $statusMap['Threads_connected'] > 50) {
                $this->warn("⚠️  High number of connections: " . $statusMap['Threads_connected']);
            }

            // Warning if many running threads
            if (isset($statusMap['Threads_running']) && $statusMap['Threads_running'] > 10) {
                $this->warn("⚠️  High number of running threads: " . $statusMap['Threads_running']);
            }
        } catch (\Exception $e) {
            $this->error("Error checking MySQL status: " . $e->getMessage());
        }
    }

    protected function checkPHPProcesses()
    {
        // This is a placeholder - actual implementation depends on system
        $this->info("PHP Processes: (Run 'ps aux | grep php-fpm' manually for details)");
    }

    protected function checkQueueWorkers()
    {
        // Check if queue workers are running
        $this->info("Queue Workers: (Run 'ps aux | grep queue:work' manually for details)");
    }
}
