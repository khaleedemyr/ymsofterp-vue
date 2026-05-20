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
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            'hour_between' => $this->hourBetween(
                (int) ($rule['from'] ?? 0),
                (int) ($rule['to'] ?? 0)
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

    private function hourBetween(int $fromHour, int $toHour): bool
    {
        $hour = (int) Carbon::now(config('app.timezone'))->format('G');

        if ($fromHour <= $toHour) {
            return $hour >= $fromHour && $hour < $toHour;
        }

        return $hour >= $fromHour || $hour < $toHour;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     */
    private function executeSendMessage(array $config, OmniConversation $conversation, array $context): void
    {
        if ($conversation->channel !== 'whatsapp') {
            throw new RuntimeException('Kirim otomatis saat ini hanya untuk WhatsApp.');
        }

        $body = trim((string) ($config['body'] ?? ''));
        if ($body === '') {
            return;
        }

        $body = $this->replacePlaceholders($body, $conversation);

        $result = app(MetaWhatsAppClient::class)->sendText(
            $conversation->external_contact_id,
            $body,
            $conversation->phone_number_id
        );

        $metaMessageId = (string) ($result['messages'][0]['id'] ?? '');
        $sentAt = now();

        OmniMessage::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => null,
            'direction' => 'outbound',
            'meta_message_id' => $metaMessageId !== '' ? $metaMessageId : null,
            'message_type' => 'text',
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
            if (User::query()->whereKey($userId)->exists()) {
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
}
