<?php

namespace App\Jobs;

use App\Services\Omni\OmnichannelInboundNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyOmniInboundMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(
        public int $conversationId,
        public int $messageId,
    ) {
        $this->onQueue((string) config('omnichannel.flow_queue', 'omnichannel'));
    }

    public function handle(OmnichannelInboundNotificationService $notifier): void
    {
        $notifier->notifyInboundMessage($this->conversationId, $this->messageId);
    }
}
