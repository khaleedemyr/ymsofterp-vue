<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FeedbackCaseEscalateOverdueCommand extends Command
{
    protected $signature = 'feedback:escalate-overdue {--limit=500 : Maximum cases per run} {--cooldown=60 : Alert cooldown in minutes}';

    protected $description = 'Escalate overdue feedback cases and write alert logs';

    public function handle(): int
    {
        $limit = max(1, min(5000, (int) $this->option('limit')));
        $cooldownMinutes = max(1, min(1440, (int) $this->option('cooldown')));
        $cooldownCutoff = now()->subMinutes($cooldownMinutes);

        $rows = DB::table('feedback_cases')
            ->whereIn('status', ['new', 'in_progress'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->where(function ($q) use ($cooldownCutoff) {
                $q->whereNull('last_alert_at')
                    ->orWhere('last_alert_at', '<=', $cooldownCutoff);
            })
            ->orderBy('due_at')
            ->limit($limit)
            ->get([
                'id',
                'source_type',
                'source_ref',
                'severity',
                'status',
                'id_outlet',
                'due_at',
                'last_alert_at',
            ]);

        if ($rows->isEmpty()) {
            $this->info('No overdue cases to escalate.');

            return self::SUCCESS;
        }

        $processed = 0;
        foreach ($rows as $row) {
            DB::transaction(function () use ($row, &$processed) {
                $message = sprintf(
                    'Overdue case %d (%s) severity=%s status=%s due_at=%s',
                    (int) $row->id,
                    (string) ($row->source_type ?? '-'),
                    (string) ($row->severity ?? 'neutral'),
                    (string) ($row->status ?? 'new'),
                    (string) ($row->due_at ?? '-')
                );

                DB::table('feedback_alert_logs')->insert([
                    'case_id' => (int) $row->id,
                    'rule_id' => null,
                    'channel' => 'in_app',
                    'target' => null,
                    'status' => 'queued',
                    'message' => $message,
                    'error_message' => null,
                    'sent_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('feedback_case_activities')->insert([
                    'case_id' => (int) $row->id,
                    'activity_type' => 'alert_sent',
                    'actor_user_id' => null,
                    'from_status' => null,
                    'to_status' => null,
                    'note' => 'Escalation queued: overdue case (in_app).',
                    'payload' => json_encode([
                        'channel' => 'in_app',
                        'severity' => (string) ($row->severity ?? ''),
                        'due_at' => (string) ($row->due_at ?? ''),
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('feedback_cases')
                    ->where('id', (int) $row->id)
                    ->update([
                        'last_alert_at' => now(),
                        'updated_at' => now(),
                    ]);

                $processed++;
            });
        }

        $this->info("Escalation queued for {$processed} overdue cases.");

        return self::SUCCESS;
    }
}
