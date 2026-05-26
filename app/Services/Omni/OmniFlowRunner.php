<?php

namespace App\Services\Omni;

use App\Models\OmniConversation;
use App\Models\OmniFlow;
use App\Models\OmniFlowRun;
use App\Models\OmniFlowRunLog;
use App\Models\OmniMessage;
use App\Models\OmniTeam;
use App\Models\User;
use App\Services\Meta\MetaWhatsAppClient;
use App\Services\NotificationService;
use App\Support\MetaInstagramTokens;
use App\Support\OmniFlowDefinition;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OmniFlowRunner
{
    public function dispatchForInboundMessage(int $conversationId, int $messageId): void
    {
        $conversation = OmniConversation::query()->find($conversationId);
        $message = OmniMessage::query()->find($messageId);

        if (! $conversation || ! $message || $message->direction !== 'inbound') {
            return;
        }

        if ($conversation->automation_paused) {
            return;
        }

        if (OmniFlowRun::query()
            ->where('conversation_id', $conversationId)
            ->where('status', 'running')
            ->exists()) {
            return;
        }

        $flows = OmniFlow::query()
            ->where('is_active', true)
            ->where('trigger_type', 'inbound_message')
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        foreach ($flows as $flow) {
            if ($flow->channel && $flow->channel !== $conversation->channel) {
                continue;
            }

            if ($this->tryStartFlow($flow, $conversation, $message)) {
                return;
            }
        }
    }

    public function run(OmniFlowRun $run): void
    {
        $run->load(['flow', 'conversation.member', 'conversation.assignees', 'conversation.teams', 'triggerMessage']);

        $flow = $run->flow;
        $conversation = $run->conversation;

        if (! $flow || ! $conversation || $conversation->automation_paused) {
            $this->finishRun($run, 'cancelled', 'Otomasi dijeda atau data tidak valid');

            return;
        }

        $definition = is_array($flow->definition) ? $flow->definition : [];
        if (OmniFlowDefinition::isGraph($definition)) {
            $this->runGraph($run, $flow, $conversation, $definition);

            return;
        }

        $steps = $flow->steps();
        if ($steps === []) {
            $this->finishRun($run, 'failed', 'Flow tanpa langkah');

            return;
        }

        $context = is_array($run->context) ? $run->context : [];
        $message = $run->triggerMessage;

        for ($i = (int) $run->current_step_index; $i < count($steps); $i++) {
            $step = $steps[$i];
            $type = (string) ($step['type'] ?? '');
            $config = is_array($step['config'] ?? null) ? $step['config'] : [];

            $run->update(['current_step_index' => $i]);

            try {
                if ($type === 'condition') {
                    $passed = $this->evaluateCondition($config, $conversation, $message);
                    $this->logStep($run, $i, $type, $passed ? 'ok' : 'skipped', $passed ? 'Kondisi terpenuhi' : 'Kondisi tidak terpenuhi — flow berhenti');
                    if (! $passed) {
                        $this->finishRun($run, 'completed', 'Berhenti di kondisi');

                        return;
                    }
                } elseif ($type === 'send_message') {
                    $this->executeSendMessage($config, $conversation, $context);
                    $this->logStep($run, $i, $type, 'ok', 'Pesan terkirim');
                } elseif ($type === 'assign_team') {
                    $this->executeAssignTeam($config, $conversation);
                    $this->logStep($run, $i, $type, 'ok', 'Tim ditugaskan');
                } elseif ($type === 'assign_users') {
                    $this->executeAssignUsers($config, $conversation);
                    $this->logStep($run, $i, $type, 'ok', 'User ditugaskan');
                } elseif ($type === 'set_lead_stage') {
                    $stage = (string) ($config['lead_stage'] ?? '');
                    if ($stage !== '') {
                        $conversation->lead_stage = $stage;
                        $conversation->save();
                    }
                    $this->logStep($run, $i, $type, 'ok', 'Lead stage diperbarui');
                } elseif ($type === 'append_memo') {
                    $text = trim((string) ($config['text'] ?? ''));
                    if ($text !== '') {
                        $conversation->memo = trim(($conversation->memo ?? '')."\n".$this->replacePlaceholders($text, $conversation));
                        $conversation->save();
                    }
                    $this->logStep($run, $i, $type, 'ok', 'Memo diperbarui');
                } elseif ($type === 'notify_assignees') {
                    $this->executeNotifyAssignees($conversation, (string) ($config['message'] ?? 'Chat masuk — otomasi inbox'));
                    $this->logStep($run, $i, $type, 'ok', 'Notifikasi dikirim');
                } else {
                    $this->logStep($run, $i, $type ?: 'unknown', 'skipped', 'Tipe langkah tidak dikenal');
                }
            } catch (\Throwable $e) {
                Log::error('Omni flow step failed', [
                    'run_id' => $run->id,
                    'step' => $i,
                    'type' => $type,
                    'error' => $e->getMessage(),
                ]);
                $this->logStep($run, $i, $type, 'failed', mb_substr($e->getMessage(), 0, 480));
                $this->finishRun($run, 'failed', $e->getMessage());

                return;
            }
        }

        $this->finishRun($run, 'completed', null);
    }

    private function tryStartFlow(OmniFlow $flow, OmniConversation $conversation, OmniMessage $message): bool
    {
        $definition = is_array($flow->definition) ? $flow->definition : [];

        if (OmniFlowDefinition::isGraph($definition)) {
            if (! $this->shouldStartGraph($definition, $conversation, $message)) {
                return false;
            }
        } else {
            $steps = $flow->steps();
            if ($steps === []) {
                return false;
            }

            $first = $steps[0];
            if (($first['type'] ?? '') === 'condition') {
                $config = is_array($first['config'] ?? null) ? $first['config'] : [];
                if (! $this->evaluateCondition($config, $conversation, $message)) {
                    return false;
                }
            }
        }

        $run = OmniFlowRun::query()->create([
            'flow_id' => $flow->id,
            'conversation_id' => $conversation->id,
            'trigger_message_id' => $message->id,
            'status' => 'running',
            'current_step_index' => 0,
            'context' => [
                'flow_name' => $flow->name,
            ],
            'started_at' => now(),
        ]);

        $conversation->active_flow_run_id = $run->id;
        $conversation->save();

        $this->run($run);

        return true;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function shouldStartGraph(array $definition, OmniConversation $conversation, OmniMessage $message): bool
    {
        $triggerId = OmniFlowDefinition::findTriggerNodeId($definition);
        if ($triggerId === null || $triggerId === '') {
            return false;
        }

        $nodes = OmniFlowDefinition::nodesById($definition);
        $outgoing = OmniFlowDefinition::outgoingEdges($definition);
        $currentId = OmniFlowDefinition::nextNodeId($triggerId, $outgoing);

        while ($currentId && $currentId !== '') {
            $node = $nodes[$currentId] ?? null;
            if (! is_array($node)) {
                return false;
            }

            $type = OmniFlowDefinition::nodeType($node);
            if ($type === 'condition') {
                return $this->evaluateCondition(
                    OmniFlowDefinition::nodeConfig($node),
                    $conversation,
                    $message
                );
            }

            if ($type !== '' && $type !== 'trigger') {
                return true;
            }

            $currentId = OmniFlowDefinition::nextNodeId($currentId, $outgoing);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function runGraph(
        OmniFlowRun $run,
        OmniFlow $flow,
        OmniConversation $conversation,
        array $definition
    ): void {
        $message = $run->triggerMessage;
        $context = is_array($run->context) ? $run->context : [];
        $nodes = OmniFlowDefinition::nodesById($definition);
        $outgoing = OmniFlowDefinition::outgoingEdges($definition);

        $triggerId = OmniFlowDefinition::findTriggerNodeId($definition);
        if ($triggerId === null || $triggerId === '') {
            $this->finishRun($run, 'failed', 'Flow tanpa node pemicu');

            return;
        }

        $currentId = (string) ($context['current_node_id'] ?? '');
        if ($currentId === '') {
            $currentId = OmniFlowDefinition::nextNodeId($triggerId, $outgoing) ?: '';
        }

        $stepIndex = (int) $run->current_step_index;
        $maxSteps = 50;

        while ($currentId !== '' && $stepIndex < $maxSteps) {
            $node = $nodes[$currentId] ?? null;
            if (! is_array($node)) {
                break;
            }

            $type = OmniFlowDefinition::nodeType($node);
            $config = OmniFlowDefinition::nodeConfig($node);

            $run->update([
                'current_step_index' => $stepIndex,
                'context' => array_merge($context, [
                    'current_node_id' => $currentId,
                    'flow_name' => $flow->name,
                ]),
            ]);

            try {
                if ($type === 'trigger') {
                    $currentId = OmniFlowDefinition::nextNodeId($currentId, $outgoing) ?: '';
                    $stepIndex++;

                    continue;
                }

                if ($type === 'condition') {
                    $passed = $this->evaluateCondition($config, $conversation, $message);
                    $this->logStep(
                        $run,
                        $stepIndex,
                        $type,
                        $passed ? 'ok' : 'skipped',
                        $passed ? 'Kondisi terpenuhi' : 'Kondisi tidak terpenuhi — cabang tidak ya'
                    );
                    $handle = $passed ? 'true' : 'false';
                    $currentId = OmniFlowDefinition::nextNodeId($currentId, $outgoing, $handle) ?: '';
                    $stepIndex++;

                    continue;
                }

                if ($type === '') {
                    $this->logStep($run, $stepIndex, 'unknown', 'skipped', 'Node tidak dikenal');
                    $currentId = OmniFlowDefinition::nextNodeId($currentId, $outgoing) ?: '';
                    $stepIndex++;

                    continue;
                }

                $this->executeStep($type, $config, $conversation, $message, $context);
                $logDetail = $type === 'send_message'
                    ? 'Pesan terkirim ('.$this->resolveSendMessageMode($config, (string) $conversation->channel).')'
                    : 'Langkah selesai';
                $this->logStep($run, $stepIndex, $type, 'ok', $logDetail);
                $currentId = OmniFlowDefinition::nextNodeId($currentId, $outgoing) ?: '';
                $stepIndex++;
            } catch (\Throwable $e) {
                Log::error('Omni flow graph step failed', [
                    'run_id' => $run->id,
                    'node_id' => $currentId,
                    'type' => $type,
                    'error' => $e->getMessage(),
                ]);
                $this->logStep($run, $stepIndex, $type, 'failed', mb_substr($e->getMessage(), 0, 480));
                $this->finishRun($run, 'failed', $e->getMessage());

                return;
            }
        }

        $this->finishRun($run, 'completed', null);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     */
    private function executeStep(
        string $type,
        array $config,
        OmniConversation $conversation,
        ?OmniMessage $message,
        array &$context
    ): void {
        if ($type === 'send_message') {
            $this->executeSendMessage($config, $conversation, $context);
        } elseif ($type === 'assign_team') {
            $this->executeAssignTeam($config, $conversation);
        } elseif ($type === 'assign_users') {
            $this->executeAssignUsers($config, $conversation);
        } elseif ($type === 'set_lead_stage') {
            $stage = (string) ($config['lead_stage'] ?? '');
            if ($stage !== '') {
                $conversation->lead_stage = $stage;
                $conversation->save();
            }
        } elseif ($type === 'append_memo') {
            $text = trim((string) ($config['text'] ?? ''));
            if ($text !== '') {
                $conversation->memo = trim(($conversation->memo ?? '')."\n".$this->replacePlaceholders($text, $conversation));
                $conversation->save();
            }
        } elseif ($type === 'notify_assignees') {
            $this->executeNotifyAssignees($conversation, (string) ($config['message'] ?? 'Chat masuk — otomasi inbox'));
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function evaluateCondition(array $config, OmniConversation $conversation, ?OmniMessage $message): bool
    {
        $rules = $config['rules'] ?? [];
        if (! is_array($rules) || $rules === []) {
            return true;
        }

        $match = ($config['match'] ?? 'all') === 'any' ? 'any' : 'all';
        $results = [];

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }
            $results[] = $this->evaluateRule($rule, $conversation, $message);
        }

        if ($results === []) {
            return true;
        }

        return $match === 'any'
            ? in_array(true, $results, true)
            : ! in_array(false, $results, true);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function evaluateRule(array $rule, OmniConversation $conversation, ?OmniMessage $message): bool
    {
        $field = (string) ($rule['field'] ?? '');
        $op = (string) ($rule['op'] ?? 'equals');
        $value = $rule['value'] ?? null;

        return match ($field) {
            'message_contains' => $this->textContains($message?->body, (string) $value),
            'message_not_contains' => ! $this->textContains($message?->body, (string) $value),
            'hour_between' => $this->timeBetween(
                $rule['from'] ?? '00:00',
                $rule['to'] ?? '23:59'
            ),
            'no_assignee' => ! $conversation->assigned_user_id
                && ! $conversation->assignees()->exists()
                && ! $conversation->teams()->exists(),
            'lead_stage' => $op === 'not_equals'
                ? (string) $conversation->lead_stage !== (string) $value
                : (string) $conversation->lead_stage === (string) $value,
            'has_member' => (bool) $value === (bool) $conversation->member_apps_member_id,
            'channel' => (string) $conversation->channel === (string) $value,
            default => true,
        };
    }

    private function textContains(?string $haystack, string $needle): bool
    {
        if ($needle === '' || $haystack === null) {
            return false;
        }

        return mb_stripos($haystack, $needle) !== false;
    }

    /**
     * Rentang waktu WIB inklusif awal, eksklusif akhir. Mendukung HH:mm atau jam integer lama (0–23).
     *
     * @param  string|int|float  $from
     * @param  string|int|float  $to
     */
    private function timeBetween(string|int|float $from, string|int|float $to): bool
    {
        $fromMinutes = $this->parseTimeToMinutes($from);
        $toMinutes = $this->parseTimeToMinutes($to);
        $now = Carbon::now(config('app.timezone'));
        $nowMinutes = ((int) $now->format('G')) * 60 + (int) $now->format('i');

        if ($fromMinutes <= $toMinutes) {
            return $nowMinutes >= $fromMinutes && $nowMinutes < $toMinutes;
        }

        return $nowMinutes >= $fromMinutes || $nowMinutes < $toMinutes;
    }

    /**
     * @param  string|int|float  $value
     */
    private function parseTimeToMinutes(string|int|float $value): int
    {
        if (is_int($value) || (is_numeric($value) && ! str_contains((string) $value, ':'))) {
            return max(0, min(23, (int) $value)) * 60;
        }

        $s = trim((string) $value);
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $s, $m)) {
            $hour = max(0, min(23, (int) $m[1]));
            $minute = max(0, min(59, (int) $m[2]));

            return $hour * 60 + $minute;
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     */
    private function executeSendMessage(array $config, OmniConversation $conversation, array $context): void
    {
        $channel = (string) $conversation->channel;
        $messageMode = $this->resolveSendMessageMode($config, $channel);

        if (in_array($messageMode, ['image', 'document'], true)) {
            $this->executeSendMessageWithMedia($config, $conversation, $messageMode);

            return;
        }

        $body = trim((string) ($config['body'] ?? ''));
        if ($body === '') {
            return;
        }

        $body = $this->replacePlaceholders($body, $conversation);

        $messageType = 'text';
        if (in_array($channel, ['messenger', 'facebook', 'instagram'], true)) {
            if ($channel === 'instagram' && $this->useInstagramLoginApi()) {
                $result = app(\App\Services\Meta\MetaInstagramLoginClient::class)->sendText(
                    $conversation->external_contact_id,
                    $body,
                    $conversation->phone_number_id
                );
            } else {
                $result = app(\App\Services\Meta\MetaMessengerClient::class)->sendText(
                    $conversation->external_contact_id,
                    $body,
                    $conversation->phone_number_id
                );
            }
            $metaMessageId = (string) ($result['message_id'] ?? $result['messages'][0]['id'] ?? '');
        } elseif ($channel === 'whatsapp') {
            $wa = app(MetaWhatsAppClient::class);
            $previewSuffix = '';

            if ($messageMode === 'quick_reply') {
                $buttons = $this->normalizeQuickReplyButtons($config['buttons'] ?? []);
                $result = $wa->sendInteractiveReplyButtons(
                    $conversation->external_contact_id,
                    $body,
                    $buttons,
                    $conversation->phone_number_id
                );
                $messageType = 'interactive';
                $previewSuffix = ' · Tombol: '.implode(', ', array_column($buttons, 'title'));
            } elseif ($messageMode === 'cta_url') {
                $cta = is_array($config['cta_url'] ?? null) ? $config['cta_url'] : [];
                $displayText = $this->replacePlaceholders(trim((string) ($cta['display_text'] ?? 'Buka link')), $conversation);
                $url = $this->replacePlaceholders(trim((string) ($cta['url'] ?? '')), $conversation);
                $result = $wa->sendInteractiveCtaUrl(
                    $conversation->external_contact_id,
                    $body,
                    $displayText,
                    $url,
                    $conversation->phone_number_id
                );
                $messageType = 'interactive';
                $previewSuffix = ' · ['.$displayText.']';
            } else {
                $result = $wa->sendText(
                    $conversation->external_contact_id,
                    $body,
                    $conversation->phone_number_id
                );
                $messageType = 'text';
            }

            $metaMessageId = (string) ($result['messages'][0]['id'] ?? '');
            $body = mb_substr($body.$previewSuffix, 0, 500);
        } else {
            throw new RuntimeException('Kirim otomatis belum didukung untuk channel: '.$channel);
        }
        $sentAt = now();

        OmniMessage::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => null,
            'direction' => 'outbound',
            'meta_message_id' => $metaMessageId !== '' ? $metaMessageId : null,
            'message_type' => $messageType,
            'body' => $body,
            'payload' => array_merge($result, ['omni_flow_automation' => true]),
            'status' => 'sent',
            'sent_at' => $sentAt,
        ]);

        $conversation->update([
            'last_message_at' => $sentAt,
            'last_message_preview' => mb_substr($body, 0, 500),
        ]);
    }

    /**
     * Kirim gambar atau PDF dari berkas yang diunggah di editor flow.
     *
     * @param  array<string, mixed>  $config
     */
    private function executeSendMessageWithMedia(
        array $config,
        OmniConversation $conversation,
        string $messageMode
    ): void {
        if ((string) $conversation->channel !== 'whatsapp') {
            throw new RuntimeException('Lampiran otomasi hanya didukung untuk WhatsApp.');
        }

        $storagePath = trim((string) ($config['media_path'] ?? ''));
        if ($storagePath === '' || ! Storage::disk('public')->exists($storagePath)) {
            throw new RuntimeException('Berkas flow tidak ditemukan. Unggah ulang lampiran di editor flow.');
        }

        $absolutePath = Storage::disk('public')->path($storagePath);
        $mime = trim((string) ($config['media_mime'] ?? ''));
        if ($mime === '') {
            $mime = mime_content_type($absolutePath) ?: 'application/octet-stream';
        }
        $filename = trim((string) ($config['media_filename'] ?? ''));
        if ($filename === '') {
            $filename = basename($storagePath);
        }

        $caption = trim((string) ($config['body'] ?? ''));
        if ($caption !== '') {
            $caption = $this->replacePlaceholders($caption, $conversation);
        }

        $wa = app(MetaWhatsAppClient::class);
        $mediaId = $wa->uploadMedia($absolutePath, $mime, $conversation->phone_number_id);

        if ($messageMode === 'image') {
            $result = $wa->sendImage(
                $conversation->external_contact_id,
                $mediaId,
                $caption !== '' ? $caption : null,
                $conversation->phone_number_id
            );
            $messageType = 'image';
            $preview = $caption !== '' ? $caption : '[Gambar]';
        } else {
            $result = $wa->sendDocument(
                $conversation->external_contact_id,
                $mediaId,
                $caption !== '' ? $caption : null,
                $filename,
                $conversation->phone_number_id
            );
            $messageType = 'document';
            $preview = $caption !== '' ? $caption : '[PDF: '.$filename.']';
        }

        $localUrl = $this->publicStorageUrl($storagePath);
        $metaMessageId = (string) ($result['messages'][0]['id'] ?? '');
        $sentAt = now();

        OmniMessage::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => null,
            'direction' => 'outbound',
            'meta_message_id' => $metaMessageId !== '' ? $metaMessageId : null,
            'message_type' => $messageType,
            'body' => $preview,
            'payload' => array_merge(is_array($result) ? $result : [], [
                'omni_flow_automation' => true,
                'local_media_url' => $localUrl,
                'media_filename' => $filename,
                'media_mime' => $mime,
            ]),
            'status' => 'sent',
            'sent_at' => $sentAt,
        ]);

        $conversation->update([
            'last_message_at' => $sentAt,
            'last_message_preview' => mb_substr($preview, 0, 500),
        ]);
    }

    private function publicStorageUrl(string $storedPath): string
    {
        $relative = Storage::disk('public')->url($storedPath);

        return str_starts_with($relative, 'http') ? $relative : url($relative);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function executeAssignTeam(array $config, OmniConversation $conversation): void
    {
        $teamIds = array_values(array_filter(array_map('intval', $config['team_ids'] ?? [])));
        if ($teamIds === []) {
            return;
        }

        $sync = [];
        foreach ($teamIds as $teamId) {
            if (OmniTeam::query()->whereKey($teamId)->exists()) {
                $sync[$teamId] = [];
            }
        }
        if ($sync !== []) {
            $conversation->teams()->syncWithoutDetaching($sync);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function executeAssignUsers(array $config, OmniConversation $conversation): void
    {
        $userIds = array_values(array_filter(array_map('intval', $config['user_ids'] ?? [])));
        if ($userIds === []) {
            return;
        }

        $sync = [];
        foreach ($userIds as $userId) {
            if (User::query()->active()->whereKey($userId)->exists()) {
                $sync[$userId] = [];
            }
        }
        if ($sync !== []) {
            $conversation->assignees()->syncWithoutDetaching($sync);
            if (! $conversation->assigned_user_id) {
                $conversation->assigned_user_id = array_key_first($sync);
                $conversation->save();
            }
        }
    }

    private function executeNotifyAssignees(OmniConversation $conversation, string $message): void
    {
        $conversation->load(['assignees', 'teams.users']);
        $userIds = $conversation->assignees->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($conversation->teams as $team) {
            foreach ($team->users as $user) {
                $userIds[] = (int) $user->id;
            }
        }

        $userIds = array_values(array_unique(array_filter($userIds)));
        if ($userIds === []) {
            return;
        }

        $label = $conversation->contact_name ?: $conversation->external_contact_id;
        $url = url('/crm/omnichannel-inbox?conversation='.$conversation->id);

        foreach ($userIds as $userId) {
            NotificationService::create([
                'user_id' => $userId,
                'type' => 'omnichannel_flow',
                'title' => 'Otomasi inbox',
                'message' => $message.' — '.$label,
                'url' => $url,
            ]);
        }
    }

    /**
     * Deteksi mode kirim dari config (termasuk jika UI lama tidak menyimpan message_mode).
     *
     * @param  array<string, mixed>  $config
     */
    private function resolveSendMessageMode(array $config, string $channel): string
    {
        $mode = (string) ($config['message_mode'] ?? 'text');
        if ($channel !== 'whatsapp') {
            return 'text';
        }

        $cta = is_array($config['cta_url'] ?? null) ? $config['cta_url'] : [];
        $ctaUrl = trim((string) ($cta['url'] ?? ''));
        $ctaLabel = trim((string) ($cta['display_text'] ?? ''));
        if ($ctaUrl !== '' && $ctaLabel !== '') {
            return 'cta_url';
        }

        if ($mode === 'quick_reply' || $mode === 'cta_url' || $mode === 'image' || $mode === 'document') {
            return $mode;
        }

        $buttons = $config['buttons'] ?? [];
        if (is_array($buttons)) {
            foreach ($buttons as $btn) {
                if (is_array($btn) && trim((string) ($btn['title'] ?? '')) !== '') {
                    return 'quick_reply';
                }
            }
        }

        $mediaPath = trim((string) ($config['media_path'] ?? ''));
        if ($mediaPath !== '') {
            $mime = (string) ($config['media_mime'] ?? '');
            if (str_starts_with($mime, 'image/')) {
                return 'image';
            }

            return 'document';
        }

        return 'text';
    }

    /**
     * @param  mixed  $buttonsRaw
     * @return list<array{id: string, title: string}>
     */
    private function normalizeQuickReplyButtons(mixed $buttonsRaw): array
    {
        if (! is_array($buttonsRaw)) {
            return [];
        }

        $out = [];
        foreach ($buttonsRaw as $i => $btn) {
            if (! is_array($btn)) {
                continue;
            }
            $title = trim((string) ($btn['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $id = trim((string) ($btn['id'] ?? ''));
            if ($id === '') {
                $id = 'btn_'.($i + 1);
            }
            $out[] = [
                'id' => preg_replace('/[^a-zA-Z0-9_\-]/', '_', $id) ?: 'btn_'.($i + 1),
                'title' => $title,
            ];
            if (count($out) >= 3) {
                break;
            }
        }

        if ($out === []) {
            throw new RuntimeException('Tombol balas: isi minimal satu label tombol.');
        }

        return $out;
    }

    private function replacePlaceholders(string $text, OmniConversation $conversation): string
    {
        $nama = $conversation->contact_name
            ?: $conversation->contact_first_name
            ?: $conversation->external_contact_id;

        $phone = $conversation->external_contact_id;
        if (str_starts_with($phone, '62')) {
            $phone = '0'.substr($phone, 2);
        }

        return str_replace(
            ['{{nama}}', '{{nomor}}', '{{nama_depan}}'],
            [$nama, $phone, $conversation->contact_first_name ?: $nama],
            $text
        );
    }

    private function logStep(OmniFlowRun $run, int $index, string $type, string $status, ?string $message): void
    {
        OmniFlowRunLog::query()->create([
            'flow_run_id' => $run->id,
            'step_index' => $index,
            'step_type' => $type,
            'status' => $status,
            'message' => $message,
            'created_at' => now(),
        ]);
    }

    private function finishRun(OmniFlowRun $run, string $status, ?string $error): void
    {
        $run->update([
            'status' => $status,
            'error_message' => $error,
            'finished_at' => now(),
        ]);

        OmniConversation::query()
            ->where('id', $run->conversation_id)
            ->where('active_flow_run_id', $run->id)
            ->update(['active_flow_run_id' => null]);
    }

    private function useInstagramLoginApi(): bool
    {
        return MetaInstagramTokens::resolved() !== []
            || (string) config('services.meta.instagram_login_access_token') !== '';
    }
}
