<?php

namespace App\Services\Meta;

use App\Services\Omni\SocialCommentsNotificationService;
use App\Support\MetaInstagramAccountRegistry;
use App\Support\MetaPageAccountRegistry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Poll komentar IG/FB, kirim notifikasi ke admin inbox untuk komentar baru.
 */
class MetaSocialCommentsNotificationService
{
    public function __construct(
        private readonly MetaInstagramCommentsService $instagram,
        private readonly MetaFacebookCommentsService $facebook,
        private readonly SocialCommentsNotificationService $notifier,
    ) {}

    /**
     * @return array{notified: int, scanned_posts: int, skipped_seen: int, errors: int}
     */
    public function checkAll(): array
    {
        if (! filter_var(config('omnichannel.social_comment_notify_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return ['notified' => 0, 'scanned_posts' => 0, 'skipped_seen' => 0, 'errors' => 0];
        }

        $notified = 0;
        $scannedPosts = 0;
        $skippedSeen = 0;
        $errors = 0;

        foreach ($this->instagram->listAccounts() as $account) {
            try {
                $result = $this->checkInstagramAccount((string) $account['ig_id'], (string) $account['label']);
                $notified += $result['notified'];
                $scannedPosts += $result['scanned_posts'];
                $skippedSeen += $result['skipped_seen'];
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('Social comment check IG failed', [
                    'ig_id' => $account['ig_id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach ($this->facebook->listPages() as $page) {
            try {
                $result = $this->checkFacebookPage((string) $page['page_id'], (string) $page['label']);
                $notified += $result['notified'];
                $scannedPosts += $result['scanned_posts'];
                $skippedSeen += $result['skipped_seen'];
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('Social comment check FB failed', [
                    'page_id' => $page['page_id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'notified' => $notified,
            'scanned_posts' => $scannedPosts,
            'skipped_seen' => $skippedSeen,
            'errors' => $errors,
        ];
    }

    /**
     * @return array{notified: int, scanned_posts: int, skipped_seen: int}
     */
    private function checkInstagramAccount(string $configuredIgId, string $accountLabel): array
    {
        $resolved = $this->instagram->resolveAccount($configuredIgId);
        $businessId = $resolved['ig_id'];
        $label = $accountLabel !== '' ? $accountLabel : MetaInstagramAccountRegistry::displayLabel($businessId);

        $notified = 0;
        $scannedPosts = 0;
        $skippedSeen = 0;

        $postLimit = (int) config('omnichannel.social_comment_posts_per_account', 12);
        $media = $this->instagram->listMedia($configuredIgId, $postLimit);

        foreach ($media as $post) {
            if ((int) ($post['comments_count'] ?? 0) <= 0) {
                continue;
            }

            $scannedPosts++;
            $postId = (string) ($post['id'] ?? '');
            if ($postId === '') {
                continue;
            }

            $comments = $this->instagram->listComments($configuredIgId, $postId, 40);
            foreach ($this->flattenComments($comments) as $comment) {
                $result = $this->processComment(
                    'instagram',
                    $businessId,
                    $label,
                    $postId,
                    (string) ($post['caption'] ?? ''),
                    $comment,
                    $businessId
                );
                $notified += $result['notified'];
                $skippedSeen += $result['skipped_seen'];
            }
        }

        return compact('notified', 'scannedPosts', 'skippedSeen');
    }

    /**
     * @return array{notified: int, scanned_posts: int, skipped_seen: int}
     */
    private function checkFacebookPage(string $configuredPageId, string $accountLabel): array
    {
        $resolved = $this->facebook->resolvePage($configuredPageId);
        $pageId = $resolved['page_id'];
        $label = $accountLabel !== '' ? $accountLabel : MetaPageAccountRegistry::displayLabel($pageId);

        $notified = 0;
        $scannedPosts = 0;
        $skippedSeen = 0;

        $postLimit = (int) config('omnichannel.social_comment_posts_per_account', 12);
        $posts = $this->facebook->listPosts($configuredPageId, $postLimit);

        foreach ($posts as $post) {
            if ((int) ($post['comments_count'] ?? 0) <= 0) {
                continue;
            }

            $scannedPosts++;
            $postId = (string) ($post['id'] ?? '');
            if ($postId === '') {
                continue;
            }

            $comments = $this->facebook->listComments($configuredPageId, $postId, 40);
            foreach ($this->flattenComments($comments) as $comment) {
                $result = $this->processComment(
                    'facebook',
                    $pageId,
                    $label,
                    $postId,
                    (string) ($post['caption'] ?? ''),
                    $comment,
                    $pageId
                );
                $notified += $result['notified'];
                $skippedSeen += $result['skipped_seen'];
            }
        }

        return compact('notified', 'scannedPosts', 'skippedSeen');
    }

    /**
     * @param  list<array<string, mixed>>  $comments
     * @return list<array<string, mixed>>
     */
    private function flattenComments(array $comments): array
    {
        $flat = [];
        foreach ($comments as $comment) {
            if (! is_array($comment)) {
                continue;
            }
            $flat[] = $comment;
            foreach ($comment['replies'] ?? [] as $reply) {
                if (is_array($reply)) {
                    $flat[] = $reply;
                }
            }
        }

        return $flat;
    }

    /**
     * @param  array<string, mixed>  $comment
     * @return array{notified: int, skipped_seen: int}
     */
    private function processComment(
        string $platform,
        string $accountId,
        string $accountLabel,
        string $postMetaId,
        string $postPreview,
        array $comment,
        string $ownAccountId
    ): array {
        $commentMetaId = (string) ($comment['id'] ?? '');
        if ($commentMetaId === '') {
            return ['notified' => 0, 'skipped_seen' => 0];
        }

        if (DB::table('omni_social_comment_seen')->where('platform', $platform)->where('comment_meta_id', $commentMetaId)->exists()) {
            return ['notified' => 0, 'skipped_seen' => 1];
        }

        $fromId = (string) ($comment['from_id'] ?? '');
        if ($fromId !== '' && $fromId === $ownAccountId) {
            $this->markSeen($platform, $accountId, $accountLabel, $postMetaId, $postPreview, $comment, null);

            return ['notified' => 0, 'skipped_seen' => 1];
        }

        $commentAt = $this->parseCommentTime((string) ($comment['timestamp'] ?? ''));
        $maxAge = (int) config('omnichannel.social_comment_notify_within_minutes', 45);
        $isRecent = $commentAt === null || $commentAt->gte(now()->subMinutes($maxAge));

        $commenter = (string) ($comment['username'] ?? '');
        $text = (string) ($comment['text'] ?? '');

        if ($isRecent) {
            $this->notifier->notifyNewComment(
                $platform,
                $accountId,
                $accountLabel,
                $postMetaId,
                $postPreview,
                $commentMetaId,
                $commenter,
                $text
            );
            $this->markSeen($platform, $accountId, $accountLabel, $postMetaId, $postPreview, $comment, now());
        } else {
            $this->markSeen($platform, $accountId, $accountLabel, $postMetaId, $postPreview, $comment, null);
        }

        return ['notified' => $isRecent ? 1 : 0, 'skipped_seen' => 0];
    }

    /**
     * @param  array<string, mixed>  $comment
     */
    private function markSeen(
        string $platform,
        string $accountId,
        string $accountLabel,
        string $postMetaId,
        string $postPreview,
        array $comment,
        ?Carbon $notifiedAt
    ): void {
        $commentMetaId = (string) ($comment['id'] ?? '');
        if ($commentMetaId === '') {
            return;
        }

        try {
            DB::table('omni_social_comment_seen')->insert([
                'platform' => $platform,
                'account_id' => $accountId,
                'account_label' => mb_substr($accountLabel, 0, 120),
                'post_meta_id' => mb_substr($postMetaId, 0, 128),
                'post_preview' => mb_substr($postPreview, 0, 500),
                'comment_meta_id' => mb_substr($commentMetaId, 0, 128),
                'commenter_name' => mb_substr((string) ($comment['username'] ?? ''), 0, 255),
                'comment_preview' => mb_substr((string) ($comment['text'] ?? ''), 0, 500),
                'comment_at' => $this->parseCommentTime((string) ($comment['timestamp'] ?? '')),
                'notified_at' => $notifiedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // duplicate race
        }
    }

    private function parseCommentTime(string $raw): ?Carbon
    {
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->timezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }
}
