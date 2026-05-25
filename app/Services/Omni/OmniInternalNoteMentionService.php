<?php

namespace App\Services\Omni;

use App\Models\OmniConversation;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\OmnichannelUserOption;
use Illuminate\Support\Arr;

class OmniInternalNoteMentionService
{
    /**
     * @param  list<int>  $mentionedUserIds
     * @return list<int> Validated assignable user IDs (unique)
     */
    public function resolveMentionedUserIds(array $mentionedUserIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $mentionedUserIds))));
        if ($ids === []) {
            return [];
        }

        return OmnichannelUserOption::assignableQuery()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Assign chat ke user yang di-tag + kirim notifikasi in-app.
     *
     * @param  list<int>  $mentionedUserIds
     */
    public function applyMentions(
        OmniConversation $conversation,
        array $mentionedUserIds,
        User $actor,
        string $notePreview
    ): void {
        $ids = $this->resolveMentionedUserIds($mentionedUserIds);
        if ($ids === []) {
            return;
        }

        $attach = [];
        foreach ($ids as $userId) {
            $attach[$userId] = [];
        }
        $conversation->assignees()->syncWithoutDetaching($attach);

        if ($conversation->assigned_user_id === null) {
            $conversation->assigned_user_id = $ids[0];
            $conversation->save();
        }

        $this->notifyMentionedUsers($conversation, $ids, $actor, $notePreview);
    }

    /**
     * @param  list<int>  $userIds
     */
    private function notifyMentionedUsers(
        OmniConversation $conversation,
        array $userIds,
        User $actor,
        string $notePreview
    ): void {
        $label = $conversation->contact_name
            ?: (string) $conversation->external_contact_id;
        $actorName = $actor->nama_lengkap ?? $actor->email ?? 'Tim';
        $snippet = mb_substr(preg_replace('/\s+/u', ' ', trim($notePreview)) ?: '', 0, 160);
        if ($snippet !== '') {
            $snippet = '"'.$snippet.'"';
        }

        $url = url('/crm/omnichannel-inbox?conversation='.$conversation->id);

        foreach ($userIds as $userId) {
            if ((int) $userId === (int) $actor->id) {
                continue;
            }

            $message = "{$actorName} menyebut Anda di catatan internal";
            $message .= $label !== '' ? " ({$label})" : '';
            $message .= $snippet !== '' ? ': '.$snippet : '.';
            $message .= ' Chat ditambahkan ke daftar tugas Anda.';

            NotificationService::create([
                'user_id' => $userId,
                'type' => 'omnichannel_inbox_mention',
                'title' => 'Inbox Omnichannel — disebut di catatan',
                'message' => $message,
                'url' => $url,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return list<array{id: int, name: string, jabatan: ?string, outlet: ?string}>
     */
    public function mentionedUsersFromPayload(?array $payload): array
    {
        $ids = Arr::wrap($payload['mentioned_user_ids'] ?? []);
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        return OmnichannelUserOption::toOptions(
            OmnichannelUserOption::assignableQuery()->whereIn('id', $ids)->get()
        );
    }
}
