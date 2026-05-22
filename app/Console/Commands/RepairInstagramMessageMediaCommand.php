<?php

namespace App\Console\Commands;

use App\Models\OmniConversation;
use App\Models\OmniMessage;
use App\Services\Meta\MetaInstagramInboxSyncService;
use App\Support\MetaInstagramTokens;
use Illuminate\Console\Command;

class RepairInstagramMessageMediaCommand extends Command
{
    protected $signature = 'meta:repair-instagram-message-media {--limit=200 : Maks pesan diperbaiki}';

    protected $description = 'Perbaiki pesan Instagram inbound yang tampil kosong (ambil ulang attachment dari Meta)';

    public function handle(MetaInstagramInboxSyncService $sync): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $tokens = MetaInstagramTokens::resolved();

        if ($tokens === []) {
            $this->error('Token Instagram kosong.');

            return self::FAILURE;
        }

        $messages = OmniMessage::query()
            ->where('direction', 'inbound')
            ->whereHas('conversation', fn ($q) => $q->where('channel', 'instagram'))
            ->where(function ($q) {
                $q->whereNull('body')
                    ->orWhere('body', '')
                    ->orWhere('message_type', 'text');
            })
            ->whereNotNull('meta_message_id')
            ->orderByDesc('sent_at')
            ->limit($limit)
            ->get();

        $fixed = 0;

        foreach ($messages as $message) {
            $conversation = OmniConversation::query()->find($message->conversation_id);
            if (! $conversation) {
                continue;
            }

            $igId = (string) $conversation->phone_number_id;
            $token = $tokens[$igId] ?? array_values($tokens)[0] ?? null;
            if (! is_string($token) || $token === '') {
                continue;
            }

            $repaired = $sync->repairMessageMedia($message, $token, $igId);
            if ($repaired) {
                $fixed++;
                $this->line("OK message #{$message->id}");
            }
        }

        $this->info("Diperbaiki: {$fixed} pesan.");

        return self::SUCCESS;
    }
}
