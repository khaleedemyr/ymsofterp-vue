<?php

namespace App\Http\Controllers;

use App\Models\OmniConversation;
use App\Models\OmniMessage;
use App\Services\Meta\MetaWhatsAppClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class OmnichannelInboxController extends Controller
{
    public function index(Request $request): Response
    {
        $conversations = OmniConversation::query()
            ->with(['member:id,nama_lengkap,mobile_phone,member_id'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (OmniConversation $c) => $this->formatConversation($c));

        $selectedId = $request->integer('conversation')
            ?: ($conversations->first()['id'] ?? null);

        $messages = [];
        $selectedConversation = null;

        if ($selectedId) {
            $conversation = OmniConversation::query()->with('member')->find($selectedId);
            if ($conversation) {
                $selectedConversation = $this->formatConversation($conversation);
                $messages = $this->loadMessages($conversation);
                $conversation->update(['unread_count' => 0]);
            }
        }

        return Inertia::render('Crm/OmnichannelInbox/Index', [
            'conversations' => $conversations,
            'selectedConversation' => $selectedConversation,
            'messages' => $messages,
            'channelFilter' => $request->get('channel', 'whatsapp'),
        ]);
    }

    public function messages(OmniConversation $conversation): JsonResponse
    {
        $conversation->update(['unread_count' => 0]);

        return response()->json([
            'conversation' => $this->formatConversation($conversation->load('member')),
            'messages' => $this->loadMessages($conversation),
        ]);
    }

    public function sendMessage(Request $request, OmniConversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:4096'],
        ]);

        if ($conversation->channel !== 'whatsapp') {
            return response()->json(['message' => 'Channel belum didukung.'], 422);
        }

        try {
            $result = app(MetaWhatsAppClient::class)->sendText(
                $conversation->external_contact_id,
                $validated['body'],
                $conversation->phone_number_id
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $metaMessageId = (string) ($result['messages'][0]['id'] ?? '');
        $sentAt = now();

        $message = OmniMessage::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'meta_message_id' => $metaMessageId !== '' ? $metaMessageId : null,
            'message_type' => 'text',
            'body' => $validated['body'],
            'payload' => $result,
            'status' => 'sent',
            'sent_at' => $sentAt,
        ]);

        $conversation->update([
            'last_message_at' => $sentAt,
            'last_message_preview' => mb_substr($validated['body'], 0, 500),
        ]);

        return response()->json([
            'message' => $this->formatMessage($message),
        ]);
    }

    private function loadMessages(OmniConversation $conversation): array
    {
        return $conversation->messages()
            ->orderBy('sent_at')
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->map(fn (OmniMessage $m) => $this->formatMessage($m))
            ->all();
    }

    private function formatConversation(OmniConversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'channel' => $conversation->channel,
            'external_contact_id' => $conversation->external_contact_id,
            'contact_name' => $conversation->contact_name,
            'display_phone' => $this->formatDisplayPhone($conversation->external_contact_id),
            'last_message_preview' => $conversation->last_message_preview,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'unread_count' => (int) $conversation->unread_count,
            'status' => $conversation->status,
            'member' => $conversation->member ? [
                'id' => $conversation->member->id,
                'nama_lengkap' => $conversation->member->nama_lengkap,
                'mobile_phone' => $conversation->member->mobile_phone,
                'member_id' => $conversation->member->member_id,
            ] : null,
        ];
    }

    private function formatMessage(OmniMessage $message): array
    {
        return [
            'id' => $message->id,
            'direction' => $message->direction,
            'message_type' => $message->message_type,
            'body' => $message->body,
            'status' => $message->status,
            'sent_at' => ($message->sent_at ?? $message->created_at)?->toIso8601String(),
        ];
    }

    private function formatDisplayPhone(string $waId): string
    {
        if (str_starts_with($waId, '62')) {
            return '0'.substr($waId, 2);
        }

        return $waId;
    }
}
