<?php

namespace App\Console\Commands;

use App\Support\MetaInstagramTokens;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DebugMetaInstagramInboxCommand extends Command
{
    protected $signature = 'meta:debug-instagram-inbox';

    protected $description = 'Diagnosa koneksi Instagram Login API (token, conversations, sample message)';

    public function handle(): int
    {
        $tokens = MetaInstagramTokens::resolved();
        $this->info('Token accounts: '.count($tokens));

        if ($tokens === []) {
            $this->error('Tidak ada token. Isi META_INSTAGRAM_LOGIN_TOKENS di .env');

            return self::FAILURE;
        }

        $version = config('services.meta.instagram_graph_version', 'v25.0');

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

                continue;
            }

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
                $this->line('/messages edge OK:');
                $this->line(json_encode($msgs->json('data') ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                $this->warn('/messages edge gagal ('.$msgs->status().'), coba legacy:');
                $legacy = Http::withToken($token)
                    ->acceptJson()
                    ->get("https://graph.instagram.com/{$version}/{$convId}", ['fields' => 'messages']);
                if ($legacy->successful()) {
                    $ids = array_column($legacy->json('messages.data') ?? [], 'id');
                    $this->line('message ids: '.implode(', ', array_slice($ids, 0, 5)));
                    if (isset($ids[0])) {
                        $one = Http::withToken($token)
                            ->acceptJson()
                            ->get("https://graph.instagram.com/{$version}/{$ids[0]}", [
                                'fields' => 'id,created_time,from,to,message',
                            ]);
                        $this->line('sample message detail:');
                        $this->line($one->body());
                    }
                } else {
                    $this->error('legacy juga gagal: '.$legacy->body());
                }
            }
        }

        $this->line('');
        $this->info('Selesai. Jalankan: php artisan meta:sync-instagram-inbox -v');

        return self::SUCCESS;
    }
}
