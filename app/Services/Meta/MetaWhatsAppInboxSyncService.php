<?php

namespace App\Services\Meta;

use App\Models\OmniMessage;
use App\Support\MetaPageTokens;
use App\Support\MetaWhatsAppWebhookArchive;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sinkron WA ke omnichannel: replay arsip webhook + percobaan pull Graph (terbatas).
 *
 * Catatan: WhatsApp Cloud API tidak menyediakan list conversation seperti Messenger/IG.
 * Sumber utama tetap webhook; perintah ini untuk menarik ulang payload yang sudah diterima server.
 */
class MetaWhatsAppInboxSyncService
{
    private const MESSAGE_PAGE_LIMIT = 25;

    private const MAX_MESSAGE_PAGES_PER_CONVERSATION = 3;

    private const STOP_AFTER_CONSECUTIVE_EXISTING = 6;

    /**
     * @return array{imported: int, replayed_files: int, pull_attempted: bool, pull_imported: int, accounts: list<array<string, mixed>>}
     */
    public function syncAll(bool $verbose = false, ?int $recentMinutes = null, bool $replayOnly = false): array
    {
        $imported = 0;
        $replayedFiles = 0;
        $pullImported = 0;
        $pullAttempted = false;
        $accounts = [];

        $replay = $this->replayArchivedWebhooks($verbose);
        $imported += $replay['imported'];
        $replayedFiles = $replay['files'];
        $accounts[] = [
            'source' => 'webhook_archive',
            'imported' => $replay['imported'],
            'files' => $replay['files'],
            'skipped_invalid' => $replay['skipped_invalid'],
        ];

        if (! $replayOnly) {
            $pull = $this->tryPullFromPageConversations($verbose, $recentMinutes);
            $pullAttempted = $pull['attempted'];
            $pullImported = $pull['imported'];
            $imported += $pullImported;
            if ($pull['attempted']) {
                $accounts[] = $pull;
            }
        }

        if ($imported > 0) {
            Cache::put('meta_whatsapp_last_sync_at', now()->toIso8601String(), now()->addDay());
        }

        return [
            'imported' => $imported,
            'replayed_files' => $replayedFiles,
            'pull_attempted' => $pullAttempted,
            'pull_imported' => $pullImported,
            'accounts' => $accounts,
        ];
    }

    /**
     * @return array{imported: int, files: int, skipped_invalid: int}
     */
    public function replayArchivedWebhooks(bool $verbose = false): array
    {
        $inbound = app(MetaWhatsAppInboundService::class);
        $imported = 0;
        $files = 0;
        $skippedInvalid = 0;

        foreach (MetaWhatsAppWebhookArchive::pendingFiles() as $path) {
            $raw = File::get($path);
            $payload = json_decode($raw, true);
            if (! is_array($payload)) {
                $skippedInvalid++;
                MetaWhatsAppWebhookArchive::markProcessed($path);

                continue;
            }

            $pending = $this->countNewInboundInWebhookPayload($payload);
            try {
                $inbound->processPayload($payload);
                $imported += $pending;
            } catch (\Throwable $e) {
                Log::warning('WhatsApp webhook replay failed', [
                    'file' => basename($path),
                    'error' => $e->getMessage(),
                ]);
            }

            MetaWhatsAppWebhookArchive::markProcessed($path);
            $files++;
        }

        if ($verbose) {
            Log::info('WhatsApp webhook archive replay finished', [
                'files' => $files,
                'imported' => $imported,
                'skipped_invalid' => $skippedInvalid,
            ]);
        }

        return [
            'imported' => $imported,
            'files' => $files,
            'skipped_invalid' => $skippedInvalid,
        ];
    }

    /**
     * Percobaan pull via Page Conversations (platform=whatsapp) — tidak semua akun mendukung.
     *
     * @return array<string, mixed>
     */
    private function tryPullFromPageConversations(bool $verbose, ?int $recentMinutes): array
    {
        $tokenMap = MetaPageTokens::resolved();
        if ($tokenMap === []) {
            $token = (string) config('services.meta.page_access_token', '');
            $pageId = (string) config('services.meta.page_id', '');
            if ($token !== '' && $pageId !== '') {
                $tokenMap = [$pageId => $token];
            }
        }

        if ($tokenMap === []) {
            return [
                'source' => 'page_conversations',
                'attempted' => false,
                'imported' => 0,
                'note' => 'META_PAGE_TOKENS kosong — skip pull Graph',
            ];
        }

        $version = config('services.meta.graph_api_version', 'v25.0');
        $phoneNumberId = (string) config('services.meta.whatsapp_phone_number_id', '');
        $wabaId = (string) config('services.meta.whatsapp_business_account_id', '');
        $imported = 0;
        $apiErrors = 0;
        $conversations = 0;

        foreach ($tokenMap as $pageId => $token) {
            $response = Http::withToken($token)
                ->acceptJson()
                ->get("https://graph.facebook.com/{$version}/{$pageId}/conversations", [
                    'platform' => 'whatsapp',
                    'limit' => 30,
                ]);

            if (! $response->successful()) {
                $apiErrors++;

                return [
                    'source' => 'page_conversations',
                    'attempted' => true,
                    'imported' => 0,
                    'page_id' => $pageId,
                    'api_errors' => $apiErrors,
                    'error' => 'Graph tidak mengembalikan conversations WhatsApp: '.mb_substr($response->body(), 0, 300),
                    'hint' => 'Normal untuk Cloud API — andalkan webhook + meta:sync-whatsapp-inbox --replay',
                ];
            }

            $rows = $response->json('data') ?? [];
            $conversations = is_array($rows) ? count($rows) : 0;
            $messagesChecked = 0;
            $skippedExisting = 0;

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $threadId = (string) ($row['id'] ?? '');
                if ($threadId === '') {
                    continue;
                }

                $this->pullThreadMessages(
                    $token,
                    $version,
                    $threadId,
                    $wabaId,
                    $phoneNumberId,
                    $imported,
                    $messagesChecked,
                    $skippedExisting,
                    $apiErrors,
                    $recentMinutes
                );
            }

            return [
                'source' => 'page_conversations',
                'attempted' => true,
                'imported' => $imported,
                'page_id' => $pageId,
                'conversations' => $conversations,
                'messages_checked' => $messagesChecked,
                'skipped_existing' => $skippedExisting,
                'api_errors' => $apiErrors,
            ];
        }

