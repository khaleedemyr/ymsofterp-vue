<?php

namespace App\Console\Commands;

use App\Models\OmniConversation;
use App\Services\Meta\MetaWhatsAppInboxSyncService;
use App\Support\MetaWhatsAppWebhookArchive;
use Illuminate\Console\Command;

class SyncMetaWhatsAppInboxCommand extends Command
{
    protected $signature = 'meta:sync-whatsapp-inbox
                            {--recent= : Hanya pesan dalam N menit terakhir (mode pull Graph, jika didukung)}
                            {--replay : Hanya replay arsip webhook (disarankan)}
                            {--replay-only : Sama dengan --replay}';

    protected $description = 'Sinkron WA ke inbox: replay arsip webhook di server (+ percobaan pull Graph)';

    public function handle(MetaWhatsAppInboxSyncService $sync): int
    {
        $verbose = $this->output->isVerbose();
        $recentOpt = $this->option('recent');
        $recentMinutes = $recentOpt !== null && $recentOpt !== ''
            ? max(1, (int) $recentOpt)
            : null;

        $replayOnly = $this->option('replay') || $this->option('replay-only');

        $this->line('Phone Number ID: '.(config('services.meta.whatsapp_phone_number_id') ?: '(kosong)'));
        $this->line('Arsip webhook: '.MetaWhatsAppWebhookArchive::directory());
        $this->line('File arsip pending: '.count(MetaWhatsAppWebhookArchive::pendingFiles()));
        $this->line('');

        if ($replayOnly) {
            $this->warn('Mode --replay: hanya memproses file arsip webhook (bukan pull riwayat penuh dari Meta).');
        } else {
            $this->warn('WhatsApp Cloud API tidak punya API list conversation seperti Messenger.');
            $this->warn('Pesan baru harus masuk via webhook. Perintah ini: replay arsip + percobaan pull Page (sering tidak didukung).');
        }

        if ($recentMinutes !== null) {
            $this->line("Mode recent: {$recentMinutes} menit (pull Graph saja)");
        }

        $result = $sync->syncAll($verbose, $recentMinutes, $replayOnly);

        foreach ($result['accounts'] as $account) {
            if (($account['source'] ?? '') === 'webhook_archive') {
                $this->line(sprintf(
                    'Arsip webhook: files=%d imported=%d invalid=%d',
                    $account['files'] ?? 0,
                    $account['imported'] ?? 0,
                    $account['skipped_invalid'] ?? 0,
                ));
            }
            if (($account['source'] ?? '') === 'page_conversations') {
                if (! empty($account['error'])) {
                    $this->warn('Pull Graph (opsional, bukan webhook WA): '.$account['error']);
                    if (str_contains((string) $account['error'], 'Session has expired') || str_contains((string) $account['error'], 'code":190')) {
                        $this->line('  → Token Page (META_PAGE_TOKENS) kedaluwarsa — perbarui di Meta. Webhook WA pakai META_WHATSAPP_ACCESS_TOKEN + META_APP_SECRET.');
                    }
                } else {
                    $this->line(sprintf(
                        'Pull Graph: conv=%d checked=%d imported=%d',
                        $account['conversations'] ?? 0,
                        $account['messages_checked'] ?? 0,
                        $account['imported'] ?? 0,
                    ));
                }
                if (! empty($account['hint'])) {
                    $this->line('  → '.$account['hint']);
                }
            }
        }

        $this->info('Total imported: '.($result['imported'] ?? 0));

        $phoneId = (string) config('services.meta.whatsapp_phone_number_id', '');
        $query = OmniConversation::query()->where('channel', 'whatsapp');
        if ($phoneId !== '') {
            $query->where('phone_number_id', $phoneId);
        }

        $count = $query->count();
        $this->line("Percakapan WA di DB (phone_number_id={$phoneId}): {$count}");

        if (($result['imported'] ?? 0) === 0 && count(MetaWhatsAppWebhookArchive::pendingFiles()) === 0) {
            $this->warn('Tidak ada arsip untuk di-replay. Pastikan webhook POST sampai ke server (cek trace/log).');
            $this->warn('Set META_WHATSAPP_WEBHOOK_ARCHIVE=true lalu kirim chat lagi sebelum sync.');
        }

        return self::SUCCESS;
    }
}
