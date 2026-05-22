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
        try {
            $result = $service->checkAll();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $adminCount = count(\App\Support\OmnichannelAuthorization::fullAccessUserIds());
        $this->line("Admin penerima notif (lihat semua inbox): {$adminCount}");

        $this->info(sprintf(
            'Selesai: notified=%d posts=%d skip_seen=%d errors=%d',
            $result['notified'],
            $result['scanned_posts'],
            $result['skipped_seen'],
            $result['errors']
        ));

        foreach ($result['error_details'] ?? [] as $detail) {
            $this->warn($detail);
        }

        if ($result['notified'] === 0 && $result['errors'] === 0) {
            $this->line('Tidak ada komentar baru (atau sudah pernah diproses).');
        }

        if ($result['errors'] > 0) {
            $this->line('Cek permission Meta (IG: instagram_business_manage_comments, FB: pages_read_engagement + pages_manage_engagement).');
        }

        return ($result['errors'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