        return [
            'source' => 'page_conversations',
            'attempted' => false,
            'imported' => 0,
        ];
    }

    private function pullThreadMessages(
        string $token,
        string $version,
        string $threadId,
        string $wabaId,
        string $phoneNumberId,
        int &$imported,
        int &$messagesChecked,
        int &$skippedExisting,
        int &$apiErrors,
        ?int $recentMinutes
    ): void {
        $cutoff = $recentMinutes !== null && $recentMinutes > 0
            ? now()->subMinutes($recentMinutes)
            : null;

        $url = "https://graph.facebook.com/{$version}/{$threadId}/messages";
        $query = [
            'fields' => 'id,created_time,from,to,message',
            'limit' => self::MESSAGE_PAGE_LIMIT,
        ];
        $pages = 0;
        $consecutiveExisting = 0;
        $inbound = app(MetaWhatsAppInboundService::class);

        while ($pages < self::MAX_MESSAGE_PAGES_PER_CONVERSATION) {
            $response = Http::withToken($token)->acceptJson()->get($url, $query);
            if (! $response->successful()) {
                $apiErrors++;

                return;
            }

            $rows = $response->json('data') ?? [];
            if ($rows !== [] && $this->isAscendingByTime($rows)) {
                $rows = array_reverse($rows);
            }

            foreach ($rows as $msg) {
                if (! is_array($msg)) {
                    continue;
                }

                $messagesChecked++;
                $metaId = (string) ($msg['id'] ?? '');
                if ($metaId === '') {
                    continue;
                }

                if (OmniMessage::query()->where('meta_message_id', $metaId)->exists()) {
                    $skippedExisting++;
                    $consecutiveExisting++;
                    if ($consecutiveExisting >= self::STOP_AFTER_CONSECUTIVE_EXISTING) {
                        return;
                    }

                    continue;
                }

                $consecutiveExisting = 0;

                if ($cutoff !== null) {
                    $created = (string) ($msg['created_time'] ?? '');
                    try {
                        if ($created !== '' && Carbon::parse($created)->lt($cutoff)) {
                            return;
                        }
                    } catch (\Throwable) {
                        // ignore
                    }
                }

                $from = (string) ($msg['from']['id'] ?? '');
                if ($from === '') {
                    continue;
                }

                $payload = [
                    'object' => 'whatsapp_business_account',
                    'entry' => [[
                        'id' => $wabaId,
                        'changes' => [[
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '',
                                    'phone_number_id' => $phoneNumberId,
                                ],
                                'contacts' => [[
                                    'profile' => ['name' => ''],
                                    'wa_id' => $from,
                                ]],
                                'messages' => [[
                                    'from' => $from,
                                    'id' => $metaId,
                                    'timestamp' => (string) (isset($msg['created_time'])
                                        ? Carbon::parse((string) $msg['created_time'])->timestamp
                                        : now()->timestamp),
                                    'type' => 'text',
                                    'text' => ['body' => (string) ($msg['message'] ?? '')],
                                ]],
                            ],
                        ]],
                    ]],
                ];

                $before = OmniMessage::query()->count();
                $inbound->processPayload($payload);
                $after = OmniMessage::query()->count();
                if ($after > $before) {
                    $imported++;
                }
            }

            $next = $response->json('paging.next');
            if (! is_string($next) || $next === '') {
                break;
            }

            $url = $next;
            $query = [];
            $pages++;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function countNewInboundInWebhookPayload(array $payload): int
    {
        $count = 0;

        foreach ($payload['entry'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            foreach ($entry['changes'] ?? [] as $change) {
                if (! is_array($change) || ($change['field'] ?? '') !== 'messages') {
                    continue;
                }
                foreach ($change['value']['messages'] ?? [] as $message) {
                    if (! is_array($message)) {
                        continue;
                    }
                    $metaId = (string) ($message['id'] ?? '');
                    if ($metaId === '') {
                        continue;
                    }
                    if (! OmniMessage::query()->where('meta_message_id', $metaId)->exists()) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function isAscendingByTime(array $rows): bool
    {
        if (count($rows) < 2) {
            return false;
        }

        try {
            $first = Carbon::parse((string) ($rows[0]['created_time'] ?? ''));
            $last = Carbon::parse((string) ($rows[count($rows) - 1]['created_time'] ?? ''));

            return $first->lt($last);
        } catch (\Throwable) {
            return false;
        }
    }
}
