<?php

namespace App\Http\Controllers;

use App\Models\OmniContact;
use App\Models\OmniConversation;
use App\Models\OmniFlowRun;
use App\Models\OmniMessage;
use App\Models\OmniMessageTemplate;
use App\Models\OmniTeam;
use App\Models\User;
use App\Services\Meta\MetaMessengerClient;
use App\Services\Meta\MetaWhatsAppClient;
use App\Support\MetaInstagramAccountRegistry;
use App\Support\MetaPageAccountRegistry;
use App\Support\MetaInstagramInboxSyncTrigger;
use App\Support\MetaMessengerInboxSyncTrigger;
use App\Support\MetaInstagramTokens;
use App\Support\OmniMetaMessagePayload;
use App\Services\NotificationService;
use App\Services\Omni\OmniAiWritingService;
use App\Services\Omni\OmniContactProfileService;
use App\Services\Omni\OmniInternalNoteMentionService;
use App\Support\OmniContactMaritalStatus;
use App\Support\OmniInstagramStoryReply;
use App\Support\OmniLeadStages;
use App\Services\Omni\OmnichannelInboxMediaService;
use App\Services\Omni\OmnichannelInboxOutboundMediaService;
use App\Support\OmnichannelAuthorization;
use App\Support\OmnichannelUserOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class OmnichannelInboxController extends Controller
{
    private const MESSAGES_PAGE_SIZE = 40;
    public function index(Request $request): Response
    {
        $this->assertInboxAccess($request);
        MetaInstagramInboxSyncTrigger::maybeRunFromInboxRequest();
        MetaMessengerInboxSyncTrigger::maybeRunFromInboxRequest();

        $user = $request->user();
        $canSeeAll = OmnichannelAuthorization::canSeeAllChats($user);
        $inbox = $this->resolveInboxFilter($request, $canSeeAll);

        $leadStageFilter = $request->get('lead_stage');
        if ($leadStageFilter !== null && $leadStageFilter !== '' && ! OmniLeadStages::isValid((string) $leadStageFilter)) {
            $leadStageFilter = null;
        }

        $conversations = $this->queryInboxConversations($request, $inbox, $canSeeAll);

        $selectedId = $request->integer('conversation')
            ?: ($conversations->first()['id'] ?? null);

        $messages = [];
        $selectedConversation = null;
        $messagePage = ['has_more_older' => false, 'oldest_message_id' => null];

        if ($selectedId) {
            $conversation = OmniConversation::query()
                ->with([
                    'member',
                    'omniContact:id,display_name,avatar_url,marital_status,preferred_outlet_id,preferred_area',
                    'omniContact.preferredOutlet:id_outlet,nama_outlet,lokasi',
                    'assignee' => fn ($q) => $q->with(['jabatan', 'outlet']),
                    'assignees' => fn ($q) => $q->with(['jabatan', 'outlet'])->orderBy('nama_lengkap'),
                    'teams:id,name',
                    'activeFlowRun.flow:id,name',
                ])
                ->find($selectedId);
            if ($conversation && OmnichannelAuthorization::userCanAccessConversation($user, $conversation, $canSeeAll)) {
                $selectedConversation = $this->formatConversation($conversation);
                $messagePage = $this->loadMessagesPage($conversation);
                $messages = $messagePage['messages'];
                $conversation->update(['unread_count' => 0]);
            }
        }

        $assignableUsers = OmnichannelUserOption::assignableOptions();

        $assignableTeams = OmniTeam::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (OmniTeam $t) => [
                'id' => $t->id,
                'name' => $t->name,
            ]);

        return Inertia::render('Crm/OmnichannelInbox/Index', [
            'messageTemplates' => OmniMessageTemplate::listActiveForInbox(),
            'conversations' => $conversations,
            'selectedConversation' => $selectedConversation,
            'messages' => $messages,
            'messagesHasMoreOlder' => $messagePage['has_more_older'] ?? false,
            'messagesOldestId' => $messagePage['oldest_message_id'] ?? null,
            'inbox' => $inbox,
            'channelFilter' => $this->parseChannelFilter($request),
            'leadStageFilter' => $leadStageFilter,
            'leadStages' => OmniLeadStages::all(),
            'assignableUsers' => $assignableUsers,
            'assignableTeams' => $assignableTeams,
            'canSeeAllChats' => $canSeeAll,
            'canManageOmnichannelTeams' => OmnichannelAuthorization::userHasPermission((int) $request->user()->id, 'omnichannel_teams_view'),
            'canManageOmnichannelFlows' => OmnichannelAuthorization::userHasPermission((int) $request->user()->id, 'omnichannel_flows_view')
                || OmnichannelAuthorization::userHasPermission((int) $request->user()->id, 'omnichannel_teams_view'),
            'aiWritingEnabled' => filter_var(config('omnichannel.ai_writing.enabled', true), FILTER_VALIDATE_BOOLEAN),
            'composerSpellcheck' => filter_var(config('omnichannel.composer.spellcheck', true), FILTER_VALIDATE_BOOLEAN),
            'autoGrammarOnSendDefault' => filter_var(config('omnichannel.composer.auto_grammar_on_send', true), FILTER_VALIDATE_BOOLEAN),
            'autoGrammarMaxChars' => (int) config('omnichannel.composer.auto_grammar_max_chars', 2500),
            'autoGrammarMinChars' => (int) config('omnichannel.composer.auto_grammar_min_chars', 4),
            'maritalStatusOptions' => OmniContactMaritalStatus::options(),
            'outletOptions' => $this->outletOptionsForInbox(),
        ]);
    }

    /**
     * @return list<array{id: int, name: string, location: ?string}>
     */
    private function outletOptionsForInbox(): array
    {
        return DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->orderBy('nama_outlet')
            ->get(['id_outlet', 'nama_outlet', 'lokasi'])
            ->map(fn ($row) => [
                'id' => (int) $row->id_outlet,
                'name' => (string) $row->nama_outlet,
                'location' => $row->lokasi ? (string) $row->lokasi : null,
            ])
            ->values()
            ->all();
    }

    /**
     * Polling ringan (JSON) — daftar chat + pesan terbuka tanpa reload halaman Inertia.
     */
    public function pollSnapshot(Request $request): JsonResponse
    {
        $this->assertInboxAccess($request);
        MetaInstagramInboxSyncTrigger::maybeRunFromInboxRequest();
        MetaMessengerInboxSyncTrigger::maybeRunFromInboxRequest();

        $user = $request->user();
        $canSeeAll = OmnichannelAuthorization::canSeeAllChats($user);
        $inbox = $this->resolveInboxFilter($request, $canSeeAll);
        $conversations = $this->queryInboxConversations($request, $inbox, $canSeeAll);

        $selectedConversation = null;
        $messages = [];
        $messagePage = ['has_more_older' => false, 'oldest_message_id' => null];

        $selectedId = $request->integer('conversation') ?: null;
        if ($selectedId) {
            $conversation = OmniConversation::query()
                ->with([
                    'member',
                    'omniContact:id,display_name,avatar_url,marital_status,preferred_outlet_id,preferred_area',
                    'omniContact.preferredOutlet:id_outlet,nama_outlet,lokasi',
                    'assignee' => fn ($q) => $q->with(['jabatan', 'outlet']),
                    'assignees' => fn ($q) => $q->with(['jabatan', 'outlet'])->orderBy('nama_lengkap'),
                    'teams:id,name',
                    'activeFlowRun.flow:id,name',
                ])
                ->find($selectedId);
            if ($conversation && OmnichannelAuthorization::userCanAccessConversation($user, $conversation, $canSeeAll)) {
                $selectedConversation = $this->formatConversation($conversation);
                $pollRequest = Request::create('/', 'GET', [
                    'limit' => self::MESSAGES_PAGE_SIZE,
                    'no_enrich' => '1',
                ]);
                $messagePage = $this->loadMessagesPage($conversation, $pollRequest);
                $messages = $messagePage['messages'];
            }
        }

        return response()->json([
            'conversations' => $conversations->values()->all(),
            'selected_conversation' => $selectedConversation,
            'messages' => $messages,
            'has_more_older' => $messagePage['has_more_older'],
            'oldest_message_id' => $messagePage['oldest_message_id'],
            'can_see_all_chats' => $canSeeAll,
        ]);
    }

    /**
     * Data awal inbox untuk YMSoft App (approval-app API).
     */
    public function apiBootstrap(Request $request): JsonResponse
    {
        $this->assertInboxAccess($request);
        MetaInstagramInboxSyncTrigger::maybeRunFromInboxRequest();
        MetaMessengerInboxSyncTrigger::maybeRunFromInboxRequest();

        $user = $request->user();
        $canSeeAll = OmnichannelAuthorization::canSeeAllChats($user);
        $inbox = $this->resolveInboxFilter($request, $canSeeAll);

        $leadStageFilter = $request->get('lead_stage');
        if ($leadStageFilter !== null && $leadStageFilter !== '' && ! OmniLeadStages::isValid((string) $leadStageFilter)) {
            $leadStageFilter = null;
        }

        $conversations = $this->queryInboxConversations($request, $inbox, $canSeeAll);

        return response()->json([
            'success' => true,
            'data' => [
                'conversations' => $conversations,
                'inbox' => $inbox,
                'channel_filter' => $this->parseChannelFilter($request),
                'lead_stage_filter' => $leadStageFilter,
                'lead_stages' => OmniLeadStages::all(),
                'assignable_users' => OmnichannelUserOption::assignableOptions(),
                'assignable_teams' => OmniTeam::query()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (OmniTeam $t) => ['id' => $t->id, 'name' => $t->name])
                    ->values()
                    ->all(),
                'can_see_all_chats' => $canSeeAll,
                'message_templates' => OmniMessageTemplate::listActiveForInbox(),
                'marital_status_options' => OmniContactMaritalStatus::options(),
                'outlet_options' => $this->outletOptionsForInbox(),
                'ai_writing_enabled' => filter_var(config('omnichannel.ai_writing.enabled', true), FILTER_VALIDATE_BOOLEAN),
                'composer_spellcheck' => filter_var(config('omnichannel.composer.spellcheck', true), FILTER_VALIDATE_BOOLEAN),
                'auto_grammar_on_send_default' => filter_var(config('omnichannel.composer.auto_grammar_on_send', true), FILTER_VALIDATE_BOOLEAN),
                'auto_grammar_max_chars' => (int) config('omnichannel.composer.auto_grammar_max_chars', 2500),
                'auto_grammar_min_chars' => (int) config('omnichannel.composer.auto_grammar_min_chars', 4),
            ],
        ]);
    }

    public function update(Request $request, OmniConversation $conversation, OmniContactProfileService $contactProfile): JsonResponse
    {
        $this->assertInboxAccess($request);
        $user = $request->user();
        $canSeeAll = OmnichannelAuthorization::canSeeAllChats($user);
        abort_unless(
            OmnichannelAuthorization::userCanAccessConversation($user, $conversation, $canSeeAll),
            403
        );

        $validated = $request->validate([
            ...OmnichannelUserOption::assignableUserIdRules('assigned_user_ids'),
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
            'marital_status' => ['nullable', 'string', Rule::in(OmniContactMaritalStatus::values())],
            'preferred_outlet_id' => ['nullable', 'integer', 'exists:tbl_data_outlet,id_outlet'],
            'preferred_area' => ['nullable', 'string', 'max:255'],
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

        $contactProfilePayload = [];
        if (array_key_exists('marital_status', $validated)) {
            $contactProfilePayload['marital_status'] = $validated['marital_status'];
            unset($validated['marital_status']);
        }
        if (array_key_exists('preferred_outlet_id', $validated)) {
            $contactProfilePayload['preferred_outlet_id'] = $validated['preferred_outlet_id'];
            unset($validated['preferred_outlet_id']);
        }
        if (array_key_exists('preferred_area', $validated)) {
            $contactProfilePayload['preferred_area'] = $validated['preferred_area'];
            unset($validated['preferred_area']);
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

        if ($contactProfilePayload !== []) {
            try {
                $contactProfile->updateForConversation($conversation, $contactProfilePayload);
            } catch (RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

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
            'conversation' => $this->formatConversation($conversation->fresh([
                'member',
                'omniContact:id,display_name,avatar_url,marital_status,preferred_outlet_id,preferred_area',
                'omniContact.preferredOutlet:id_outlet,nama_outlet,lokasi',
                'assignee' => fn ($q) => $q->with(['jabatan', 'outlet']),
                'assignees' => fn ($q) => $q->with(['jabatan', 'outlet'])->orderBy('nama_lengkap'),
                'teams:id,name',
            ])),
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

        $page = $this->loadMessagesPage($conversation, $request);

        return response()->json([
            'conversation' => $this->formatConversation($conversation->load([
                'member',
                'assignee' => fn ($q) => $q->with(['jabatan', 'outlet']),
                'assignees' => fn ($q) => $q->with(['jabatan', 'outlet'])->orderBy('nama_lengkap'),
                'teams:id,name',
            ])),
            'messages' => $page['messages'],
            'has_more_older' => $page['has_more_older'],
            'oldest_message_id' => $page['oldest_message_id'],
        ]);
    }

    public function aiAssist(Request $request): JsonResponse
    {
        $this->assertInboxAccess($request);
        $user = $request->user();
        $canSeeAll = OmnichannelAuthorization::canSeeAllChats($user);

        $validated = $request->validate([
            'action' => [
                'required',
                'string',
                Rule::in([
                    'grammar',
                    'tone',
                    'translate_to_en',
                    'translate_to_id',
                    'simplify',
                    'expand',
                    'shorten',
                    'to_list',
                    'custom',
                ]),
            ],
            'text' => 'required|string|max:8000',
            'tone' => 'nullable|string|in:professional,friendly,formal,empathetic,casual',
            'list_style' => 'nullable|string|in:bullet,numbered',
            'custom_prompt' => 'nullable|string|max:2000',
            'conversation_id' => 'nullable|integer|exists:omni_conversations,id',
        ]);

        $context = null;
        if (
            $validated['action'] !== 'grammar'
            && ! empty($validated['conversation_id'])
            && config('omnichannel.ai_writing.include_context', true)
        ) {
            $conversation = OmniConversation::query()->find($validated['conversation_id']);
            if ($conversation && OmnichannelAuthorization::userCanAccessConversation($user, $conversation, $canSeeAll)) {
                $context = [
                    'contact_name' => $conversation->contact_name,
                    'channel' => $conversation->channel,
                    'recent_messages' => app(OmniAiWritingService::class)->recentMessageContext($conversation),
                ];
            }
        }

        try {
            $text = app(OmniAiWritingService::class)->transform(
                $validated['action'],
                $validated['text'],
                $validated['tone'] ?? null,
                $validated['list_style'] ?? 'bullet',
                $validated['custom_prompt'] ?? null,
                $context
            );

            return response()->json([
                'success' => true,
                'text' => $text,
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'AI Writing Assistant gagal. Coba lagi atau hubungi administrator.',
            ], 500);
        }
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
            'body' => ['nullable', 'string', 'max:4096'],
            'attachment' => ['nullable', 'file', 'max:16384'],
            'attachments' => ['nullable', 'array', 'max:'.OmnichannelInboxOutboundMediaService::MAX_ATTACHMENTS],
            'attachments.*' => ['file', 'max:16384'],
            'send_config' => ['nullable'],
        ]);

        $body = trim((string) ($validated['body'] ?? ''));
        $files = $this->collectOutboundAttachments($request);
        $file = $files[0] ?? null;
        $sendConfig = $this->parseSendConfig($request->input('send_config'));

        if (count($files) > 1) {
            return $this->sendMultipleOutboundAttachments($request, $conversation, $files, $body);
        }

        if ($body === '' && ! $file && ($sendConfig['message_mode'] ?? 'text') === 'text') {
            $hasMedia = trim((string) ($sendConfig['media_path'] ?? '')) !== '';
            if (! $hasMedia) {
                return response()->json(['message' => 'Pesan atau lampiran wajib diisi.'], 422);
            }
        }

        $channel = (string) $conversation->channel;
        $messageType = 'text';
        $preview = $body;
        $payload = [];
        $metaMessageId = '';

        try {
            if (in_array($channel, ['messenger', 'facebook', 'instagram'], true)) {
                if ($channel === 'instagram' && $this->useInstagramLoginApi()) {
                    $igClient = app(\App\Services\Meta\MetaInstagramLoginClient::class);
                    $recipient = $conversation->external_contact_id;

                    if ($file) {
                        $sent = app(OmnichannelInboxOutboundMediaService::class)->sendOutboundFile($conversation, $file);
                        $messageType = $sent['message_type'];
                        $preview = $body !== '' ? $body : $sent['preview'];
                        $payload = $sent['payload'];
                        $metaMessageId = $sent['meta_message_id'];

                        if ($body !== '') {
                            $igClient->sendText($recipient, $body, $conversation->phone_number_id);
                        }
                    } else {
                        if ($body === '') {
                            return response()->json(['message' => 'Pesan teks wajib diisi.'], 422);
                        }
                        $result = $igClient->sendText($recipient, $body, $conversation->phone_number_id);
                        $payload = is_array($result) ? $result : [];
                        $metaMessageId = (string) ($result['message_id'] ?? $result['messages'][0]['id'] ?? '');
                    }
                } else {
                    if ($file) {
                        return response()->json(['message' => 'Lampiran untuk Messenger (Page API) belum didukung. Gunakan channel Instagram Login atau kirim teks.'], 422);
                    }
                    if ($body === '') {
                        return response()->json(['message' => 'Pesan teks wajib diisi.'], 422);
                    }
                    $result = app(MetaMessengerClient::class)->sendText(
                        $conversation->external_contact_id,
                        $body,
                        $conversation->phone_number_id
                    );
                    $payload = is_array($result) ? $result : [];
                    $metaMessageId = (string) ($result['message_id'] ?? $result['messages'][0]['id'] ?? '');
                }
            } elseif ($channel === 'whatsapp') {
                $client = app(MetaWhatsAppClient::class);
                $templateMode = (string) ($sendConfig['message_mode'] ?? 'text');
                if (! $file && $templateMode !== 'text' && $sendConfig !== []) {
                    $sent = app(\App\Services\Omni\OmniWhatsappOutboundService::class)->send(
                        $conversation,
                        $body,
                        $sendConfig
                    );
                    $messageType = $sent['message_type'];
                    $preview = $sent['body'];
                    $payload = $sent['payload'];
                    $metaMessageId = $sent['meta_message_id'];
                } elseif ($file) {
                    $sent = app(OmnichannelInboxOutboundMediaService::class)->sendOutboundFile(
                        $conversation,
                        $file,
                        $body !== '' ? $body : null
                    );
                    $messageType = $sent['message_type'];
                    $preview = $sent['preview'];
                    $payload = $sent['payload'];
                    $metaMessageId = $sent['meta_message_id'];
            } else {
                $result = $client->sendText(
                    $conversation->external_contact_id,
                    $body,
                    $conversation->phone_number_id
                );
                $payload = is_array($result) ? $result : [];
                $metaMessageId = (string) ($result['messages'][0]['id'] ?? '');
                }
            } else {
                return response()->json(['message' => 'Channel belum didukung.'], 422);
            }
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $sentAt = now();

        $message = OmniMessage::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'direction' => 'outbound',
            'meta_message_id' => $metaMessageId !== '' ? $metaMessageId : null,
            'message_type' => $messageType,
            'body' => $body !== '' ? $body : ($preview ?: ''),
            'payload' => $payload,
            'status' => 'sent',
            'sent_at' => $sentAt,
        ]);

        $conversation->update([
            'last_message_at' => $sentAt,
            'last_message_preview' => mb_substr($preview, 0, 500),
        ]);

        return response()->json([
            'message' => $this->formatMessage($message->load('author:id,nama_lengkap,email')),
        ]);
    }

    public function pauseAutomation(Request $request, OmniConversation $conversation): JsonResponse
    {
        $this->assertInboxAccess($request);
        $user = $request->user();
        $canSeeAll = OmnichannelAuthorization::canSeeAllChats($user);
        abort_unless(
            OmnichannelAuthorization::userCanAccessConversation($user, $conversation, $canSeeAll),
            403
        );

        $conversation->automation_paused = true;
        $conversation->save();

        if ($conversation->active_flow_run_id) {
            OmniFlowRun::query()
                ->where('id', $conversation->active_flow_run_id)
                ->where('status', 'running')
                ->update([
                    'status' => 'cancelled',
                    'error_message' => 'Dihentikan oleh pengguna',
                    'finished_at' => now(),
                ]);
            $conversation->active_flow_run_id = null;
            $conversation->save();
        }

        return response()->json([
            'conversation' => $this->formatConversation($conversation->fresh([
                'member',
                'assignee' => fn ($q) => $q->with(['jabatan', 'outlet']),
                'assignees' => fn ($q) => $q->with(['jabatan', 'outlet'])->orderBy('nama_lengkap'),
                'teams:id,name',
                'activeFlowRun.flow:id,name',
            ])),
        ]);
    }

    public function storeInternalNote(
        Request $request,
        OmniConversation $conversation,
        OmniInternalNoteMentionService $mentionService
    ): JsonResponse {
        $this->assertInboxAccess($request);
        $user = $request->user();
        $canSeeAll = OmnichannelAuthorization::canSeeAllChats($user);
        abort_unless(
            OmnichannelAuthorization::userCanAccessConversation($user, $conversation, $canSeeAll),
            403
        );

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:8192'],
            'attachment' => ['nullable', 'file', 'max:16384'],
            'attachments' => ['nullable', 'array', 'max:'.OmnichannelInboxOutboundMediaService::MAX_ATTACHMENTS],
            'attachments.*' => ['file', 'max:16384'],
            'mentioned_user_ids' => ['nullable', 'array'],
            'mentioned_user_ids.*' => ['integer'],
        ]);

        $body = trim((string) ($validated['body'] ?? ''));
        $files = $this->collectOutboundAttachments($request);
        if ($body === '' && $files === []) {
            return response()->json(['message' => 'Catatan atau lampiran wajib diisi.'], 422);
        }

        $mentionedIds = $mentionService->resolveMentionedUserIds(
            array_values($validated['mentioned_user_ids'] ?? [])
        );

        $sentAt = now();
        $created = [];
        $lastPreview = $body;

        if ($files === []) {
            $message = $this->createInternalNoteMessage(
                $conversation,
                $request->user()->id,
                $body,
                null,
                $mentionedIds,
                $sentAt
            );
            $created[] = $message;
            $lastPreview = $body;
        } else {
            foreach ($files as $index => $file) {
                $noteBody = $index === 0 ? $body : '';
                $message = $this->createInternalNoteMessage(
                    $conversation,
                    $request->user()->id,
                    $noteBody,
                    $file,
                    $index === 0 ? $mentionedIds : [],
                    $sentAt
                );
                $created[] = $message;
                $lastPreview = $message->body;
            }
        }

        $conversation->update([
            'last_message_at' => $sentAt,
            'last_message_preview' => mb_substr('[Catatan] '.$lastPreview, 0, 500),
        ]);

        if ($mentionedIds !== []) {
            $mentionService->applyMentions($conversation, $mentionedIds, $user, $lastPreview);
        }

        $formatted = array_map(
            fn (OmniMessage $m) => $this->formatMessage($m->load('author:id,nama_lengkap,email'), $conversation),
            $created
        );

        $response = [
            'messages' => $formatted,
            'message' => $formatted[count($formatted) - 1] ?? null,
        ];

        if ($mentionedIds !== []) {
            $response['conversation'] = $this->formatConversation($conversation->fresh([
                'member',
                'omniContact:id,display_name,avatar_url,marital_status,preferred_outlet_id,preferred_area',
                'omniContact.preferredOutlet:id_outlet,nama_outlet,lokasi',
                'assignee' => fn ($q) => $q->with(['jabatan', 'outlet']),
                'assignees' => fn ($q) => $q->with(['jabatan', 'outlet'])->orderBy('nama_lengkap'),
                'teams:id,name',
            ]));
        }

        return response()->json($response);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function resolveInboxFilter(Request $request, bool $canSeeAll): string
    {
        $inbox = (string) $request->get('inbox', $canSeeAll ? 'all' : 'mine');
        if (! in_array($inbox, ['all', 'mine', 'unassigned'], true)) {
            return $canSeeAll ? 'all' : 'mine';
        }

        if (! $canSeeAll && $inbox === 'unassigned') {
            return 'mine';
        }

        return $inbox;
    }

    private function queryInboxConversations(
        Request $request,
        ?string $inbox = null,
        ?bool $canSeeAll = null
    ): \Illuminate\Support\Collection {
        $user = $request->user();
        $canSeeAll ??= OmnichannelAuthorization::canSeeAllChats($user);
        $inbox ??= $this->resolveInboxFilter($request, $canSeeAll);

        $leadStageFilter = $request->get('lead_stage');
        if ($leadStageFilter !== null && $leadStageFilter !== '' && ! OmniLeadStages::isValid((string) $leadStageFilter)) {
            $leadStageFilter = null;
        }

        $query = OmniConversation::query()
            ->with([
                'member:id,nama_lengkap,mobile_phone,member_id,member_level,is_exclusive_member',
                'omniContact:id,display_name,avatar_url',
                'assignee' => fn ($q) => $q->with(['jabatan', 'outlet']),
                'assignees' => fn ($q) => $q->with(['jabatan', 'outlet'])->orderBy('nama_lengkap'),
                'teams:id,name',
            ]);

        OmnichannelAuthorization::applyInboxVisibility($query, $user, $inbox, $canSeeAll);

        if ($leadStageFilter) {
            $query->where('lead_stage', $leadStageFilter);
        }

        $this->applyChannelFilter($query, $this->parseChannelFilter($request));

        return $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(150)
            ->get()
            ->map(fn (OmniConversation $c) => $this->formatConversation($c));
    }

    /**
     * @return null|'whatsapp'|'instagram'|'messenger'
     */
    private function parseChannelFilter(Request $request): ?string
    {
        $channel = $request->get('channel');
        if ($channel === null || $channel === '' || $channel === 'all') {
            return null;
        }

        $allowed = ['whatsapp', 'instagram', 'messenger'];
        if (! in_array($channel, $allowed, true)) {
            return null;
        }

        return $channel;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<OmniConversation>  $query
     */
    private function applyChannelFilter($query, ?string $channel): void
    {
        if ($channel === null) {
            return;
        }

        if ($channel === 'messenger') {
            $query->whereIn('channel', ['messenger', 'facebook']);

            return;
        }

        $query->where('channel', $channel);
    }

    private function assertInboxAccess(Request $request): void
    {
        abort_unless(OmnichannelAuthorization::canViewInbox($request->user()), 403);
    }

    /**
     * Muat pesan per halaman (terbaru dulu). Scroll ke atas → before_id untuk riwayat lama.
     *
     * @return array{messages: list<array<string, mixed>>, has_more_older: bool, oldest_message_id: int|null}
     */
    private function loadMessagesPage(OmniConversation $conversation, ?Request $request = null): array
    {
        $limit = min(max((int) ($request?->input('limit', self::MESSAGES_PAGE_SIZE)), 10), 80);
        $beforeId = $request?->integer('before_id') ?: null;
        $enrichMedia = ! filter_var($request?->input('no_enrich', false), FILTER_VALIDATE_BOOLEAN);
        $query = $conversation->messages()->with('author:id,nama_lengkap,email');
        if ($beforeId) {
            $query->where('id', '<', $beforeId);
        }

        $rows = $query
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();
        $hasMore = $rows->count() > $limit;
        if ($hasMore) {
            $rows = $rows->take($limit);
        }
        $rows = $rows->reverse()->values();

        $media = app(OmnichannelInboxMediaService::class);
        $enriched = 0;
        $maxEnrich = $enrichMedia ? ($beforeId ? 0 : 6) : 0;

        $messages = $rows->map(function (OmniMessage $m) use ($conversation, $media, &$enriched, $maxEnrich) {
            if ($enriched < $maxEnrich && $media->needsResolve($m)) {
                $media->ensureCached($m, $conversation);
                $m->refresh();
                if ($media->needsResolve($m)) {
                    $media->attemptChannelRepair($m, $conversation);
                    $m->refresh();
                }
                $enriched++;
            }

            return $this->formatMessage($m, $conversation);
        })->all();

        $oldestId = $rows->first()?->id;

        return [
            'messages' => $messages,
            'has_more_older' => $hasMore,
            'oldest_message_id' => $oldestId !== null ? (int) $oldestId : null,
        ];
    }

    /**
     * Muat / cache ulang lampiran satu pesan (untuk pesan lama yang masih [Gambar]).
     */
    public function messageMedia(Request $request, OmniMessage $message): JsonResponse
    {
        $this->assertInboxAccess($request);
        $user = $request->user();
        $conversation = $message->conversation;
        if (! $conversation) {
            abort(404);
        }
        $canSeeAll = OmnichannelAuthorization::canSeeAllChats($user);
        abort_unless(
            OmnichannelAuthorization::userCanAccessConversation($user, $conversation, $canSeeAll),
            403
        );
        $media = app(OmnichannelInboxMediaService::class);
        $media->ensureCached($message, $conversation);
        $message->refresh();

        $url = $media->clientSafeMediaUrl($message, $conversation);
        if ($url === null) {
            $media->attemptChannelRepair($message, $conversation);
            $message->refresh();
            $url = $media->clientSafeMediaUrl($message, $conversation);
        }

        return response()->json([
            'success' => $url !== null,
            'media_url' => $url,
            'message' => $this->formatMessage($message, $conversation),
        ]);
    }

    private function formatConversation(OmniConversation $conversation): array
    {
        $assignees = [];
        if ($conversation->relationLoaded('assignees')) {
            $assignees = OmnichannelUserOption::toOptions($conversation->assignees);
        }

        $assignedTeams = [];
        if ($conversation->relationLoaded('teams')) {
            $assignedTeams = $conversation->teams->map(fn (OmniTeam $t) => [
                'id' => $t->id,
                'name' => $t->name,
            ])->values()->all();
        }

        $omniContact = $conversation->relationLoaded('omniContact') ? $conversation->omniContact : null;
        $resolvedName = $conversation->contact_name ?: $omniContact?->display_name;

        $channelAccountId = (string) ($conversation->phone_number_id ?? '');
        $channelAccountLabel = $this->resolveChannelAccountLabel($conversation);

        return [
            'id' => $conversation->id,
            'channel' => $conversation->channel,
            'external_contact_id' => $conversation->external_contact_id,
            'contact_name' => $resolvedName,
            'contact_avatar_url' => $omniContact?->avatar_url,
            'channel_account_id' => $channelAccountId !== '' ? $channelAccountId : null,
            'channel_account_label' => $channelAccountLabel,
            'display_phone' => $this->formatContactDisplayId($conversation),
            'display_phone_international' => $this->formatContactDisplayId($conversation),
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
            'assignee' => $conversation->assignee
                ? OmnichannelUserOption::toArray($conversation->assignee)
                : null,
            'member' => $conversation->member ? [
                'id' => $conversation->member->id,
                'nama_lengkap' => $conversation->member->nama_lengkap,
                'mobile_phone' => $conversation->member->mobile_phone,
                'member_id' => $conversation->member->member_id,
                'member_level' => $conversation->member->member_level,
                'is_exclusive_member' => (bool) $conversation->member->is_exclusive_member,
            ] : null,
            'contact_profile' => $this->formatContactProfile($omniContact),
            'automation_paused' => (bool) $conversation->automation_paused,
            'active_flow' => $conversation->relationLoaded('activeFlowRun') && $conversation->activeFlowRun
                ? [
                    'run_id' => $conversation->activeFlowRun->id,
                    'status' => $conversation->activeFlowRun->status,
                    'flow_name' => $conversation->activeFlowRun->flow?->name,
                ]
                : null,
        ];
    }

    /**
     * @return array{
     *   marital_status: ?string,
     *   marital_status_label: ?string,
     *   preferred_outlet_id: ?int,
     *   preferred_outlet_name: ?string,
     *   preferred_area: ?string
     * }
     */
    private function formatContactProfile(?OmniContact $contact): array
    {
        if (! $contact) {
            return [
                'marital_status' => null,
                'marital_status_label' => null,
                'preferred_outlet_id' => null,
                'preferred_outlet_name' => null,
                'preferred_area' => null,
            ];
        }

        $outlet = $contact->relationLoaded('preferredOutlet') ? $contact->preferredOutlet : null;

        return [
            'marital_status' => $contact->marital_status,
            'marital_status_label' => OmniContactMaritalStatus::label($contact->marital_status),
            'preferred_outlet_id' => $contact->preferred_outlet_id ? (int) $contact->preferred_outlet_id : null,
            'preferred_outlet_name' => $outlet?->nama_outlet,
            'preferred_area' => $contact->preferred_area,
        ];
    }

    private function formatMessage(OmniMessage $message, ?OmniConversation $conversation = null): array
    {
        $authorName = null;
        if ($message->relationLoaded('author') && $message->author) {
            $authorName = $message->author->nama_lengkap ?? $message->author->email;
        } elseif ($message->direction === 'outbound' && $message->user_id === null) {
            $authorName = 'Otomasi';
        }

        $payload = is_array($message->payload) ? $message->payload : [];
        $mediaUrl = app(OmnichannelInboxMediaService::class)->clientSafeMediaUrl($message, $conversation);

        $messageType = (string) $message->message_type;
        $mediaMime = (string) ($payload['media_mime'] ?? '');
        if (OmniMetaMessagePayload::isVideoLike($payload, $messageType, $mediaMime)) {
            $messageType = 'video';
        } elseif ($mediaUrl !== null && ($messageType === 'attachment' || $messageType === 'text')
            && ($mediaMime === '' || str_starts_with($mediaMime, 'image/'))) {
            $looksLikeImage = $mediaMime !== '' && str_starts_with($mediaMime, 'image/')
                || preg_match('/\.(jpe?g|png|gif|webp)(\?|$)/i', $mediaUrl) === 1;
            if ($looksLikeImage) {
                $messageType = 'image';
            }
        } elseif ($mediaUrl !== null && ($messageType === 'attachment' || $messageType === 'text')) {
            if (preg_match('/\.(mp4|webm|mov|m4v)(\?|$)/i', $mediaUrl) === 1) {
                $messageType = 'video';
            }
        }

        $mentionedUsers = app(OmniInternalNoteMentionService::class)->mentionedUsersFromPayload($payload);
        $storyReply = OmniInstagramStoryReply::fromPayload($payload);
        if ($storyReply !== null && $messageType === 'text') {
            $messageType = 'story_reply';
        }

        return [
            'id' => $message->id,
            'direction' => $message->direction,
            'message_type' => $messageType,
            'body' => $message->body,
            'status' => $message->status,
            'sent_at' => ($message->sent_at ?? $message->created_at)?->toIso8601String(),
            'author_name' => $authorName,
            'media_url' => $mediaUrl,
            'media_filename' => $payload['media_filename'] ?? null,
            'media_mime' => $payload['media_mime'] ?? null,
            'mentioned_users' => $mentionedUsers,
            'story_reply' => $storyReply,
        ];
    }

    private function resolveChannelAccountLabel(OmniConversation $conversation): ?string
    {
        $accountId = (string) ($conversation->phone_number_id ?? '');
        if ($accountId === '') {
            return null;
        }

        return match ((string) $conversation->channel) {
            'instagram' => MetaInstagramAccountRegistry::displayLabel($accountId),
            'messenger', 'facebook' => MetaPageAccountRegistry::displayLabel($accountId),
            default => null,
        };
    }

    private function formatContactDisplayId(OmniConversation $conversation): string
    {
        $id = (string) $conversation->external_contact_id;

        return match ((string) $conversation->channel) {
            'instagram' => 'Instagram · '.$id,
            'messenger', 'facebook' => 'Messenger · '.(strlen($id) > 8 ? '…'.substr($id, -8) : $id),
            default => $this->formatDisplayPhone($id),
        };
    }

    private function formatDisplayPhone(string $waId): string
    {
        if (str_starts_with($waId, '62')) {
            return '0'.substr($waId, 2);
        }

        return $waId;
    }

    private function formatInternationalPhone(string $waId): string
    {
        $digits = preg_replace('/\D/', '', $waId) ?? '';
        if ($digits === '') {
            return $waId;
        }
        if (str_starts_with($digits, '62')) {
            return '+'.$digits;
        }
        if (str_starts_with($digits, '0')) {
            return '+62'.substr($digits, 1);
        }

        return '+'.$digits;
    }

    private function useInstagramLoginApi(): bool
    {
        return MetaInstagramTokens::resolved() !== []
            || (string) config('services.meta.instagram_login_access_token') !== '';
    }

    private function publicStorageUrl(string $storedPath): string
    {
        $relative = Storage::disk('public')->url($storedPath);

        return str_starts_with($relative, 'http')
            ? $relative
            : url($relative);
    }

    /**
     * @param  list<int>  $mentionedIds
     */
    private function createInternalNoteMessage(
        OmniConversation $conversation,
        int $userId,
        string $body,
        ?UploadedFile $file,
        array $mentionedIds,
        \Illuminate\Support\Carbon $sentAt
    ): OmniMessage {
        $messageType = 'note';
        $payload = null;
        $preview = $body;

        if ($file) {
            $mime = $file->getMimeType() ?: 'application/octet-stream';
            $storedPath = $file->store("omni-internal/{$conversation->id}", 'public');
            $messageType = str_starts_with($mime, 'image/') ? 'image' : 'document';
            $preview = $body !== '' ? $body : ($messageType === 'image' ? '[Gambar]' : '[Lampiran: '.$file->getClientOriginalName().']');
            $payload = [
                'local_media_url' => Storage::disk('public')->url($storedPath),
                'media_filename' => $file->getClientOriginalName(),
                'media_mime' => $mime,
            ];
        }

        if ($mentionedIds !== []) {
            $payload = is_array($payload) ? $payload : [];
            $payload['mentioned_user_ids'] = $mentionedIds;
        }

        return OmniMessage::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $userId,
            'direction' => 'internal',
            'meta_message_id' => null,
            'message_type' => $messageType,
            'body' => $body !== '' ? $body : $preview,
            'payload' => $payload,
            'status' => null,
            'sent_at' => $sentAt,
        ]);
    }

    /**
     * @return list<UploadedFile>
     */
    private function collectOutboundAttachments(Request $request): array
    {
        $files = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $uploaded) {
                if ($uploaded instanceof UploadedFile) {
                    $files[] = $uploaded;
                }
            }
        }
        if ($request->hasFile('attachment')) {
            $files[] = $request->file('attachment');
        }

        return array_slice($files, 0, OmnichannelInboxOutboundMediaService::MAX_ATTACHMENTS);
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    private function sendMultipleOutboundAttachments(
        Request $request,
        OmniConversation $conversation,
        array $files,
        string $body
    ): JsonResponse {
        $sendConfig = $this->parseSendConfig($request->input('send_config'));
        if (($sendConfig['message_mode'] ?? 'text') !== 'text' && $sendConfig !== []) {
            return response()->json([
                'message' => 'Template WA (tombol/lampiran) tidak bisa digabung dengan beberapa gambar sekaligus.',
            ], 422);
        }

        $channel = (string) $conversation->channel;

        foreach ($files as $file) {
            $mime = $file->getMimeType() ?: '';
            if (! str_starts_with($mime, 'image/')) {
                return response()->json([
                    'message' => 'Kirim beberapa file sekaligus hanya untuk gambar. Untuk PDF atau dokumen, kirim satu per satu.',
                ], 422);
            }
        }

        if ($channel === 'whatsapp' || ($channel === 'instagram' && $this->useInstagramLoginApi())) {
            // supported
        } else {
            return response()->json([
                'message' => 'Kirim beberapa gambar sekaligus belum didukung untuk channel ini.',
            ], 422);
        }

        $mediaService = app(OmnichannelInboxOutboundMediaService::class);
        $sentAt = now();
        $created = [];
        $lastPreview = $body !== '' ? $body : '[Gambar]';
        $caption = $body;

        try {
            foreach ($files as $index => $file) {
                $cap = $index === 0 && $channel === 'whatsapp' ? ($caption !== '' ? $caption : null) : null;
                $sent = $mediaService->sendOutboundFile($conversation, $file, $cap);
                $msgBody = $sent['body'] !== '' ? $sent['body'] : ($cap ?? '');
                $message = OmniMessage::query()->create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $request->user()->id,
                    'direction' => 'outbound',
                    'meta_message_id' => $sent['meta_message_id'] !== '' ? $sent['meta_message_id'] : null,
                    'message_type' => $sent['message_type'],
                    'body' => $msgBody !== '' ? $msgBody : $sent['preview'],
                    'payload' => $sent['payload'],
                    'status' => 'sent',
                    'sent_at' => $sentAt,
                ]);
                $created[] = $this->formatMessage($message->load('author:id,nama_lengkap,email'));
                $lastPreview = $sent['preview'];
            }

            if ($channel === 'instagram' && $this->useInstagramLoginApi() && $body !== '') {
                $igClient = app(\App\Services\Meta\MetaInstagramLoginClient::class);
                $igClient->sendText(
                    $conversation->external_contact_id,
                    $body,
                    $conversation->phone_number_id
                );
            }
        } catch (RuntimeException $e) {
            if ($created !== []) {
                return response()->json([
                    'message' => 'Sebagian gambar terkirim, lalu gagal: '.$e->getMessage(),
                    'messages' => $created,
                ], 502);
            }

            return response()->json(['message' => $e->getMessage()], 422);
        }

        $conversation->update([
            'last_message_at' => $sentAt,
            'last_message_preview' => mb_substr($lastPreview, 0, 500),
        ]);

        return response()->json([
            'messages' => $created,
            'message' => $created[count($created) - 1] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseSendConfig(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
