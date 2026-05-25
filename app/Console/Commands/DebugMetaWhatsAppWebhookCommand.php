<?php

namespace App\Console\Commands;

use App\Models\OmniConversation;
use App\Models\OmniMessage;
use App\Services\Meta\MetaWhatsAppClient;
use App\Support\MetaWhatsAppWebhookArchive;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
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
        $httpsWebhookUrl = str_starts_with($webhookUrl, 'http://')
            ? 'https://'.substr($webhookUrl, 7)
            : $webhookUrl;
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
            $this->error('  APP_URL pakai http — ubah ke https://ymsofterp.com lalu config:clear.');
            $this->line('  Callback Meta yang benar (HTTPS): '.$httpsWebhookUrl);
        }
        $this->line('Webhook URL dari APP_URL (jangan dipakai di Meta jika http):');
        $this->line('  '.$webhookUrl);
        if ($httpsWebhookUrl !== $webhookUrl) {
            $this->info('Webhook URL production (pakai ini di Meta Dashboard):');
            $this->line('  '.$httpsWebhookUrl);
        }
        if (! str_contains($httpsWebhookUrl, '/api/webhooks/meta/whatsapp')) {
            $this->warn('  URL harus mengandung /api/webhooks/meta/whatsapp');
        }
        $this->line('META_APP_ID: '.(config('services.meta.app_id') ?: '(kosong)'));
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

        $appId = config('services.meta.app_id');
        $appSecret = config('services.meta.app_secret');
        if ($appId && $appSecret) {
            try {
                $subs = $client->listAppWebhookSubscriptions();
                $this->info('App webhook subscriptions (developers → Webhooks):');
                if ($subs === []) {
                    $this->error('  KOSONG — subscribe object whatsapp_business_account + field messages di App Dashboard.');
                }
                foreach ($subs as $sub) {
                    $object = (string) ($sub['object'] ?? '?');
                    $fields = $sub['fields'] ?? [];
                    $fieldList = is_array($fields)
                        ? implode(', ', array_map(fn ($f) => is_array($f) ? ($f['name'] ?? json_encode($f)) : (string) $f, $fields))
                        : (string) $fields;
                    $active = ($sub['active'] ?? false) ? 'active' : 'inactive';
                    $callback = (string) ($sub['callback_url'] ?? '-');
                    $this->line("  - object={$object} [{$active}] fields={$fieldList}");
                    $this->line('    callback: '.$callback);
                    if ($object === 'whatsapp_business_account' && ! str_contains($fieldList, 'messages')) {
                        $this->error('    Field messages TIDAK terdaftar — chat WA tidak akan POST ke server.');
                    }
                }
            } catch (\Throwable $e) {
                $this->warn('App subscriptions: '.$e->getMessage());
                $this->line('  Cek manual: App → Webhooks → whatsapp_business_account → messages.');
            }
            $this->line('');
        }

        if ($this->option('probe')) {
            $host = parse_url($httpsWebhookUrl, PHP_URL_HOST) ?: 'ymsofterp.com';
            $this->info('Sertifikat TLS (port 443):');
            $this->inspectTlsCertificate((string) $host);
            $wwwHost = 'www.'.$host;
            if ($wwwHost !== $host) {
                $this->line('  --- www ---');
                $this->inspectTlsCertificate($wwwHost);
            }
            $this->line('');

            if ($verifyToken) {
                $challenge = 'erp_probe_'.time();
                $this->info('Probe route Laravel (internal, tanpa HTTPS):');
                $internal = Request::create('/api/webhooks/meta/whatsapp', 'GET', [
                    'hub_mode' => 'subscribe',
                    'hub_verify_token' => $verifyToken,
                    'hub_challenge' => $challenge,
                ]);
                $internalResponse = app()->handle($internal);
                $internalOk = $internalResponse->getStatusCode() === 200
                    && str_contains((string) $internalResponse->getContent(), $challenge);
                $this->line('  '.($internalOk ? 'OK' : 'FAIL').' GET /api/webhooks/meta/whatsapp → HTTP '.$internalResponse->getStatusCode());
                if (! $internalOk) {
                    $this->warn('  Route/token Laravel bermasalah — cek META_WEBHOOK_VERIFY_TOKEN.');
                }
                $this->line('');

                $this->info('Probe HTTPS publik (sama seperti Meta):');
                $query = http_build_query([
                    'hub_mode' => 'subscribe',
                    'hub_verify_token' => $verifyToken,
                    'hub_challenge' => $challenge,
                ]);
                try {
                    $probe = Http::timeout(15)->get($httpsWebhookUrl.'?'.$query);
                    $ok = $probe->status() === 200 && str_contains($probe->body(), $challenge);
                    $this->line('  '.($ok ? 'OK' : 'FAIL').' '.$httpsWebhookUrl.' → HTTP '.$probe->status());
                } catch (\Throwable $e) {
                    $msg = $e->getMessage();
                    $this->error('  Probe HTTPS gagal: '.$msg);
                    if (str_contains($msg, 'error 60') || str_contains($msg, 'certificate')) {
                        $this->error('  SSL tidak cocok untuk '.$host.' — Meta Verify & save akan GAGAL sampai cert diperbaiki.');
                        $this->line('  Perbaiki di cPanel: SSL/TLS → Let\'s Encrypt untuk ymsofterp.com + www.');
                        $this->line('  Atau sesuaikan APP_URL + callback Meta ke hostname yang ada di sertifikat (mis. www.).');
                    }
                }
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
        $this->line('  1. Perbaiki SSL agar cocok dengan hostname callback (lihat probe TLS di atas)');
        $this->line('  2. .env: APP_URL=https://ymsofterp.com → php artisan config:clear');
        $this->line('  3. developers.facebook.com → app YMSoft ERP → WhatsApp → Configuration');
        $this->line('     Callback: '.$httpsWebhookUrl);
        $this->line('     Verify token = META_WEBHOOK_VERIFY_TOKEN → klik Verify and save, centang messages');
        $this->line('  4. php artisan meta:whatsapp-waba-subscribe --subscribe');
        $this->line('  5. Kirim WA ke nomor production: tail -f storage/logs/whatsapp-webhook.trace.log');
        $this->line('  6. Ada arsip pending? php artisan meta:sync-whatsapp-inbox --replay');

        return self::SUCCESS;
    }

    private function inspectTlsCertificate(string $host): void
    {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'SNI_enabled' => true,
                'peer_name' => $host,
            ],
        ]);

        $socket = @stream_socket_client(
            'ssl://'.$host.':443',
            $errno,
            $errstr,
            12,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($socket === false) {
            $this->warn("  {$host}: tidak bisa connect ({$errstr})");

            return;
        }

        $params = stream_context_get_params($socket);
        fclose($socket);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if (! is_resource($cert) && ! is_string($cert)) {
            $this->warn("  {$host}: sertifikat tidak terbaca");

            return;
        }

        $parsed = openssl_x509_parse($cert);
        if (! is_array($parsed)) {
            $this->warn("  {$host}: parse sertifikat gagal");

            return;
        }

        $cn = $parsed['subject']['CN'] ?? '?';
        $sans = $parsed['extensions']['subjectAltName'] ?? '';
        $validTo = isset($parsed['validTo_time_t'])
            ? date('Y-m-d', (int) $parsed['validTo_time_t'])
            : '?';

        $this->line("  host={$host} CN={$cn} expires={$validTo}");
        if ($sans !== '') {
            $this->line('  SAN: '.preg_replace('/\s+/', ' ', trim($sans)));
        }

        $coversHost = stripos($cn, $host) !== false
            || stripos($sans, $host) !== false;
        if (! $coversHost && $cn !== '?') {
            $this->error("  Sertifikat TIDAK mencakup {$host} — inilah penyebab cURL error 60 / Meta verify gagal.");
        } else {
            $this->info("  Sertifikat mencakup {$host} — OK untuk Meta.");
        }
    }
}
