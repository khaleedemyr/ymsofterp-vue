<?php

namespace App\Console\Commands;

use App\Services\Meta\MetaWhatsAppClient;
use Illuminate\Console\Command;

class SubscribeMetaWhatsAppWabaCommand extends Command
{
    protected $signature = 'meta:whatsapp-waba-subscribe
                            {--subscribe : POST subscribe app ERP ke WABA (setelah Sleekflow dilepas)}
                            {--waba= : Override WABA ID}';

    protected $description = 'Cek / subscribe WABA WhatsApp ke app ERP (ganti Sleekflow)';

    public function handle(MetaWhatsAppClient $client): int
    {
        $wabaId = $this->option('waba') ?: config('services.meta.whatsapp_business_account_id');
        $phoneId = config('services.meta.whatsapp_phone_number_id');
        $webhook = url('/api/webhooks/meta/whatsapp');

        $this->info('WABA ID: '.($wabaId ?: '(kosong)'));
        $this->info('Phone Number ID: '.($phoneId ?: '(kosong)'));
        $this->info('Webhook ERP: '.$webhook);
        $this->line('');

        try {
            if ($this->option('subscribe')) {
                $this->warn('Subscribe app ERP ke WABA…');
                $result = $client->subscribeWabaToApp($wabaId ? (string) $wabaId : null);
                $this->info('Subscribe OK: '.json_encode($result, JSON_UNESCAPED_UNICODE));
            }

            $apps = $client->listSubscribedApps($wabaId ? (string) $wabaId : null);

            if ($apps === []) {
                $this->warn('subscribed_apps kosong — belum ada app atau token salah.');

                return self::FAILURE;
            }

            $this->info('subscribed_apps:');
            foreach ($apps as $row) {
                $meta = $row['whatsapp_business_api_data'] ?? $row;
                $name = $meta['name'] ?? '?';
                $id = $meta['id'] ?? '?';
                $this->line("  - {$name} (app id: {$id})");

                if ((string) $id === '812364635796464') {
                    $this->warn('    ↑ masih Sleekflow — lepas dulu di Sleekflow / Business Manager.');
                }
                if ((string) $id === '1302269045204850') {
                    $this->info('    ↑ ERP YMSoft — OK.');
                }
            }

            $this->line('');
            $this->line('Pastikan di Meta App Dashboard → WhatsApp → webhook:');
            $this->line('  Callback: '.$webhook);
            $this->line('  Field: messages (centang)');
            $this->line('Kirim tes WA ke nomor production, cek Omnichannel Inbox ERP.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
