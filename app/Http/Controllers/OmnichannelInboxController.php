<?php

namespace App\Http\Controllers;

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
use App\Support\OmniLeadStages;
use App\Services\Omni\OmnichannelInboxMediaService;
use App\Support\OmnichannelAuthorization;
use App\Support\OmnichannelUserOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class OmnichannelInboxController extends Controller
{
    public function index(Request $request): Response
    {
        $this->assertInboxAccess($request);
        MetaInstagramInboxSyncTrigger::maybeRunFromInboxRequest();
        MetaMessengerInboxSyncTrigger::maybeRunFromInboxRequest();

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

        $conversations = $this->queryInboxConversations($request);

        $selectedId = $request->integer('conversation')
            ?: ($conversations->first()['id'] ?? null);

        $messages = [];
        $selectedConversation = null;

        if ($selectedId) {
            $conversation = OmniConversation::query()
                ->with([
                    'member',
                    'omniContact:id,display_name,avatar_url',
                    'assignee' => fn ($q) => $q->with(['jabatan', 'outlet']),
                    'assignees' => fn ($q) => $q->with(['jabatan', 'outlet'])->orderBy('nama_lengkap'),
                    'teams:id,name',
                    'activeFlowRun.flow:id,name',
                ])
                ->find($selectedId);
            if ($conversation && OmnichannelAuthorization::userCanAccessConversation($user, $conversation, $canSeeAll)) {
                $selectedConversation = $this->formatConversation($conversation);
                $messages = $this->loadMessages($conversation);
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
        ]);
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
        $conversations = $this->queryInboxConversations($request);

        $selectedConversation = null;
        $messages = [];

        $selectedId = $request->integer('conversation') ?: null;
        if ($selectedId) {
            $conversation = OmniConversation::query()
                ->with([
                    'member',
                    'omniContact:id,display_name,avatar_url',
                    'assignee' => fn ($q) => $q->with(['jabatan', 'outlet']),
                    'assignees' => fn ($q) => $q->with(['jabatan', 'outlet'])->orderBy('nama_lengkap'),
                    'teams:id,name',
                    'activeFlowRun.flow:id,name',
                ])
                ->find($selectedId);
            if ($conversation && OmnichannelAuthorization::userCanAccessConversation($user, $conversation, $canSeeAll)) {
                $selectedConversation = $this->formatConversation($conversation);
                $messages = $this->loadMessages($conversation);
            }
        }

        return response()->json([
            'conversations' => $conversations->values()->all(),
            'selected_conversation' => $selectedConversation,
            'messages' => $messages,
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

        $inbox = $request->get('inbox', 'all');
        if (! in_array($inbox, ['all', 'mine', 'unassigned'], true)) {
            $inbox = 'all';
        }

        $leadStageFilter = $request->get('lead_stage');
        if ($leadStageFilter !== null && $leadStageFilter !== '' && ! OmniLeadStages::isValid((string) $leadStageFilter)) {
            $leadStageFilter = null;
        }

        $conversations = $this->queryInboxConversations($request);

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
            ],
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
            'assigned_user_ids' => OmnichannelUserOption::assignableUserIdRules(),
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
            'conversation' => $this->formatConversation($conversation->fresh([
                'member',
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

        return response()->json([
            'conversation' => $this->formatConversation($conversation->load([
                'member',
                'assignee' => fn ($q) => $q->with(['jabatan', 'outlet']),
                'assignees' => fn ($q) => $q->with(['jabatan', 'outlet'])->orderBy('nama_lengkap'),
                'teams:id,name',
            ])),
            'messages' => $this->loadMessages($conversation),
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
        if (! empty($validated['conversation_id']) && config('omnichannel.ai_writing.include_context', true)) {
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
        ]);

        $body = trim((string) ($validated['body'] ?? ''));
        $file = $request->file('attachment');

        if ($body === '' && ! $file) {
            return response()->json(['message' => 'Pesan atau lampiran wajib diisi.'], 422);
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
                        $mime = $file->getMimeType() ?: 'application/octet-stream';
                        $storedPath = $file->store("omni-outbound/{$conversation->id}", 'public');
                        $publicUrl = $this->publicStorageUrl($storedPath);
                        $isImage = str_starts_with($mime, 'image/');

                        if ($isImage) {
                            if ($file->getSize() > 8 * 1024 * 1024) {
                                return response()->json(['message' => 'Gambar untuk Instagram maksimal 8 MB.'], 422);
                            }
                            if (! in_array($mime, ['image/jpeg', 'image/jpg', 'image/png'], true)) {
                                return response()->json(['message' => 'Instagram mendukung gambar PNG atau JPEG.'], 422);
                            }
                            $result = $igClient->sendImage($recipient, $publicUrl, $conversation->phone_number_id);
                            $messageType = 'image';
                            $preview = $body !== '' ? $body : '[Gambar]';
                        } elseif ($mime === 'application/pdf') {
                            if ($file->getSize() > 25 * 1024 * 1024) {
                                return response()->json(['message' => 'PDF untuk Instagram maksimal 25 MB.'], 422);
                            }
                            $result = $igClient->sendFile($recipient, $publicUrl, $conversation->phone_number_id);
                            $messageType = 'document';
                            $preview = $body !== '' ? $body : '[PDF: '.$file->getClientOriginalName().']';
                        } else {
                            return response()->json([
                                'message' => 'Lampiran Instagram: gambar (PNG/JPEG) atau PDF. Video/audio belum didukung di inbox.',
                            ], 422);
                        }

                        $payload = array_merge(is_array($result) ? $result : [], [
                            'local_media_url' => $publicUrl,
                            'media_filename' => $file->getClientOriginalName(),
                            'media_mime' => $mime,
                        ]);
                        $metaMessageId = (string) ($result['message_id'] ?? '');

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
                if ($file) {
                $mime = $file->getMimeType() ?: 'application/octet-stream';
                $storedPath = $file->store("omni-outbound/{$conversation->id}", 'public');
                $localUrl = Storage::disk('public')->url($storedPath);
                $absolutePath = Storage::disk('public')->path($storedPath);
                $mediaId = $client->uploadMedia($absolutePath, $mime, $conversation->phone_number_id);
                $isImage = str_starts_with($mime, 'image/');

                if ($isImage) {
                    $result = $client->sendImage(
                        $conversation->external_contact_id,
                        $mediaId,
                        $body !== '' ? $body : null,
                        $conversation->phone_number_id
                    );
                    $messageType = 'image';
                    $preview = $body !== '' ? $body : '[Gambar]';
                } else {
                    $result = $client->sendDocument(
                        $conversation->external_contact_id,
                        $mediaId,
                        $body !== '' ? $body : null,
                        $file->getClientOriginalName(),
                        $conversation->phone_number_id
                    );
                    $messageType = 'document';
                    $preview = $body !== '' ? $body : '[Lampiran: '.$file->getClientOriginalName().']';
                }

                $payload = array_merge(is_array($result) ? $result : [], [
                    'local_media_url' => $localUrl,
                    'media_filename' => $file->getClientOriginalName(),
                    'media_mime' => $mime,
                ]);
                $metaMessageId = (string) ($result['messages'][0]['id'] ?? '');
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
            'body' => ['nullable', 'string', 'max:8192'],
            'attachment' => ['nullable', 'file', 'max:16384'],
        ]);

        $body = trim((string) ($validated['body'] ?? ''));
        $file = $request->file('attachment');
        if ($body === '' && ! $file) {
            return response()->json(['message' => 'Catatan atau lampiran wajib diisi.'], 422);
        }

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

        $sentAt = now();

        $message = OmniMessage::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'direction' => 'internal',
            'meta_message_id' => null,
            'message_type' => $messageType,
            'body' => $body !== '' ? $body : $preview,
            'payload' => $payload,
            'status' => null,
            'sent_at' => $sentAt,
        ]);

        $conversation->update([
            'last_message_at' => $sentAt,
            'last_message_preview' => mb_substr('[Catatan] '.$preview, 0, 500),
        ]);

        return response()->json([
            'message' => $this->formatMessage($message->load('author:id,nama_lengkap,email')),
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function queryInboxConversations(Request $request): \Illuminate\Support\Collection
    {
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

    private function loadMessages(OmniConversation $conversation): array
    {
        $media = app(OmnichannelInboxMediaService::class);
        $enriched = 0;
        $maxEnrich = 20;

        return $conversation->messages()
            ->with('author:id,nama_lengkap,email')
            ->orderBy('sent_at')
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->map(function (OmniMessage $m) use ($conversation, $media, &$enriched, $maxEnrich) {
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
            })
            ->all();
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
        if ($mediaUrl !== null && ($messageType === 'attachment' || $messageType === 'text')
            && ($mediaMime === '' || str_starts_with($mediaMime, 'image/'))) {
            $looksLikeImage = $mediaMime !== '' && str_starts_with($mediaMime, 'image/')
                || preg_match('/\.(jpe?g|png|gif|webp)(\?|$)/i', $mediaUrl) === 1;
            if ($looksLikeImage) {
                $messageType = 'image';
            }
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
}
