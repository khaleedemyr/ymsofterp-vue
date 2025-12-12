<?php

namespace App\Console\Commands;

use App\Models\WebDeviceToken;
use App\Models\EmployeeDeviceToken;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupDeviceTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'device-tokens:cleanup {--days=30} {--limit=5}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup old and excess device tokens to prevent notification spam';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $limit = (int) $this->option('limit');

        $this->info("Starting device token cleanup...");
        $this->info("Deactivating tokens older than {$days} days and keeping max {$limit} tokens per user.");

        // Cleanup web device tokens
        $this->cleanupWebDeviceTokens($days, $limit);

        // Cleanup employee device tokens
        $this->cleanupEmployeeDeviceTokens($days, $limit);

        $this->info("Device token cleanup completed!");

        return 0;
    }

    /**
     * Cleanup web device tokens
     */
    protected function cleanupWebDeviceTokens($days, $limit)
    {
        $this->line("Cleaning up web device tokens...");

        // Deactivate old tokens (not used for > $days days)
        $oldTokens = WebDeviceToken::where('is_active', true)
            ->where(function($query) use ($days) {
                $query->whereNull('last_used_at')
                    ->orWhere('last_used_at', '<', now()->subDays($days));
            })
            ->where('created_at', '<', now()->subDays($days))
            ->get();

        $deactivatedOld = 0;
        foreach ($oldTokens as $token) {
            $token->is_active = false;
            $token->save();
            $deactivatedOld++;
        }

        $this->info("  - Deactivated {$deactivatedOld} old web device tokens (not used for > {$days} days)");

        // Limit tokens per user - keep only $limit most recent
        $users = WebDeviceToken::where('is_active', true)
            ->select('user_id')
            ->distinct()
            ->pluck('user_id');

        $deactivatedExcess = 0;
        foreach ($users as $userId) {
            $activeTokens = WebDeviceToken::where('user_id', $userId)
                ->where('is_active', true)
                ->orderBy('last_used_at', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            if ($activeTokens->count() > $limit) {
                $tokensToDeactivate = $activeTokens->slice($limit);
                foreach ($tokensToDeactivate as $token) {
                    $token->is_active = false;
                    $token->save();
                    $deactivatedExcess++;
                }
            }
        }

        $this->info("  - Deactivated {$deactivatedExcess} excess web device tokens (keeping max {$limit} per user)");

        Log::info('Device token cleanup: Web tokens', [
            'deactivated_old' => $deactivatedOld,
            'deactivated_excess' => $deactivatedExcess,
        ]);
    }

    /**
     * Cleanup employee device tokens
     */
    protected function cleanupEmployeeDeviceTokens($days, $limit)
    {
        $this->line("Cleaning up employee device tokens...");

        // Deactivate old tokens (not used for > $days days)
        $oldTokens = EmployeeDeviceToken::where('is_active', true)
            ->where(function($query) use ($days) {
                $query->whereNull('last_used_at')
                    ->orWhere('last_used_at', '<', now()->subDays($days));
            })
            ->where('created_at', '<', now()->subDays($days))
            ->get();

        $deactivatedOld = 0;
        foreach ($oldTokens as $token) {
            $token->is_active = false;
            $token->save();
            $deactivatedOld++;
        }

        $this->info("  - Deactivated {$deactivatedOld} old employee device tokens (not used for > {$days} days)");

        // Limit tokens per user - keep only $limit most recent
        $users = EmployeeDeviceToken::where('is_active', true)
            ->select('user_id')
            ->distinct()
            ->pluck('user_id');

        $deactivatedExcess = 0;
        foreach ($users as $userId) {
            $activeTokens = EmployeeDeviceToken::where('user_id', $userId)
                ->where('is_active', true)
                ->orderBy('last_used_at', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            if ($activeTokens->count() > $limit) {
                $tokensToDeactivate = $activeTokens->slice($limit);
                foreach ($tokensToDeactivate as $token) {
                    $token->is_active = false;
                    $token->save();
                    $deactivatedExcess++;
                }
            }
        }

        $this->info("  - Deactivated {$deactivatedExcess} excess employee device tokens (keeping max {$limit} per user)");

        Log::info('Device token cleanup: Employee tokens', [
            'deactivated_old' => $deactivatedOld,
            'deactivated_excess' => $deactivatedExcess,
        ]);
    }
}

