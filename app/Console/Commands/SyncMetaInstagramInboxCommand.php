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
        $count = $sync->syncAll();
        $this->info("Imported {$count} new Instagram message(s).");

        return self::SUCCESS;
    }
}
