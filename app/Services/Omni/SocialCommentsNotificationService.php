<?php

namespace App\Services\Omni;

use App\Models\User;
use App\Services\NotificationService;
use App\Support\OmnichannelAuthorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Notifikasi ke admin "lihat semua inbox" (Tim Inbox Omnichannel) saat komentar IG/FB baru.
 */
class SocialCommentsNotificationService
{
    public function notifyNewComment(
        string $platform,
        string $accountId,
        string $accountLabel,
        string $postMetaId,
        string $postPreview,
        string $commentMetaId,
        string $commenterName,
        string $commentText
    ): void {
        $recipientIds = $this->resolveAdminRecipientIds();
        if ($recipientIds === []) {
            Log::debug('Social comment notify: no admin recipients');

            return;
        }

        $channelLabel = $platform === 'facebook' ? 'Facebook' : 'Instagram';
        $postShort = mb_substr($postPreview !== '' ? $postPreview : 'Post', 0, 120);
        $commentShort = mb_substr($commentText, 0, 200);
        $author = $commenterName !== '' ? $commenterName : 'Pengguna';

        $title = "Komentar baru — {$channelLabel}";
        $message = "【{$accountLabel}】 Post: \"{$postShort}\" — {$author}: {$commentShort}";

        $url = url('/crm/instagram-comments?'.http_build_query([
            'platform' => $platform === 'facebook' ? 'facebook' : 'instagram',
            'account' => $accountId,
            'post' => $postMetaId,
        ]));

        foreach ($recipientIds as $userId) {
            NotificationService::create([
                'user_id' => $userId,
                'type' => 'social_comment_new',
                'title' => $title,
                'message' => $message,
                'url' => $url,
            ]);
        }

        Log::info('Social comment notify sent', [
            'platform' => $platform,
            'account_id' => $accountId,
            'post_meta_id' => $postMetaId,
            'comment_meta_id' => mb_substr($commentMetaId, 0, 40),
            'recipient_count' => count($recipientIds),
        ]);
    }

    /**
     * Pengguna di menu Tim Inbox → "Pengguna yang melihat semua inbox".
     *
     * @return list<int>
     */
    private function resolveAdminRecipientIds(): array
    {
        $ids = OmnichannelAuthorization::fullAccessUserIds();
        if ($ids === []) {
            return [];
        }

        $active = User::query()
            ->whereIn('id', $ids)
            ->where('status', 'A')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_filter(
            $active,
            fn (int $uid) => OmnichannelAuthorization::userHasPermission($uid, 'omnichannel_inbox_view')
                || OmnichannelAuthorization::userHasPermission($uid, 'instagram_comments_view')
        ));
    }
}
