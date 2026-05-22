<?php

namespace App\Jobs\Wa;

use App\Models\WaBroadcastCampaign;
use App\Services\Wa\WaBroadcastCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BuildWaBroadcastRecipientsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(public int $campaignId)
    {
        $this->onQueue((string) config('omnichannel.wa_broadcast_queue', 'wa-broadcast'));
    }

    public function handle(WaBroadcastCampaignService $service): void
    {
        $campaign = WaBroadcastCampaign::query()->find($this->campaignId);
        if (! $campaign || ! in_array($campaign->status, ['building', 'draft'], true)) {
            return;
        }

        try {
            $service->materializeRecipients($campaign);
            $service->dispatchSendBatch($campaign, 100);
        } catch (\Throwable $e) {
            Log::error('WA broadcast build failed', [
                'campaign_id' => $this->campaignId,
                'error' => $e->getMessage(),
            ]);
            $campaign->status = 'failed';
            $campaign->last_error = $e->getMessage();
            $campaign->save();
        }
    }
}
