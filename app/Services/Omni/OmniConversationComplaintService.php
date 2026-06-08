<?php

namespace App\Services\Omni;

use App\Models\OmniConversation;
use App\Models\OmniMessage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OmniConversationComplaintService
{
    private const SEVERITY_RANK = [
        'minor' => 1,
        'major' => 2,
        'critical' => 3,
    ];

    /**
     * @return array{severity: string, snippet: string}|null
     */
    public function analyzeText(string $text): ?array
    {
        $normalized = mb_strtolower(trim($text));
        if ($normalized === '' || mb_strlen($normalized) < 4) {
            return null;
        }

        $critical = [
            'racun', 'keracunan', 'muntah', 'sakit perut', 'busuk', 'basi', 'tikus', 'kecoa', 'serangga',
            'tidak higienis', 'kotor', 'berkarat', 'palsu', 'hoax', 'viral', 'polisi', 'pengaduan', 'lapor polisi',
            'bikin sakit', 'alergi', 'darurat',
        ];
        $major = [
            'kecewa', 'mengecewakan', 'komplain', 'komplen', 'buruk', 'jelek', 'tidak puas', 'tidak puas',
            'refund', 'kompensasi', 'ganti rugi', 'minta maaf', 'lambat', 'lama banget', 'mahal', 'tipu',
            'menipu', 'parah', 'kesal', 'marah', 'kecewa banget', 'worst', 'terburuk', 'disappoint',
        ];
        $minor = [
            'kurang', 'agak', 'sedikit', 'biasa aja', 'lumayan', 'kurang puas', 'kurang enak', 'kurang bagus',
        ];

        foreach ($critical as $kw) {
            if (str_contains($normalized, $kw)) {
                return ['severity' => 'critical', 'snippet' => $this->snippet($text)];
            }
        }
        foreach ($major as $kw) {
            if (str_contains($normalized, $kw)) {
                return ['severity' => 'major', 'snippet' => $this->snippet($text)];
            }
        }
        foreach ($minor as $kw) {
            if (str_contains($normalized, $kw)) {
                return ['severity' => 'minor', 'snippet' => $this->snippet($text)];
            }
        }

        return null;
    }

    public function supportsComplaintColumns(): bool
    {
        return Schema::hasColumn('omni_conversations', 'complaint_severity');
    }

    public function evaluateInboundMessage(OmniConversation $conversation, OmniMessage $message): void
    {
        if (! $this->supportsComplaintColumns()) {
            return;
        }
        if ($message->direction !== 'inbound' || (string) $message->message_type !== 'text') {
            return;
        }

        $hit = $this->analyzeText((string) $message->body);
        if ($hit === null) {
            return;
        }

        $this->applyComplaintHit($conversation, $hit['severity'], $hit['snippet'], (int) $message->id);
    }

    public function scanConversation(OmniConversation $conversation, int $limit = 30): void
    {
        if (! $this->supportsComplaintColumns()) {
            return;
        }

        $messages = $conversation->messages()
            ->where('direction', 'inbound')
            ->where('message_type', 'text')
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'body', 'direction', 'message_type']);

        foreach ($messages as $message) {
            $hit = $this->analyzeText((string) $message->body);
            if ($hit !== null) {
                $this->applyComplaintHit($conversation, $hit['severity'], $hit['snippet'], (int) $message->id);

                return;
            }
        }
    }

    /**
     * @return array{case_id: int, case_url: string, created: bool}
     */
    public function escalateToCustomerVoice(OmniConversation $conversation, User $actor): array
    {
        if (! Schema::hasTable('feedback_cases')) {
            throw new \RuntimeException('Tabel feedback_cases belum tersedia.');
        }

        $conversation->loadMissing(['omniContact.preferredOutlet']);

        $sourceRef = 'omni:conv:'.$conversation->id;
        $existingId = DB::table('feedback_cases')->where('source_ref', $sourceRef)->value('id');
        if ($existingId) {
            $caseId = (int) $existingId;
            if ($this->supportsComplaintColumns() && ! $conversation->feedback_case_id) {
                $conversation->feedback_case_id = $caseId;
                $conversation->save();
            }

            return [
                'case_id' => $caseId,
                'case_url' => url('/customer-voice-command-center?show_all=1&open_case='.$caseId),
                'created' => false,
            ];
        }

        $severity = $this->resolveEscalationSeverity($conversation);
        $rawText = $this->buildEscalationRawText($conversation);
        $eventAt = $conversation->complaint_detected_at
            ?? $conversation->last_customer_message_at
            ?? $conversation->last_message_at
            ?? now();

        $outletId = $conversation->omniContact?->preferred_outlet_id;
        $channelLabel = match ((string) $conversation->channel) {
            'whatsapp' => 'WhatsApp Inbox',
            'instagram' => 'Instagram DM',
            'messenger', 'facebook' => 'Messenger',
            default => 'Omnichannel Inbox',
        };

        $meta = [
            'origin' => 'omnichannel_inbox',
            'conversation_id' => $conversation->id,
            'channel' => $conversation->channel,
            'external_contact_id' => $conversation->external_contact_id,
            'complaint_snippet' => $conversation->complaint_snippet,
            'escalated_by_user_id' => $actor->id,
            'escalated_by_name' => $actor->nama_lengkap ?? $actor->email,
            'inbox_url' => url('/crm/omnichannel-inbox?conversation='.$conversation->id),
        ];

        $risk = match ($severity) {
            'critical' => 95,
            'major' => 75,
            'minor' => 50,
            default => 40,
        };
        $sla = match ($severity) {
            'critical' => 30,
            'major' => 120,
            'minor' => 1440,
            default => null,
        };
        $dueAt = $sla !== null
            ? Carbon::parse($eventAt)->addMinutes($sla)->format('Y-m-d H:i:s')
            : null;

        $summary = $conversation->complaint_snippet
            ?: mb_substr(trim($rawText), 0, 200);

        $caseId = (int) DB::table('feedback_cases')->insertGetId([
            'source_type' => 'omnichannel_inbox',
            'source_ref' => $sourceRef,
            'source_report_id' => null,
            'source_item_id' => $conversation->complaint_message_id,
            'id_outlet' => $outletId,
            'channel_account' => $channelLabel,
            'author_name' => $conversation->contact_name,
            'customer_contact' => $conversation->external_contact_id,
            'event_at' => Carbon::parse($eventAt)->format('Y-m-d H:i:s'),
            'severity' => $severity,
            'topics' => json_encode(['service_complaint'], JSON_UNESCAPED_UNICODE),
            'summary_id' => $summary !== '' ? $summary : 'Komplain dari omnichannel inbox',
            'raw_text' => $rawText,
            'risk_score' => $risk,
            'status' => 'new',
            'sla_minutes' => $sla,
            'due_at' => $dueAt,
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (Schema::hasTable('feedback_case_activities')) {
            DB::table('feedback_case_activities')->insert([
                'case_id' => $caseId,
                'activity_type' => 'created',
                'actor_user_id' => $actor->id,
                'note' => 'Eskalasi dari Omnichannel Inbox #'.$conversation->id,
                'payload' => json_encode(['conversation_id' => $conversation->id], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($this->supportsComplaintColumns()) {
            $conversation->feedback_case_id = $caseId;
            $conversation->save();
        }

        return [
            'case_id' => $caseId,
            'case_url' => url('/customer-voice-command-center?show_all=1&open_case='.$caseId),
            'created' => true,
        ];
    }

    private function applyComplaintHit(
        OmniConversation $conversation,
        string $severity,
        string $snippet,
        int $messageId
    ): void {
        $current = (string) ($conversation->complaint_severity ?? '');
        $currentRank = self::SEVERITY_RANK[$current] ?? 0;
        $newRank = self::SEVERITY_RANK[$severity] ?? 0;

        if ($newRank < $currentRank && $conversation->complaint_severity) {
            return;
        }

        $conversation->complaint_severity = $severity;
        $conversation->complaint_snippet = $snippet;
        $conversation->complaint_message_id = $messageId;
        $conversation->complaint_detected_at = now();
        $conversation->save();
    }

    private function resolveEscalationSeverity(OmniConversation $conversation): string
    {
        $severity = strtolower(trim((string) ($conversation->complaint_severity ?? '')));
        if (in_array($severity, ['critical', 'major', 'minor'], true)) {
            return $severity;
        }

        return 'major';
    }

    private function buildEscalationRawText(OmniConversation $conversation): string
    {
        $lines = [];
        $messages = $conversation->messages()
            ->where('direction', 'inbound')
            ->where('message_type', 'text')
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['body', 'sent_at']);

        foreach ($messages->reverse() as $msg) {
            $body = trim((string) $msg->body);
            if ($body === '') {
                continue;
            }
            $at = $msg->sent_at?->format('d/m/Y H:i') ?? '';
            $lines[] = ($at !== '' ? "[{$at}] " : '').$body;
        }

        if ($lines === [] && $conversation->complaint_snippet) {
            return (string) $conversation->complaint_snippet;
        }

        return implode("\n", $lines);
    }

    private function snippet(string $text): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return mb_strlen($t) > 280 ? mb_substr($t, 0, 277).'…' : $t;
    }
}
