<?php

namespace App\Console\Commands;

use App\Models\OmniMessage;
use App\Services\Meta\MetaInstagramInboxSyncService;
use App\Support\MetaInstagramTokens;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class DebugMetaInstagramInboxCommand extends Command
{
    protected $signature = 'meta:debug-instagram-inbox
                            {--clear-rate-limit : Hapus backoff rate limit Instagram API}';

    protected $description = 'Diagnosa Instagram DM: token, rate limit, webhook trace, sync';

    public function handle(): int
    {
        if ($this->option('clear-rate-limit')) {
            MetaInstagramInboxSyncService::clearRateLimitBackoff();
            $this->info('Rate limit backoff Instagram dihapus. Jalankan: php artisan meta:sync-instagram-inbox -v');

            return self::SUCCESS;
        }

        $base = rtrim(config('app.url'), '/');
        $this->info('=== Instagram DM (Login API + webhook) ===');
        $this->line('APP_URL: '.$base);
        $this->line('Webhook IG Login (dashboard): '.$base.'/api/webhooks/meta/instagram');
        $this->line('Webhook object=instagram di Meta sering ke: '.$base.'/api/webhooks/meta/messenger');
        $this->line('Sync enabled: '.(filter_var(config('services.meta.instagram_inbox_sync_enabled', true), FILTER_VALIDATE_BOOLEAN) ? 'yes' : 'NO'));
        $this->line('');

        if (MetaInstagramInboxSyncService::isRateLimited()) {
            $until = (int) Cache::get(MetaInstagramInboxSyncService::RATE_LIMIT_CACHE_KEY);
            $this->error('Rate limit backoff AKTIF sampai: '.date('Y-m-d H:i:s', $until));
            $this->line('  Hapus: php artisan meta:debug-instagram-inbox --clear-rate-limit');
            $this->line('  Lalu: php artisan meta:sync-instagram-inbox --recent=60 -v');
        } else {
            $this->info('Rate limit backoff: tidak aktif');
        }

        $lastSync = Cache::get('meta_instagram_last_sync_at');
        $this->line('Last sync cache: '.($lastSync ?: '(belum pernah)'));
        $this->line('');

        $this->traceTail('messenger', 'Messenger + IG webhook (object=instagram)');
        $this->traceTail('instagram-login', 'Instagram Login webhook');
        $this->line('');

        $tokens = MetaInstagramTokens::resolved();
        $this->info('Token accounts: '.count($tokens));

        if ($tokens === []) {
            $this->error('Tidak ada token. Isi META_INSTAGRAM_LOGIN_TOKENS di .env');
            $this->line('  Lalu: php artisan config:clear');

            return self::FAILURE;
        }

        $version = config('services.meta.instagram_graph_version', 'v25.0');
        $anyOk = false;

        foreach ($tokens as $igId => $token) {
            $this->line('');
            $this->info("=== Account key: {$igId} ===");

            $me = Http::withToken($token)
                ->acceptJson()
                ->get("https://graph.instagram.com/{$version}/me", [
                    'fields' => 'user_id,username',
                ]);

            if (! $me->successful()) {
                $this->error('/me gagal: '.$me->status().' '.$me->body());
                $this->warn('  Token kedaluwarsa? Meta Dashboard → Instagram Login → Generate token baru.');

                continue;
            }

            $anyOk = true;
            $userId = (string) ($me->json('user_id') ?? '');
            $username = (string) ($me->json('username') ?? '');
            $this->line("/me OK → user_id={$userId} @{$username}");

            $conv = Http::withToken($token)
                ->acceptJson()
                ->get("https://graph.instagram.com/{$version}/me/conversations", [
                    'platform' => 'instagram',
                    'limit' => 3,
                ]);

            if (! $conv->successful()) {
                $this->error('conversations gagal: '.$conv->status().' '.$conv->body());
                if ($conv->status() === 429 || str_contains($conv->body(), '"code":4')) {
                    $this->warn('  Rate limit — tunggu atau --clear-rate-limit lalu sync lagi.');
                }

                continue;
            }

            $rows = $conv->json('data') ?? [];
            $this->line('conversations: '.count($rows));

            $first = $rows[0] ?? null;
            if (! is_array($first)) {
                continue;
            }

            $convId = (string) ($first['id'] ?? '');
            $this->line('sample conversation id: '.$convId);

            $msgs = Http::withToken($token)
                ->acceptJson()
                ->get("https://graph.instagram.com/{$version}/{$convId}/messages", [
                    'fields' => 'id,created_time,from,to,message',
                    'limit' => 3,
                ]);

            if ($msgs->successful()) {
                $this->line('/messages edge OK (sample):');
                $this->line(json_encode($msgs->json('data') ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                $this->warn('/messages edge gagal ('.$msgs->status().')');
            }
        }

        $igIn = OmniMessage::query()
            ->where('direction', 'inbound')
            ->whereHas('conversation', fn ($q) => $q->where('channel', 'instagram'))
            ->where('created_at', '>=', now()->subHours(6))
            ->count();
        $this->line('');
        $this->line('Inbound Instagram 6 jam terakhir di DB: '.$igIn);

        $this->line('');
        if ($anyOk) {
            $this->info('Langkah perbaikan:');
            $this->line('  1. php artisan meta:sync-instagram-inbox --recent=60 -v');
            $this->line('  2. Buka inbox ERP filter Instagram (trigger sync ~2 menit)');
            $this->line('  3. DM dari akun pribadi ke @IG bisnis (bukan balas dari akun bisnis)');
            $this->line('  4. Pastikan cron: * * * * * php artisan schedule:run');
        }

        return $anyOk ? self::SUCCESS : self::FAILURE;
    }

    private function traceTail(string $channel, string $label): void
    {
        $path = storage_path('logs/'.$channel.'-webhook.trace.log');
        $this->line($label.': '.$path);
        if (! is_file($path)) {
            $this->warn('  (belum ada file)');

            return;
        }

        $lines = array_slice(file($path, FILE_IGNORE_NEW_LINES) ?: [], -3);
        foreach ($lines as $line) {
            $this->line('  '.$line);
        }
    }
}
