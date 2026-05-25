<?php

namespace App\Console\Commands;

use App\Models\OmniMessage;
use App\Support\MetaInstagramTokens;
use App\Support\MetaPageTokens;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DebugOmniMetaWebhooksCommand extends Command
{
    protected $signature = 'meta:debug-omni-webhooks';

    protected $description = 'Ringkasan kesehatan webhook WA + IG + Messenger (trace log, token, sync)';

    public function handle(): int
    {
        $base = rtrim(config('app.url'), '/');

        $this->info('=== Omnichannel Meta — ringkasan ===');
        $this->line('APP_URL: '.$base);
        $this->line('');

        $this->traceSummary('WhatsApp', 'whatsapp', $base.'/api/webhooks/meta/whatsapp');
        $this->line('  Sync manual: php artisan meta:sync-whatsapp-inbox --replay');
        $this->line('  Detail: php artisan meta:debug-whatsapp-webhook --probe');
        $this->line('');

        $this->traceSummary('Instagram Login', 'instagram-login', $base.'/api/webhooks/meta/instagram');
        $igTokens = count(MetaInstagramTokens::resolved());
        $this->line('  Token IG di .env: '.$igTokens.($igTokens === 0 ? ' (KOSONG — DM tidak bisa sync)' : ''));
        $this->line('  Debug: php artisan meta:debug-instagram-inbox');
        $this->line('  Sync poll: php artisan meta:sync-instagram-inbox -v');
        $this->line('');

        $this->traceSummary('Messenger (Page)', 'messenger', $base.'/api/webhooks/meta/messenger');
        $pageTokens = count(MetaPageTokens::resolved());
        $this->line('  Page token di META_PAGE_TOKENS: '.$pageTokens.($pageTokens === 0 ? ' (KOSONG — polling Messenger gagal)' : ''));
        $this->line('  Debug: php artisan meta:debug-messenger-inbox');
        $this->line('  Sync poll: php artisan meta:sync-messenger-inbox --recent=60 -v');
        $this->line('');

        $waIn = OmniMessage::query()
            ->where('direction', 'inbound')
            ->whereHas('conversation', fn ($q) => $q->where('channel', 'whatsapp'))
            ->where('created_at', '>=', now()->subHours(6))
            ->count();
        $igIn = OmniMessage::query()
            ->where('direction', 'inbound')
            ->whereHas('conversation', fn ($q) => $q->where('channel', 'instagram'))
            ->where('created_at', '>=', now()->subHours(6))
            ->count();
        $msgIn = OmniMessage::query()
            ->where('direction', 'inbound')
            ->whereHas('conversation', fn ($q) => $q->whereIn('channel', ['messenger', 'facebook']))
            ->where('created_at', '>=', now()->subHours(6))
            ->count();

        $this->info('Inbound 6 jam terakhir di DB:');
        $this->line("  WhatsApp: {$waIn} | Instagram: {$igIn} | Messenger: {$msgIn}");
        $this->line('');

        $this->warn('PENTING — kanal ini BERBEDA:');
        $this->line('  • WA: hanya webhook POST (bukan polling). GET verify ≠ chat masuk.');
        $this->line('  • IG: webhook + polling (token IGQ… bisa kedaluwarsa ~60 hari).');
        $this->line('  • Messenger: webhook Page + polling (META_PAGE_TOKENS bisa expired).');
        $this->line('');
        $this->line('Saat tes: tail -f storage/logs/whatsapp-webhook.trace.log');
        $this->line('  Kirim chat ke +62 811-1018-8808 → harus ada baris POST baru (bukan hanya GET).');

        return self::SUCCESS;
    }

    private function traceSummary(string $label, string $channel, string $callbackUrl): void
    {
        $this->info($label);
        $this->line('  Callback: '.$callbackUrl);

        $path = storage_path('logs/'.$channel.'-webhook.trace.log');
        if (! is_file($path)) {
            $this->warn('  Trace: belum ada file (belum pernah POST/GET dari Meta)');

            return;
        }

        $lines = array_slice(file($path, FILE_IGNORE_NEW_LINES) ?: [], -30);
        $lastPost = null;
        $lastGet = null;
        foreach (array_reverse($lines) as $line) {
            if ($lastPost === null && str_contains($line, ' POST ')) {
                $lastPost = $line;
            }
            if ($lastGet === null && str_contains($line, ' GET ')) {
                $lastGet = $line;
            }
        }

        if ($lastPost) {
            $this->line('  POST terakhir: '.mb_substr($lastPost, 0, 120));
        } else {
            $this->warn('  Belum ada POST di trace (30 baris terakhir)');
        }
        if ($lastGet) {
            $this->line('  GET terakhir: '.mb_substr($lastGet, 0, 100));
        }
    }
}
