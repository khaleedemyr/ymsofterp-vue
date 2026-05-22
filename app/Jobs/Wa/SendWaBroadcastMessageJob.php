<?php

namespace App\Jobs\Wa;

use App\Models\WaBroadcastRecipient;
use App\Services\Meta\MetaWhatsAppClient;
use App\Services\Wa\WaBroadcastCampaignService;
use App\Services\Wa\WaBroadcastDailyLimitService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWaBroadcastMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $recipientId)
    {
        $this->onQueue((string) config('omnichannel.wa_broadcast_queue', 'wa-broadcast'));
    }

    public function handle(
        MetaWhatsAppClient $wa,
        WaBroadcastDailyLimitService $dailyLimit,
        WaBroadcastCampaignService $campaignService
    ): void {
        $recipient = WaBroadcastRecipient::query()->with('campaign')->find($this->recipientId);
        if (! $recipient || $recipient->status !== 'pending') {
            return;
        }

        $campaign = $recipient->campaign;
        if (! $campaign || $campaign->status !== 'running') {
            return;
        }

        if (! $dailyLimit->canSend(1, $campaign->phone_number_id)) {
            $campaign->status = 'paused';
            $campaign->last_error = 'Kuota harian broadcast habis.';
            $campaign->save();

            return;
        }

        $recipient->status = 'queued';
        $recipient->save();

        $to = $recipient->wa_id ?: $recipient->phone_normalized;

        try {
            if ($campaign->message_type === 'template') {
                $templateName = (string) $campaign->template_name;
                if ($templateName === '') {
                    throw new \RuntimeException('Nama template WA wajib diisi.');
                }
                $params = $campaign->template_body_params ?? [];
                if (! is_array($params)) {
                    $params = [];
                }
                $response = $wa->sendTemplate(
                    $to,
                    $templateName,
                    (string) ($campaign->template_language ?: 'id'),
                    array_map('strval', $params),
                    $campaign->phone_number_id
                );
            } else {
                $text = trim((string) $campaign->session_text);
                if ($text === '') {
                    throw new \RuntimeException('Isi pesan kosong.');
                }
                $response = $wa->sendText($to, $text, $campaign->phone_number_id);
            }

            $recipient->status = 'sent';
            $recipient->meta_message_id = (string) ($response['messages'][0]['id'] ?? '');
            $recipient->sent_at = now();
            $recipient->error_message = null;
            $recipient->save();

            $dailyLimit->incrementSent(1, $campaign->phone_number_id);
        } catch (\Throwable $e) {
            $recipient->status = 'failed';
            $recipient->error_message = mb_substr($e->getMessage(), 0, 2000);
            $recipient->save();

            Log::warning('WA broadcast send failed', [
                'recipient_id' => $recipient->id,
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);
        }

        $campaignService->syncCampaignStats($campaign);

        if ($campaign->status === 'running') {
            $campaignService->dispatchSendBatch($campaign, 50);
        }
    }
}
