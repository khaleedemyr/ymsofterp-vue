<?php

namespace App\Console\Commands;

use App\Services\Meta\MetaSocialContentSyncService;
use Illuminate\Console\Command;

class SyncMetaSocialContentCommand extends Command
{
    protected $signature = 'meta:sync-social-content {--limit=50 : Max posts per account}';

    protected $description = 'Sync konten & metrik performa Instagram + Facebook Page ke social_content_*';

    public function handle(MetaSocialContentSyncService $service): int
    {
        $limit = (int) $this->option('limit');

        try {
            $result = $service->syncAll($limit);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Selesai: synced=%d (ig=%d fb=%d) accounts=%d errors=%d',
            $result['synced'],
            $result['ig_synced'] ?? 0,
            $result['fb_synced'] ?? 0,
            $result['accounts'],
            $result['errors']
        ));

        foreach ($result['error_details'] ?? [] as $detail) {
            $this->warn($detail);
        }

        if ($result['accounts'] === 0) {
            $this->line('Tidak ada akun Meta (META_INSTAGRAM_LOGIN_TOKENS / META_PAGE_TOKENS).');
        }

        if ($result['errors'] > 0) {
            $this->line('Cek permission Meta (IG insights / pages_read_engagement). Likes & comments tetap tersimpan bila tersedia.');
        }

        return ($result['errors'] ?? 0) > 0 && ($result['synced'] ?? 0) === 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
