<?php

namespace App\Console\Commands;

use App\Services\Meta\MetaMessengerInboxSyncService;
use Illuminate\Console\Command;

class SyncMetaMessengerInboxCommand extends Command
{
    protected $signature = 'meta:sync-messenger-inbox
                            {--recent= : Hanya pesan dalam N menit terakhir (cepat, untuk cron/inbox)}';

    protected $description = 'Poll Facebook Page Conversations API dan impor DM Messenger ke omnichannel inbox';

    public function handle(MetaMessengerInboxSyncService $sync): int
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

        if ($result['accounts'] !== [] && isset($result['accounts'][0]['error']) && count($result['accounts']) === 1) {
            $this->error($result['accounts'][0]['error']);

            return self::FAILURE;
        }

        foreach ($result['accounts'] as $account) {
            if (! empty($account['skipped_invalid_token'])) {
                $this->warn(($account['page_id'] ?? '?').': dilewati (bukan Page token — cek META_PAGE_TOKENS)');

                continue;
            }

            if (isset($account['error'])) {
                $this->error(($account['page_id'] ?? '?').': '.$account['error']);

                continue;
            }

            $this->line(sprintf(
                '%s (%s): conv=%d checked=%d imported=%d skip_db=%d skip_out=%d skip_no_from=%d api_err=%d',
                $account['page_id'] ?? '?',
                $account['page_name'] ?? '-',
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
            if ($recentMinutes !== null) {
                $this->warn('Imported 0 dengan --recent: tidak ada DM baru dalam '.$recentMinutes.' menit, atau semua sudah ada di DB (lihat skip_db).');
                $this->warn('Impor riwayat: php artisan meta:sync-messenger-inbox -v');
            } else {
                $this->warn('Imported 0 — semua pesan mungkin sudah ada di DB (skip_db) atau tidak ada pesan inbound.');
            }
            $this->warn('Diagnosa: php artisan meta:debug-messenger-inbox');
        }

        return self::SUCCESS;
    }
}
