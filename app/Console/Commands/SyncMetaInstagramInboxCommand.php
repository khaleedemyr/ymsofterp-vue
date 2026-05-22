<?php

namespace App\Console\Commands;

use App\Services\Meta\MetaInstagramInboxSyncService;
use Illuminate\Console\Command;

class SyncMetaInstagramInboxCommand extends Command
{
    protected $signature = 'meta:sync-instagram-inbox';

    protected $description = 'Poll Instagram Login API conversations and import new DMs into omnichannel inbox';

    public function handle(MetaInstagramInboxSyncService $sync): int
    {
        // Pakai -v / --verbose bawaan Laravel (jangan definisi option verbose sendiri)
        $verbose = $this->output->isVerbose();
        $result = $sync->syncAll($verbose);

        if ($result['accounts'] !== [] && isset($result['accounts'][0]['error']) && count($result['accounts']) === 1) {
            $this->error($result['accounts'][0]['error']);

            return self::FAILURE;
        }

        $this->info("Imported {$result['imported']} new Instagram message(s).");

        if ($verbose) {
            foreach ($result['accounts'] as $account) {
                if (isset($account['error'])) {
                    $this->error(($account['ig_id'] ?? '?').': '.$account['error']);

                    continue;
                }

                $this->line(sprintf(
                    '%s (@%s): conversations=%d, checked=%d, imported=%d, skipped_db=%d, skipped_outbound=%d, api_errors=%d',
                    $account['ig_id'],
                    $account['username'] ?? '-',
                    $account['conversations'] ?? 0,
                    $account['messages_checked'] ?? 0,
                    $account['imported'] ?? 0,
                    $account['skipped_existing'] ?? 0,
                    $account['skipped_outbound'] ?? 0,
                    $account['api_errors'] ?? 0,
                ));
            }
        }

        return self::SUCCESS;
    }
}
