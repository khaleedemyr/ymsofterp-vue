<?php

namespace App\Services\Wa;

use App\Jobs\Wa\BuildWaBroadcastRecipientsJob;
use App\Jobs\Wa\SendWaBroadcastMessageJob;
use App\Models\WaBroadcastCampaign;
use App\Models\WaBroadcastRecipient;
use Illuminate\Support\Facades\DB;

class WaBroadcastCampaignService
{
    public function __construct(
        private readonly WaBroadcastRecipientResolver $resolver,
        private readonly WaBroadcastDailyLimitService $dailyLimit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data, int $userId): WaBroadcastCampaign
    {
        $filters = $data['filter_definition'] ?? [];
        $estimated = $this->resolver->count(is_array($filters) ? $filters : []);

        return WaBroadcastCampaign::query()->create([
            'name' => (string) ($data['name'] ?? 'Broadcast WA'),
            'status' => 'draft',
            'message_type' => (string) ($data['message_type'] ?? 'template'),
            'template_name' => $data['template_name'] ?? null,
            'template_language' => (string) ($data['template_language'] ?? 'id'),
            'template_body_params' => $data['template_body_params'] ?? [],
            'session_text' => $data['session_text'] ?? null,
            'filter_definition' => $filters,
            'recipient_count_estimated' => $estimated,
            'daily_cap' => (int) ($data['daily_cap'] ?? $this->dailyLimit->cap()),
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'created_by_user_id' => $userId,
            'phone_number_id' => config('services.meta.whatsapp_phone_number_id'),
        ]);
    }

    public function refreshEstimate(WaBroadcastCampaign $campaign): int
    {
        $filters = $campaign->filter_definition ?? [];
        $count = $this->resolver->count(is_array($filters) ? $filters : []);
        $campaign->recipient_count_estimated = $count;
        $campaign->save();

        return $count;
    }

    public function start(WaBroadcastCampaign $campaign): void
    {
        if (! in_array($campaign->status, ['draft', 'scheduled', 'paused'], true)) {
            throw new \RuntimeException('Campaign tidak bisa dijalankan dari status: '.$campaign->status);
        }

        $remaining = $this->dailyLimit->remainingToday($campaign->phone_number_id);
        if ($remaining <= 0) {
            throw new \RuntimeException('Kuota harian broadcast WA sudah habis ('.$this->dailyLimit->cap().'/hari).');
        }

        $campaign->status = 'building';
        $campaign->started_at = $campaign->started_at ?? now();
        $campaign->last_error = null;
        $campaign->save();

        BuildWaBroadcastRecipientsJob::dispatch($campaign->id);
    }

    public function pause(WaBroadcastCampaign $campaign): void
    {
        if ($campaign->status === 'running') {
            $campaign->status = 'paused';
            $campaign->save();
        }
    }

    public function dispatchSendBatch(WaBroadcastCampaign $campaign, int $batchSize = 50): int
    {
        if ($campaign->status !== 'running') {
            return 0;
        }

        $remaining = $this->dailyLimit->remainingToday($campaign->phone_number_id);
        if ($remaining <= 0) {
            $campaign->status = 'paused';
            $campaign->last_error = 'Kuota harian tercapai.';
            $campaign->save();

            return 0;
        }

        $limit = min($batchSize, $remaining);
        $ids = WaBroadcastRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->all();

        foreach ($ids as $id) {
            SendWaBroadcastMessageJob::dispatch((int) $id);
        }

        return count($ids);
    }

    public function syncCampaignStats(WaBroadcastCampaign $campaign): void
    {
        $stats = WaBroadcastRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' OR status = 'delivered' OR status = 'read' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) as skipped,
                SUM(CASE WHEN status = 'pending' OR status = 'queued' THEN 1 ELSE 0 END) as pending
            ")
            ->first();

        $campaign->recipient_count_total = (int) ($stats->total ?? 0);
        $campaign->recipient_count_sent = (int) ($stats->sent ?? 0);
        $campaign->recipient_count_failed = (int) ($stats->failed ?? 0);
        $campaign->recipient_count_skipped = (int) ($stats->skipped ?? 0);

        if ((int) ($stats->pending ?? 0) === 0 && $campaign->status === 'running') {
            $campaign->status = 'completed';
            $campaign->finished_at = now();
        }

        $campaign->save();
    }

    /**
     * Materialize recipients in chunks after build job.
     */
    public function materializeRecipients(WaBroadcastCampaign $campaign): void
    {
        $filters = $campaign->filter_definition ?? [];
        if (! is_array($filters)) {
            $filters = [];
        }

        $campaign->recipients()->delete();

        $all = $this->resolver->resolve($filters, PHP_INT_MAX);
        $now = now();

        foreach ($all->chunk(200) as $batch) {
            $insert = $batch->map(fn ($r) => [
                'campaign_id' => $campaign->id,
                'phone_normalized' => $r['phone_normalized'],
                'wa_id' => $r['wa_id'],
                'member_apps_member_id' => $r['member_apps_member_id'],
                'omni_contact_id' => $r['omni_contact_id'],
                'display_name' => $r['display_name'] ? mb_substr((string) $r['display_name'], 0, 255) : null,
                'source' => $r['source'],
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            DB::table('wa_broadcast_recipients')->insert($insert);
        }

        $campaign->recipient_count_total = $campaign->recipients()->count();
        $campaign->status = 'running';
        $campaign->save();
    }
}
