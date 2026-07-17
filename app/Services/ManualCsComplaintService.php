<?php

namespace App\Services;

use App\Models\ManualCsComplaint;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ManualCsComplaintService
{
    /**
     * @return array{case_id: int, case_url: string, created: bool}
     */
    public function syncToCvcc(ManualCsComplaint $complaint, User $actor): array
    {
        if (! Schema::hasTable('feedback_cases')) {
            throw new \RuntimeException('Tabel feedback_cases belum tersedia.');
        }

        $sourceRef = 'mcs:'.$complaint->id;
        $existingId = $complaint->feedback_case_id
            ?: DB::table('feedback_cases')->where('source_ref', $sourceRef)->value('id');

        if ($existingId) {
            $caseId = (int) $existingId;
            $complaint->update([
                'feedback_case_id' => $caseId,
                'sync_status' => 'synced',
                'synced_at' => now(),
                'updated_by' => $actor->id,
            ]);

            return [
                'case_id' => $caseId,
                'case_url' => url('/customer-voice-command-center?show_all=1&open_case='.$caseId),
                'created' => false,
            ];
        }

        $severity = $this->normalizeSeverity($complaint->severity);
        $risk = $this->riskScoreFromSeverity($severity);
        $sla = $this->slaMinutesFromSeverity($severity);
        $eventAt = Carbon::parse($complaint->event_at);
        $dueAt = $sla !== null ? $eventAt->copy()->addMinutes($sla)->format('Y-m-d H:i:s') : null;

        $channelLabel = match ($complaint->input_channel) {
            'phone' => 'Telepon CS',
            'walk_in' => 'Walk-in CS',
            'email' => 'Email CS',
            'whatsapp_cs' => 'WhatsApp CS',
            default => 'Customer Service',
        };

        $topics = array_values(array_filter((array) ($complaint->topics ?? [])));
        if ($topics === []) {
            $topics = ['service_complaint'];
        }

        $summary = trim((string) ($complaint->summary ?: ''));
        if ($summary === '') {
            $summary = mb_substr(trim((string) $complaint->complaint_text), 0, 200);
        }

        $meta = [
            'origin' => 'manual_cs_input',
            'manual_cs_complaint_id' => $complaint->id,
            'manual_cs_number' => $complaint->number,
            'input_channel' => $complaint->input_channel,
            'created_by_user_id' => $complaint->created_by,
            'created_by_name' => $actor->nama_lengkap ?? $actor->email,
            'synced_by_user_id' => $actor->id,
            'synced_by_name' => $actor->nama_lengkap ?? $actor->email,
            'customer_email' => $complaint->customer_email,
            'follow_up_target' => 'customer',
            'input_url' => url('/manual-cs-complaints'),
        ];

        $payload = [
            'source_type' => 'manual_cs',
            'source_ref' => $sourceRef,
            'source_report_id' => null,
            'source_item_id' => $complaint->id,
            'id_outlet' => $complaint->id_outlet,
            'channel_account' => $channelLabel,
            'author_name' => $complaint->author_name,
            'customer_contact' => $complaint->customer_contact,
            'event_at' => $eventAt->format('Y-m-d H:i:s'),
            'severity' => $severity,
            'topics' => json_encode($topics, JSON_UNESCAPED_UNICODE),
            'summary_id' => $summary !== '' ? $summary : 'Komplain manual Customer Service',
            'raw_text' => $complaint->complaint_text,
            'risk_score' => $risk,
            'status' => 'new',
            'sla_minutes' => $sla,
            'due_at' => $dueAt,
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('feedback_cases', 'follow_up_status')) {
            $payload['follow_up_status'] = 'new';
        }

        $caseId = (int) DB::table('feedback_cases')->insertGetId(array_merge($payload, [
            'created_at' => now(),
        ]));

        if (Schema::hasTable('feedback_case_activities')) {
            DB::table('feedback_case_activities')->insert([
                'case_id' => $caseId,
                'activity_type' => 'created',
                'actor_user_id' => $actor->id,
                'note' => 'Input manual Customer Service #'.$complaint->number,
                'payload' => json_encode(['manual_cs_complaint_id' => $complaint->id], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $complaint->update([
            'feedback_case_id' => $caseId,
            'sync_status' => 'synced',
            'synced_at' => now(),
            'updated_by' => $actor->id,
        ]);

        return [
            'case_id' => $caseId,
            'case_url' => url('/customer-voice-command-center?show_all=1&open_case='.$caseId),
            'created' => true,
        ];
    }

    public function generateNumber(): string
    {
        $prefix = 'MCS'.now()->format('Ymd');
        $last = ManualCsComplaint::withTrashed()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function normalizeSeverity(?string $severity): string
    {
        $s = strtolower(trim((string) ($severity ?? '')));

        return match ($s) {
            'critical', 'severe' => 'critical',
            'major', 'negative' => 'major',
            'minor', 'mild_negative' => 'minor',
            default => 'major',
        };
    }

    private function riskScoreFromSeverity(string $severity): int
    {
        return match ($severity) {
            'critical' => 95,
            'major' => 75,
            'minor' => 50,
            default => 40,
        };
    }

    private function slaMinutesFromSeverity(string $severity): ?int
    {
        return match ($severity) {
            'critical' => 30,
            'major' => 120,
            'minor' => 1440,
            default => null,
        };
    }
}
