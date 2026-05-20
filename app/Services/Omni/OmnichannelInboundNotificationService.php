<?php

namespace App\Services\Omni;

use App\Models\OmniConversation;
use App\Models\OmniMessage;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\OmnichannelAuthorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Push + in-app notification saat ada pesan masuk omnichannel (bukan polling).
 */
class OmnichannelInboundNotificationService
{
    public function notifyInboundMessage(int $conversationId, int $messageId): void
    {
        $conversation = OmniConversation::query()
            ->with(['assignees:id', 'teams:id'])
            ->find($conversationId);

        $message = OmniMessage::query()->find($messageId);

        if (! $conversation || ! $message || $message->direction !== 'inbound') {
            return;
        }

        $recipientIds = $this->resolveRecipientUserIds($conversation);
        if ($recipientIds === []) {
            Log::debug('Omnichannel inbound notify: no recipients', [
                'conversation_id' => $conversationId,
            ]);

            return;
        }

        $label = $conversation->contact_name
            ?: $this->formatDisplayPhone($conversation->external_contact_id);
        $preview = mb_substr($message->body ?: '['.$message->message_type.']', 0, 160);
        $channel = strtoupper((string) ($conversation->channel ?: 'whatsapp'));
        $url = url('/crm/omnichannel-inbox?conversation='.$conversation->id);

        foreach ($recipientIds as $userId) {
            NotificationService::create([
                'user_id' => $userId,
                'type' => 'omnichannel_inbox_message',
                'title' => 'Pesan masuk — Omnichannel',
                'message' => "[{$channel}] {$label}: {$preview}",
                'url' => $url,
            ]);
        }

        Log::info('Omnichannel inbound notify sent', [
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'recipient_count' => count($recipientIds),
        ]);
    }

    /**
     * Admin inbox (lihat semua) + penugasan langsung + anggota tim terkait.
     *
     * @return list<int>
     */
    private function resolveRecipientUserIds(OmniConversation $conversation): array
    {
        $ids = OmnichannelAuthorization::fullAccessUserIds();

        if ($conversation->assigned_user_id) {
            $ids[] = (int) $conversation->assigned_user_id;
        }

        foreach ($conversation->assignees as $assignee) {
            $ids[] = (int) $assignee->id;
        }

        $teamIds = $conversation->teams->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($teamIds !== []) {
            $teamUserIds = DB::table('omni_team_user')
                ->whereIn('team_id', $teamIds)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $ids = array_merge($ids, $teamUserIds);
        }

        $ids = array_values(array_unique(array_filter($ids, fn ($id) => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $activeWithInbox = User::query()
            ->whereIn('id', $ids)
            ->where('status', 'A')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_filter(
            $activeWithInbox,
            fn (int $uid) => OmnichannelAuthorization::userHasPermission($uid, 'omnichannel_inbox_view')
        ));
    }

    private function formatDisplayPhone(?string $externalId): string
    {
        $digits = preg_replace('/\D/', '', (string) $externalId) ?? '';
        if ($digits === '') {
            return (string) $externalId;
        }
        if (str_starts_with($digits, '62')) {
            return '+'.substr($digits, 0, 2).' '.substr($digits, 2);
        }

        return '+'.$digits;
    }
}
