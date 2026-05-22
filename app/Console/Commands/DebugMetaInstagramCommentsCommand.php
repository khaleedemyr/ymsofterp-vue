<?php

namespace App\Console\Commands;

use App\Services\Meta\MetaInstagramCommentsService;
use Illuminate\Console\Command;

class DebugMetaInstagramCommentsCommand extends Command
{
    protected $signature = 'meta:debug-instagram-comments {ig_id? : IG professional id dari META_INSTAGRAM_LOGIN_TOKENS}';

    protected $description = 'Diagnosa API post & komentar Instagram Login';

    public function handle(MetaInstagramCommentsService $comments): int
    {
        $accounts = $comments->listAccounts();
        if ($accounts === []) {
            $this->error('Tidak ada META_INSTAGRAM_LOGIN_TOKENS');

            return self::FAILURE;
        }

        $igId = (string) ($this->argument('ig_id') ?: $accounts[0]['ig_id']);
        $this->info("Account: {$igId}");

        try {
            $account = $comments->resolveAccount($igId);
            $this->line('/me → ig_id='.$account['ig_id'].' @'.($account['username'] ?? '-'));

            $media = $comments->listMedia($igId, 3);
            $this->line('media: '.count($media));
            $first = $media[0] ?? null;
            if (! is_array($first)) {
                return self::SUCCESS;
            }

            $this->line('sample media: '.$first['id'].' ('.$first['media_type'].') comments='.$first['comments_count']);

            if ((int) $first['comments_count'] > 0) {
                $list = $comments->listComments($igId, (string) $first['id'], 3);
                $this->line('comments: '.count($list));
                if (isset($list[0]['id'])) {
                    $this->line('sample comment: @'.($list[0]['username'] ?? '?').' — '.mb_substr((string) $list[0]['text'], 0, 80));
                }
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $this->warn('Pastikan permission: instagram_business_basic, instagram_business_manage_comments');

            return self::FAILURE;
        }

        $this->info('OK. Buka: /crm/instagram-comments?account='.$igId);

        return self::SUCCESS;
    }
}
