<?php

namespace App\Console\Commands;

use App\Services\Meta\MetaInstagramInboxSyncService;
use Illuminate\Console\Command;

class SyncMetaInstagramInboxCommand extends Command
{
    protected $signature = 'meta:sync-instagram-inbox
                            {--recent= : Hanya pesan dalam N menit terakhir (cepat, untuk cron/inbox)}';

    protected $description = 'Poll Instagram Login API conversations and import new DMs into omnichannel inbox';

    public function handle(MetaInstagramInboxSyncService $sync): int
    {
        $verbose = $this->output->isVerbose();
        $recentOpt = $this->option('recent');
        $recentMinutes = $recentOpt !== null && $recentOpt !== ''
            ? max(1, (int) $recentOpt)
            : null;

        if ($recentMinutes !== null) {
            $this->line("Mode recent: {$recentMinutes} menit terakhir");
        }

        $result = $sync->syncAll($verbose, $recentMinutes);

        if ($result['accounts'] !== [] && isset($result['accounts'][0]['skipped']) && ($result['accounts'][0]['skipped'] ?? '') === 'rate_limit_backoff') {
            $this->warn('Skipped: Meta Instagram rate limit backoff aktif.');
            $this->warn('Jalankan: php artisan meta:debug-instagram-inbox --clear-rate-limit');

            return self::SUCCESS;
        }

        if ($result['accounts'] !== [] && isset($result['accounts'][0]['error']) && count($result['accounts']) === 1) {
            $this->error($result['accounts'][0]['error']);

            return self::FAILURE;
        }

        foreach ($result['accounts'] as $account) {
            if (isset($account['error'])) {
                $this->error(($account['ig_id'] ?? '?').': '.$account['error']);

                continue;
            }

            $this->line(sprintf(
                '%s (@%s): conv=%d checked=%d imported=%d skip_db=%d skip_out=%d skip_no_from=%d api_err=%d',
                $account['ig_id'] ?? '?',
                $account['username'] ?? '-',
                $account['conversations'] ?? 0,
                $account['messages_checked'] ?? 0,
                $account['imported'] ?? 0,
                $account['skipped_existing'] ?? 0,
                $account['skipped_outbound'] ?? 0,
                $account['skipped_no_sender'] ?? 0,
                $account['api_errors'] ?? 0,
            ));
        }

        $this->info('Total imported: '.($result['imported'] ?? 0));

        if (($result['imported'] ?? 0) === 0) {
            $this->warn('Imported 0 — cek skip_out (DM dari akun bisnis?) / api_err / skip_no_from.');
            $this->warn('Diagnosa: php artisan meta:debug-instagram-inbox');
        }

        return self::SUCCESS;
    }
}
