<?php

namespace App\Console\Commands;

use App\Services\Meta\MetaFacebookCommentsService;
use Illuminate\Console\Command;

class DebugMetaFacebookCommentsCommand extends Command
{
    protected $signature = 'meta:debug-facebook-comments {page_id? : Page ID dari META_PAGE_TOKENS}';

    protected $description = 'Diagnosa API post & komentar Facebook Page';

    public function handle(MetaFacebookCommentsService $comments): int
    {
        $pages = $comments->listPages();
        if ($pages === []) {
            $this->error('Tidak ada META_PAGE_TOKENS');

            return self::FAILURE;
        }

        $pageId = (string) ($this->argument('page_id') ?: $pages[0]['page_id']);
        $this->info("Page: {$pageId}");

        try {
            $page = $comments->resolvePage($pageId);
            $this->line('/me → '.$page['page_id'].' ('.($page['name'] ?? '-').')');

            $posts = $comments->listPosts($pageId, 3);
            $this->line('posts: '.count($posts));
            $first = $posts[0] ?? null;
            if (! is_array($first)) {
                return self::SUCCESS;
            }

            $this->line('sample: '.$first['id'].' comments='.$first['comments_count']);

            if ((int) $first['comments_count'] > 0) {
                $list = $comments->listComments($pageId, (string) $first['id'], 3);
                $this->line('comments: '.count($list));
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $this->warn('Permission: pages_read_engagement, pages_manage_engagement');

            return self::FAILURE;
        }

        $this->info('OK. Buka: /crm/instagram-comments?platform=facebook&account='.$pageId);

        return self::SUCCESS;
    }
}
