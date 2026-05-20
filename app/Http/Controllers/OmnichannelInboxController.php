<?php

namespace App\Http\Controllers;

use App\Models\OmniConversation;
use App\Models\OmniMessage;
use App\Models\OmniTeam;
use App\Models\User;
use App\Services\Meta\MetaWhatsAppClient;
use App\Services\NotificationService;
use App\Support\OmniLeadStages;
use App\Support\OmnichannelAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class OmnichannelInboxController extends Controller
{
    public function index(Request $request): Response
    {
        $this->assertInboxAccess($request);

        $user = $request->user();
        $canSeeAll = OmnichannelAuthorization::canSeeAllChats($user);

        $inbox = $request->get('inbox', 'all');
        if (! in_array($inbox, ['all', 'mine', 'unassigned'], true)) {
            $inbox = 'all';
        }

        $leadStageFilter = $request->get('lead_stage');
        if ($leadStageFilter !== null && $leadStageFilter !== '' && ! OmniLeadStages::isValid((string) $leadStageFilter)) {
            $leadStageFilter = null;
        }

        $query = OmniConversation::query()
            ->with([
                'member:id,nama_lengkap,mobile_phone,member_id,member_level,is_exclusive_member',
                'assignee:id,nama_lengkap,email',
                'assignees:id,nama_lengkap,email',
                'teams:id,name',
            ]);

        OmnichannelAuthorization::applyInboxVisibility($query, $user, $inbox, $canSeeAll);

        if ($leadStageFilter) {
            $query->where('lead_stage', $leadStageFilter);
        }

        $conversations = $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(150)
            ->get()
            ->map(fn (OmniConversation $c) => $this->formatConversation($c));

        $selectedId = $request->integer('conversation')
            ?: ($conversations->first()['id'] ?? null);

        $messages = [];
        $selectedConversation = null;

        if ($selectedId) {
            $conversation = OmniConversation::query()
                ->with(['member', 'assignee', 'assignees', 'teams:id,name'])
                ->find($selectedId);
            if ($conversation && OmnichannelAuthorization::userCanAccessConversation($user, $conversation, $canSeeAll)) {
                $selectedConversation = $this->formatConversation($conversation);
                $messages = $this->loadMessages($conversation);
                $conversation->update(['unread_count' => 0]);
            }
        }

        $assignableUsers = User::query()
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'email'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->nama_lengkap ?? $u->email,
            ]);

        $assignableTeams = OmniTeam::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (OmniTeam $t) => [
                'id' => $t->id,
                'name' => $t->name,
            ]);

        return Inertia::render('Crm/OmnichannelInbox/Index', [
            'conversations' => $conversations,
            'selectedConversation' => $selectedConversation,
            'messages' => $messages,
            'inbox' => $inbox,
            'leadStageFilter' => $leadStageFilter,
            'leadStages' => OmniLeadStages::all(),
            'assignableUsers' => $assignableUsers,
            'assignableTeams' => $assignableTeams,
            'canSeeAllChats' => $canSeeAll,
            'canManageOmnichannelTeams' => OmnichannelAuthorization::userHasPermission((int) $request->user()->id, 'omnichannel_teams_view'),
        ]);
    }

    public function update(Request $request, OmniConversation $conversation): JsonResponse
    {
        $this->assertInboxAccess($request);
        $user = $request->user();
        $canSeeAll = OmnichannelAuthorization::canSeeAllChats($user);
        abort_unless(
            OmnichannelAuthorization::userCanAccessConversation($user, $conversation, $canSeeAll),
            403
        );

        $validated = $request->validate([
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_user_ids.*' => ['integer', 'exists:users,id'],
            'assigned_team_ids' => ['nullable', 'array'],
            'assigned_team_ids.*' => ['integer', 'exists:omni_teams,id'],
            'notify_assignees' => ['sometimes', 'boolean'],
            'lead_stage' => ['nullable', 'string', Rule::in(OmniLeadStages::values())],
            'memo' => ['nullable', 'string', 'max:10000'],
            'contact_first_name' => ['nullable', 'string', 'max:120'],
            'contact_last_name' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['nullable', 'string', 'max:255'],
            'contact_company' => ['nullable', 'string', 'max:255'],
            'contact_job_title' => ['nullable', 'string', 'max:255'],
        ]);

        $notify = (bool) ($validated['notify_assignees'] ?? false);
        unset($validated['notify_assignees']);

        $assignedIds = null;
        if (array_key_exists('assigned_user_ids', $validated)) {
            $assignedIds = array_values(array_unique(array_filter($validated['assigned_user_ids'] ?? [])));
            unset($validated['assigned_user_ids']);
        }

        $assignedTeamIds = null;
        if (array_key_exists('assigned_team_ids', $validated)) {
            $assignedTeamIds = array_values(array_unique(array_filter($validated['assigned_team_ids'] ?? [])));
            unset($validated['assigned_team_ids']);
        }

        $conversation->fill($validated);

        if ($assignedIds !== null) {
            $syncPayload = [];
            foreach ($assignedIds as $userId) {
                $syncPayload[$userId] = [];
            }
            $conversation->assignees()->sync($syncPayload);
            $conversation->assigned_user_id = $assignedIds[0] ?? null;
        }

        if ($assignedTeamIds !== null) {
            $syncTeams = [];
            foreach ($assignedTeamIds as $teamId) {
                $syncTeams[$teamId] = [];
            }
            $conversation->teams()->sync($syncTeams);
        }

        $conversation->save();

        $notifyUserIds = [];
        if ($notify && ($assignedIds !== null || $assignedTeamIds !== null)) {
            if ($assignedIds !== null) {
                $notifyUserIds = array_merge($notifyUserIds, $assignedIds);
            }
            if ($assignedTeamIds !== null && count($assignedTeamIds) > 0) {
                $fromTeams = DB::table('omni_team_user')
                    ->whereIn('team_id', $assignedTeamIds)
                    ->pluck('user_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
                $notifyUserIds = array_merge($notifyUserIds, $fromTeams);
            }
            $notifyUserIds = array_values(array_unique(array_filter($notifyUserIds)));
            if (count($notifyUserIds) > 0) {
                $this->notifyAssignees($conversation->fresh(['assignees', 'teams']), $notifyUserIds, $request->user());
            }
        }

        return response()->json([
            'conversation' => $this->formatConversation($conversation->fresh(['member', 'assignee', 'assignees', 'teams:id,name'])),
        ]);
    }

    /**
     * @param  list<int>  $userIds
     */
    private function notifyAssignees(OmniConversation $conversation, array $userIds, User $actor): void
    {
        $label = $conversation->contact_name
            ?: $this->formatDisplayPhone($conversation->external_contact_id);
        $actorName = $actor->nama_lengkap ?? $actor->email ?? 'Tim';
        $message = "{$actorName} menugaskan Anda ke chat WhatsApp: {$label}.";
        $url = url('/crm/omnichannel-inbox?conversation='.$conversation->id);

        foreach ($userIds as $userId) {
            if ((int) $userId === (int) $actor->id) {
                continue;
            }
            NotificationService::create([
                'user_id' => $userId,
                'type' => 'omnichannel_inbox_assign',
                'title' => 'Inbox Omnichannel — penugasan chat',
                'message' => $message,
                'url' => $url,
            ]);
        }
    }

    public function messages(Request $request, OmniConversation $conversation): JsonResponse
    {
        $this->assertInboxAccess($request);
        $user = $request->user();
        $canSeeAll = OmnichannelAuthorization::canSeeAllChats($user);
        abort_unless(
            OmnichannelAuthorization::userCanAccessConversation($user, $conversation, $canSeeAll),
            403
        );

        $conversation->update(['unread_count' => 0]);

        return response()->json([
            'conversation' => $this->formatConversation($conversation->load(['member', 'assignee', 'assignees', 'teams:id,name'])),
            'messages' => $this->loadMessages($conversation),
        ]);
    }

    public function sendMessage(Request $request, OmniConversation $conversation): JsonResponse
    {
        $this->assertInboxAccess($request);
        $user = $request->user();
        $canSeeAll = OmnichannelAuthorization::canSeeAllChats($user);
        abort_unless(
            OmnichannelAuthorization::userCanAccessConversation($user, $conversation, $canSeeAll),
            403
        );

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
            'user_id' => $request->user()->id,
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

    public function storeInternalNote(Request $request, OmniConversation $conversation): JsonResponse
    {
        $this->assertInboxAccess($request);
        $user = $request->user();
        $canSeeAll = OmnichannelAuthorization::canSeeAllChats($user);
        abort_unless(
            OmnichannelAuthorization::userCanAccessConversation($user, $conversation, $canSeeAll),
            403
        );

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:8192'],
        ]);

        $sentAt = now();

        $message = OmniMessage::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'direction' => 'internal',
            'meta_message_id' => null,
            'message_type' => 'note',
            'body' => $validated['body'],
            'payload' => null,
            'status' => null,
            'sent_at' => $sentAt,
        ]);

        $conversation->update([
            'last_message_at' => $sentAt,
            'last_message_preview' => mb_substr('[Catatan] '.$validated['body'], 0, 500),
        ]);

        return response()->json([
            'message' => $this->formatMessage($message->load('author')),
        ]);
    }

    private function assertInboxAccess(Request $request): void
    {
        abort_unless(OmnichannelAuthorization::canViewInbox($request->user()), 403);
    }

    private function loadMessages(OmniConversation $conversation): array
    {
        return $conversation->messages()
            ->with('author:id,nama_lengkap,email')
            ->orderBy('sent_at')
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->map(fn (OmniMessage $m) => $this->formatMessage($m))
            ->all();
    }

    private function formatConversation(OmniConversation $conversation): array
    {
        $assignees = [];
        if ($conversation->relationLoaded('assignees')) {
            $assignees = $conversation->assignees->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->nama_lengkap ?? $u->email,
            ])->values()->all();
        }

        $assignedTeams = [];
        if ($conversation->relationLoaded('teams')) {
            $assignedTeams = $conversation->teams->map(fn (OmniTeam $t) => [
                'id' => $t->id,
                'name' => $t->name,
            ])->values()->all();
        }

        return [
            'id' => $conversation->id,
            'channel' => $conversation->channel,
            'external_contact_id' => $conversation->external_contact_id,
            'contact_name' => $conversation->contact_name,
            'display_phone' => $this->formatDisplayPhone($conversation->external_contact_id),
            'last_message_preview' => $conversation->last_message_preview,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'last_customer_message_at' => $conversation->last_customer_message_at?->toIso8601String(),
            'unread_count' => (int) $conversation->unread_count,
            'status' => $conversation->status,
            'lead_stage' => $conversation->lead_stage ?? 'new_lead',
            'memo' => $conversation->memo,
            'contact_first_name' => $conversation->contact_first_name,
            'contact_last_name' => $conversation->contact_last_name,
            'contact_email' => $conversation->contact_email,
            'contact_company' => $conversation->contact_company,
            'contact_job_title' => $conversation->contact_job_title,
            'assigned_user_id' => $conversation->assigned_user_id,
            'assignees' => $assignees,
            'assigned_teams' => $assignedTeams,
            'assignee' => $conversation->assignee ? [
                'id' => $conversation->assignee->id,
                'name' => $conversation->assignee->nama_lengkap ?? $conversation->assignee->email,
            ] : null,
            'member' => $conversation->member ? [
                'id' => $conversation->member->id,
                'nama_lengkap' => $conversation->member->nama_lengkap,
                'mobile_phone' => $conversation->member->mobile_phone,
                'member_id' => $conversation->member->member_id,
                'member_level' => $conversation->member->member_level,
                'is_exclusive_member' => (bool) $conversation->member->is_exclusive_member,
            ] : null,
        ];
    }

    private function formatMessage(OmniMessage $message): array
    {
        $authorName = null;
        if ($message->relationLoaded('author') && $message->author) {
            $authorName = $message->author->nama_lengkap ?? $message->author->email;
        }

        return [
            'id' => $message->id,
            'direction' => $message->direction,
            'message_type' => $message->message_type,
            'body' => $message->body,
            'status' => $message->status,
            'sent_at' => ($message->sent_at ?? $message->created_at)?->toIso8601String(),
            'author_name' => $authorName,
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
