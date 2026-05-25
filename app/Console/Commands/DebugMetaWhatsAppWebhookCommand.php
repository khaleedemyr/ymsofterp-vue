<?php

namespace App\Console\Commands;

use App\Models\OmniConversation;
use App\Models\OmniMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DebugMetaWhatsAppWebhookCommand extends Command
{
    protected $signature = 'meta:debug-whatsapp-webhook';

    protected $description = 'Diagnosa webhook WhatsApp: config, trace log, percakapan terakhir di DB';

    public function handle(): int
    {
        $webhookUrl = url('/api/webhooks/meta/whatsapp');
        $phoneId = config('services.meta.whatsapp_phone_number_id');
        $wabaId = config('services.meta.whatsapp_business_account_id');
        $appSecret = config('services.meta.app_secret');
        $verifyToken = config('services.meta.webhook_verify_token');
        $skipSig = config('services.meta.webhook_skip_signature_verify');

        $this->info('Webhook URL (wajib sama di Meta App Dashboard):');
        $this->line('  '.$webhookUrl);
        $this->line('Phone Number ID: '.($phoneId ?: '(kosong)'));
        $this->line('WABA ID: '.($wabaId ?: '(kosong)'));
        $this->line('META_APP_SECRET: '.($appSecret !== '' && $appSecret !== null ? 'terisi' : 'KOSONG (signature skip otomatis)'));
        $this->line('META_WEBHOOK_VERIFY_TOKEN: '.($verifyToken !== '' && $verifyToken !== null ? 'terisi' : 'KOSONG'));
        $this->line('META_WEBHOOK_SKIP_SIGNATURE_VERIFY: '.($skipSig ? 'true' : 'false'));
        $this->line('');

        if ($wabaId && config('services.meta.whatsapp_access_token')) {
            try {
                $version = config('services.meta.graph_api_version', 'v25.0');
                $token = config('services.meta.whatsapp_access_token');
                $response = Http::withToken($token)
                    ->get("https://graph.facebook.com/{$version}/{$wabaId}/subscribed_apps");
                $this->info('subscribed_apps:');
                if ($response->successful()) {
                    foreach ($response->json('data') ?? [] as $row) {
                        $meta = $row['whatsapp_business_api_data'] ?? $row;
                        $this->line('  - '.($meta['name'] ?? '?').' (id: '.($meta['id'] ?? '?').')');
                    }
                } else {
                    $this->warn('  Gagal: '.$response->body());
                }
            } catch (\Throwable $e) {
                $this->warn('subscribed_apps error: '.$e->getMessage());
            }
            $this->line('');
        }

        $tracePath = storage_path('logs/whatsapp-webhook.trace.log');
        $this->info('Trace webhook: '.$tracePath);
        if (is_file($tracePath)) {
            $lines = array_slice(file($tracePath, FILE_IGNORE_NEW_LINES) ?: [], -8);
            if ($lines === []) {
                $this->warn('  (file kosong — Meta belum POST ke server)');
            } else {
                foreach ($lines as $line) {
                    $this->line('  '.$line);
                }
            }
        } else {
            $this->warn('  Belum ada — kirim chat WA lalu cek lagi. Jika tetap kosong: URL webhook salah atau Meta tidak mengirim.');
        }
        $this->line('');

        $this->info('Percakapan WhatsApp terakhir di DB:');
        $conversations = OmniConversation::query()
            ->where('channel', 'whatsapp')
            ->orderByDesc('last_message_at')
            ->limit(5)
            ->get(['id', 'phone_number_id', 'contact_name', 'last_message_at', 'last_message_preview']);

        if ($conversations->isEmpty()) {
            $this->warn('  Tidak ada omni_conversations channel=whatsapp — webhook belum simpan pesan.');
        } else {
            foreach ($conversations as $c) {
                $this->line(sprintf(
                    '  #%d phone_number_id=%s %s — %s',
                    $c->id,
                    $c->phone_number_id ?? '-',
                    $c->contact_name ?? '-',
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
        $this->line('Checklist jika chat tidak masuk inbox:');
        $this->line('  1. Meta App → WhatsApp → Webhook callback = URL di atas + field messages');
        $this->line('  2. META_APP_SECRET = App Secret app YMSoft ERP (1302269045204850)');
        $this->line('  3. grep "Meta WhatsApp" storage/logs/laravel.log | tail -20');
        $this->line('  4. Inbox ERP: filter channel = WhatsApp / Semua, refresh');

        return self::SUCCESS;
    }
}
