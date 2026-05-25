<?php

namespace App\Console\Commands;

use App\Models\OmniConversation;
use App\Models\OmniMessage;
use App\Services\Meta\MetaWhatsAppClient;
use App\Support\MetaWhatsAppWebhookArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class DebugMetaWhatsAppWebhookCommand extends Command
{
    protected $signature = 'meta:debug-whatsapp-webhook
                            {--probe : Uji GET verify webhook ke APP_URL (dari server)}';

    protected $description = 'Diagnosa webhook WhatsApp: config, token, arsip, trace log, subscribed_apps';

    public function handle(MetaWhatsAppClient $client): int
    {
        $webhookUrl = url('/api/webhooks/meta/whatsapp');
        $phoneId = config('services.meta.whatsapp_phone_number_id');
        $wabaId = config('services.meta.whatsapp_business_account_id');
        $appSecret = config('services.meta.app_secret');
        $verifyToken = config('services.meta.webhook_verify_token');
        $skipSig = config('services.meta.webhook_skip_signature_verify');
        $appUrl = config('app.url');
        $archiveEnabled = MetaWhatsAppWebhookArchive::isEnabled();
        $archiveDir = MetaWhatsAppWebhookArchive::directory();

        $this->info('=== Webhook WhatsApp ERP ===');
        $this->line('APP_URL: '.($appUrl ?: '(kosong)'));
        if ($appUrl && ! str_starts_with($appUrl, 'https://')) {
            $this->warn('  APP_URL harus https:// — Meta menolak callback http.');
        }
        $this->line('Webhook URL (wajib sama di Meta App Dashboard):');
        $this->line('  '.$webhookUrl);
        if (! str_contains($webhookUrl, '/api/webhooks/meta/whatsapp')) {
            $this->warn('  URL harus mengandung /api/webhooks/meta/whatsapp');
        }
        $this->line('Phone Number ID: '.($phoneId ?: '(kosong)'));
        $this->line('WABA ID: '.($wabaId ?: '(kosong)'));
        $this->line('META_APP_SECRET: '.($appSecret !== '' && $appSecret !== null ? 'terisi' : 'KOSONG (signature tidak dicek)'));
        $this->line('META_WEBHOOK_VERIFY_TOKEN: '.($verifyToken !== '' && $verifyToken !== null ? 'terisi' : 'KOSONG'));
        $this->line('META_WEBHOOK_SKIP_SIGNATURE_VERIFY: '.($skipSig ? 'true' : 'false'));
        $this->line('META_WHATSAPP_WEBHOOK_ARCHIVE: '.($archiveEnabled ? 'aktif' : 'NONAKTIF — tidak ada file arsip'));
        $this->line('');

        $this->info('Arsip webhook: '.$archiveDir);
        if (! File::isDirectory($archiveDir)) {
            $this->warn('  Folder belum ada (normal jika belum pernah POST).');
        } elseif (! is_writable($archiveDir)) {
            $this->error('  Folder tidak writable — chmod storage/app/meta-webhook-archive');
        } else {
            $pending = count(MetaWhatsAppWebhookArchive::pendingFiles());
            $allJson = count(glob($archiveDir.DIRECTORY_SEPARATOR.'*.json') ?: []);
            $this->line("  Pending replay: {$pending}, total file .json: {$allJson}");
            if ($pending === 0 && $allJson === 0) {
                $this->warn('  Arsip kosong = Meta belum POST ke server ATAU webhook callback salah.');
            }
        }
        $this->line('');

        if ($phoneId && config('services.meta.whatsapp_access_token')) {
            try {
                $phone = $client->getPhoneNumberDetails((string) $phoneId);
                $this->info('Nomor WA (Graph API):');
                $this->line('  display: '.($phone['display_phone_number'] ?? '?'));
                $this->line('  verified_name: '.($phone['verified_name'] ?? '?'));
                $this->line('  quality: '.($phone['quality_rating'] ?? '?'));
            } catch (\Throwable $e) {
                $this->error('Token / Phone Number ID gagal: '.$e->getMessage());
                $this->warn('  Perbarui META_WHATSAPP_ACCESS_TOKEN (System User token app ERP, bukan Sleekflow).');
            }
            $this->line('');
        } else {
            $this->warn('META_WHATSAPP_ACCESS_TOKEN atau PHONE_NUMBER_ID kosong — kirim WA dari ERP juga akan gagal.');
            $this->line('');
        }

        if ($wabaId && config('services.meta.whatsapp_access_token')) {
            try {
                $apps = $client->listSubscribedApps($wabaId ? (string) $wabaId : null);
                $this->info('subscribed_apps (WABA):');
                $hasErp = false;
                foreach ($apps as $row) {
                    $meta = $row['whatsapp_business_api_data'] ?? $row;
                    $name = $meta['name'] ?? '?';
                    $id = (string) ($meta['id'] ?? '?');
                    $this->line("  - {$name} (app id: {$id})");
                    if ($id === '1302269045204850') {
                        $hasErp = true;
                    }
                    if ($id === '812364635796464') {
                        $this->warn('    ↑ Sleekflow masih subscribe — lepas di Sleekflow dulu.');
                    }
                }
                if (! $hasErp) {
                    $this->error('  App ERP (1302269045204850) TIDAK ada — jalankan: php artisan meta:whatsapp-waba-subscribe --subscribe');
                }
            } catch (\Throwable $e) {
                $this->error('subscribed_apps error: '.$e->getMessage());
            }
            $this->line('');
        }

        if ($this->option('probe') && $verifyToken) {
            $this->info('Probe GET verify (dari server ini):');
            $probeUrl = $webhookUrl.'?'.http_build_query([
                'hub_mode' => 'subscribe',
                'hub_verify_token' => $verifyToken,
                'hub_challenge' => 'erp_probe_'.time(),
            ]);
            try {
                $probe = Http::timeout(15)->get($probeUrl);
                $this->line('  HTTP '.$probe->status().' body='.mb_substr($probe->body(), 0, 80));
                if ($probe->status() !== 200) {
                    $this->warn('  Harus 200 + challenge — cek META_WEBHOOK_VERIFY_TOKEN & route /api/webhooks/meta/whatsapp');
                }
            } catch (\Throwable $e) {
                $this->error('  Probe gagal: '.$e->getMessage());
            }
            $this->line('');
        }

        $tracePath = storage_path('logs/whatsapp-webhook.trace.log');
        $this->info('Trace webhook: '.$tracePath);
        if (is_file($tracePath)) {
            $lines = array_slice(file($tracePath, FILE_IGNORE_NEW_LINES) ?: [], -10);
            if ($lines === []) {
                $this->warn('  (file kosong — Meta belum POST)');
            } else {
                foreach ($lines as $line) {
                    $this->line('  '.$line);
                }
                if (! str_contains(implode("\n", $lines), 'note=processed') && str_contains(implode("\n", $lines), 'sig_invalid')) {
                    $this->warn('  Ada sig_invalid — set META_APP_SECRET app ERP atau sementara META_WEBHOOK_SKIP_SIGNATURE_VERIFY=true');
                }
            }
        } else {
            $this->warn('  Belum ada — kirim chat WA lalu: tail -f storage/logs/whatsapp-webhook.trace.log');
        }
        $this->line('');

        $this->info('Percakapan WhatsApp di DB:');
        $conversations = OmniConversation::query()
            ->where('channel', 'whatsapp')
            ->orderByDesc('last_message_at')
            ->limit(5)
            ->get(['id', 'phone_number_id', 'external_contact_id', 'contact_name', 'last_message_at', 'last_message_preview']);

        if ($conversations->isEmpty()) {
            $this->warn('  Tidak ada — webhook belum menyimpan pesan masuk.');
        } else {
            foreach ($conversations as $c) {
                $this->line(sprintf(
                    '  #%d %s phone=%s — %s',
                    $c->id,
                    $c->contact_name ?? $c->external_contact_id,
                    $c->phone_number_id ?? '-',
                    $c->last_message_preview ?? ''
                ));
            }
        }

        $inboundCount = OmniMessage::query()
            ->where('direction', 'inbound')
            ->whereHas('conversation', fn ($q) => $q->where('channel', 'whatsapp'))
            ->where('created_at', '>=', now()->subHours(24))
            ->count();
        $this->line('');
        $this->line('Inbound WA 24 jam terakhir: '.$inboundCount);

        $this->line('');
        $this->info('Urutan perbaikan (arsip 0 + chat tes tidak masuk):');
        $this->line('  1. developers.facebook.com → app YMSoft ERP → WhatsApp → Configuration');
        $this->line('     Callback: '.$webhookUrl);
        $this->line('     Verify token = META_WEBHOOK_VERIFY_TOKEN → klik Verify and save, centang messages');
        $this->line('  2. php artisan meta:whatsapp-waba-subscribe --subscribe');
        $this->line('  3. Kirim WA ke nomor production, di terminal lain: tail -f storage/logs/whatsapp-webhook.trace.log');
        $this->line('     Harus muncul baris POST content_len>0 (bukan hanya GET verify)');
        $this->line('  4. Jika POST sig_invalid: perbaiki META_APP_SECRET atau META_WEBHOOK_SKIP_SIGNATURE_VERIFY=true');
        $this->line('  5. php artisan config:clear — replay hanya berguna jika arsip ada file .json');

        return self::SUCCESS;
    }
}
