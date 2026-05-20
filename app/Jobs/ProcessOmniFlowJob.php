<?php

namespace App\Jobs;

use App\Services\Omni\OmniFlowRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessOmniFlowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public int $conversationId,
        public int $messageId,
    ) {
        $this->onQueue((string) config('omnichannel.flow_queue', 'omnichannel'));
    }

    public function handle(OmniFlowRunner $runner): void
    {
        $runner->dispatchForInboundMessage($this->conversationId, $this->messageId);
    }
}
