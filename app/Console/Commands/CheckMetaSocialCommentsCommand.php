<?php

namespace App\Console\Commands;

use App\Services\Meta\MetaSocialCommentsNotificationService;
use Illuminate\Console\Command;

class CheckMetaSocialCommentsCommand extends Command
{
    protected $signature = 'meta:check-social-comments';

    protected $description = 'Cek komentar IG/FB baru dan kirim notifikasi ke admin Tim Inbox Omnichannel';

    public function handle(MetaSocialCommentsNotificationService $service): int
    {
        $result = $service->checkAll();

        $this->info(sprintf(
            'Selesai: notified=%d posts=%d skip_seen=%d errors=%d',
            $result['notified'],
            $result['scanned_posts'],
            $result['skipped_seen'],
            $result['errors']
        ));

        if ($result['notified'] === 0 && $result['errors'] === 0) {
            $this->line('Tidak ada komentar baru (atau sudah pernah diproses).');
        }

        return self::SUCCESS;
    }
}
