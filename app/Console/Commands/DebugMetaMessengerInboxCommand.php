<?php

namespace App\Console\Commands;

use App\Support\MetaPageTokens;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DebugMetaMessengerInboxCommand extends Command
{
    protected $signature = 'meta:debug-messenger-inbox';

    protected $description = 'Diagnosa Page token & Conversations API Messenger (platform=messenger)';

    public function handle(): int
    {
        $tokens = MetaPageTokens::resolved();
        if ($tokens === []) {
            $token = (string) config('services.meta.page_access_token', '');
            $pageId = (string) config('services.meta.page_id', '');
            if ($token !== '' && $pageId !== '') {
                $tokens = [$pageId => $token];
            }
        }

        $this->info('Page token entries: '.count($tokens));

        if ($tokens === []) {
            $this->error('Tidak ada token. Isi META_PAGE_TOKENS atau META_PAGE_ACCESS_TOKEN + META_PAGE_ID');

            return self::FAILURE;
        }

        $version = config('services.meta.graph_api_version', 'v25.0');

        foreach ($tokens as $pageKey => $token) {
            $this->line('');
            if (preg_match('/^178414\d+$/', (string) $pageKey)) {
                $this->warn("=== Page key: {$pageKey} — ini IG professional id, bukan Page. Pindah ke META_INSTAGRAM_LOGIN_TOKENS ===");

                continue;
            }

            $this->info("=== Page key: {$pageKey} ===");

            $me = Http::withToken($token)
                ->acceptJson()
                ->get("https://graph.facebook.com/{$version}/me", ['fields' => 'id,name']);

            if (! $me->successful()) {
                $this->error('/me gagal: '.$me->status().' '.$me->body());
                $this->warn('Kemungkinan: ini bukan Page token (mis. token IG di META_PAGE_TOKENS).');

                continue;
            }

            $pageId = (string) ($me->json('id') ?? $pageKey);
            $name = (string) ($me->json('name') ?? '');
            $this->line("/me OK → page_id={$pageId} name={$name}");

            $conv = Http::withToken($token)
                ->acceptJson()
                ->get("https://graph.facebook.com/{$version}/{$pageId}/conversations", [
                    'platform' => 'messenger',
                    'limit' => 3,
                ]);

            if (! $conv->successful()) {
                $this->error('conversations?platform=messenger gagal: '.$conv->status().' '.$conv->body());
                $this->warn('Perlu permission: pages_messaging, pages_manage_metadata, pages_read_engagement');

                continue;
            }

            $rows = $conv->json('data') ?? [];
            $this->line('conversations (messenger): '.count($rows));

            $first = $rows[0] ?? null;
            if (! is_array($first)) {
                continue;
            }

            $convId = (string) ($first['id'] ?? '');
            $this->line('sample conversation id: '.$convId);

            $msgs = Http::withToken($token)
                ->acceptJson()
                ->get("https://graph.facebook.com/{$version}/{$convId}/messages", [
                    'fields' => 'id,created_time,from,to,message',
                    'limit' => 3,
                ]);

            if ($msgs->successful()) {
                $this->line('/messages OK:');
                $this->line(json_encode($msgs->json('data') ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                $this->error('/messages gagal: '.$msgs->body());
            }
        }

        $this->line('');
        $this->info('Selesai. Jalankan: php artisan meta:sync-messenger-inbox --recent=60 -v');

        return self::SUCCESS;
    }
}
