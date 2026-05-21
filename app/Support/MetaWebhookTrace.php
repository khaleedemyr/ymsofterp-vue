<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Jejak file mentah untuk debug webhook Meta (tidak bergantung LOG_CHANNEL / level).
 */
final class MetaWebhookTrace
{
    public static function write(string $channel, string $method, Request $request, ?string $note = null): void
    {
        $objectHint = '';
        if ($method === 'POST') {
            $body = $request->getContent();
            if ($body !== '') {
                $decoded = json_decode($body, true);
                if (is_array($decoded)) {
                    $objectHint = sprintf(
                        ' object=%s entries=%d',
                        (string) ($decoded['object'] ?? '-'),
                        count($decoded['entry'] ?? [])
                    );
                }
            }
        }

        $line = sprintf(
            "[%s] channel=%s %s path=/%s content_len=%d has_sig=%s ip=%s%s%s\n",
            date('c'),
            $channel,
            $method,
            $request->path(),
            strlen($request->getContent()),
            $request->header('X-Hub-Signature-256') !== null ? '1' : '0',
            $request->ip() ?? '-',
            $objectHint,
            $note !== null && $note !== '' ? ' note='.$note : ''
        );

        @file_put_contents(self::path($channel), $line, FILE_APPEND | LOCK_EX);
    }

    private static function path(string $channel): string
    {
        $safe = preg_replace('/[^a-z0-9_-]/', '', strtolower($channel)) ?: 'meta';

        return storage_path('logs/'.$safe.'-webhook.trace.log');
    }
}
