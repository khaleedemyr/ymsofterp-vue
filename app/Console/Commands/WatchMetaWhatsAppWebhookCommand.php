<?php

namespace App\Console\Commands;

use App\Services\Meta\MetaWhatsAppWebhookGuardService;
use Illuminate\Console\Command;

class WatchMetaWhatsAppWebhookCommand extends Command
{
    protected $signature = 'meta:watch-whatsapp-webhook
                            {--dry-run : Cek saja, tanpa auto-remediate / notifikasi}';

    protected $description = 'Pantau webhook WA: deteksi override asing, auto-hapus, notifikasi admin';

    public function handle(MetaWhatsAppWebhookGuardService $guard): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $guard->checkAndRemediate(autoRemediate: ! $dryRun);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('Expected callback: '.$result['expected_callback']);
        $this->line('webhook_configuration: '.json_encode(
            $result['webhook_configuration'],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ));
        $this->line('');

        if ($result['ok'] && $result['anomalies'] === [] && ! $result['remediated']) {
            $this->info('OK — webhook WA aman (tidak ada override asing).');

            return self::SUCCESS;
        }

        foreach ($result['anomalies'] as $line) {
            $this->warn('• '.$line);
        }

        if ($result['remediated']) {
            $this->info('Auto-remediate: subscribe WABA dijalankan.');
        }

        if ($result['notified']) {
            $this->info('Notifikasi alert dikirim ke admin (META_WHATSAPP_WEBHOOK_ALERT_USER_IDS).');
        } elseif ($dryRun) {
            $this->line('Dry-run: notifikasi & remediate dilewati.');
        } elseif ($result['anomalies'] !== [] || $result['remediated']) {
            $this->line('Notifikasi tidak dikirim (dedupe cache atau user_id kosong).');
        }

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
