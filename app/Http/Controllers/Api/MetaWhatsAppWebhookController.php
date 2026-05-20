<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MetaWhatsAppWebhookController extends Controller
{
    /**
     * Meta webhook verification (GET hub.mode=subscribe).
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        $verifyToken = config('services.meta.webhook_verify_token');

        if ($mode !== 'subscribe' || $verifyToken === null || $verifyToken === '' || $token !== $verifyToken) {
            Log::warning('Meta WhatsApp webhook verify failed', [
                'mode' => $mode,
                'token_match' => $token === $verifyToken,
            ]);
            abort(403, 'Forbidden');
        }

        Log::info('Meta WhatsApp webhook verified');

        return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Incoming WhatsApp events (messages, statuses).
     */
    public function handle(Request $request): Response
    {
        Log::warning('Meta WhatsApp webhook POST hit', [
            'has_signature' => $request->header('X-Hub-Signature-256') !== null,
            'content_length' => strlen($request->getContent()),
        ]);

        if (! $this->isValidSignature($request)) {
            Log::warning('Meta WhatsApp webhook rejected: invalid signature', [
                'has_signature_header' => $request->header('X-Hub-Signature-256') !== null,
                'app_secret_configured' => (string) config('services.meta.app_secret') !== '',
                'skip_signature' => config('services.meta.webhook_skip_signature_verify'),
            ]);
            abort(403, 'Invalid signature');
        }

        Log::warning('Meta WhatsApp webhook received', [
            'payload' => $request->all(),
        ]);

        return response('EVENT_RECEIVED', 200);
    }

    private function isValidSignature(Request $request): bool
    {
        if (config('services.meta.webhook_skip_signature_verify')) {
            return true;
        }

        $secret = config('services.meta.app_secret');

        if ($secret === null || $secret === '') {
            return true;
        }

        $signature = $request->header('X-Hub-Signature-256');

        if ($signature === null || $signature === '') {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
