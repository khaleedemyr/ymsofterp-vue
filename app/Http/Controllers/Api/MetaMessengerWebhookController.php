<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Meta\MetaMessengerInboundService;
use App\Services\Meta\MetaWebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MetaMessengerWebhookController extends Controller
{
    /**
     * Verifikasi webhook Messenger / Instagram (GET hub.mode=subscribe).
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        $verifyToken = config('services.meta.webhook_verify_token');

        if ($mode !== 'subscribe' || $verifyToken === null || $verifyToken === '' || $token !== $verifyToken) {
            Log::warning('Meta Messenger webhook verify failed', [
                'mode' => $mode,
                'token_match' => $token === $verifyToken,
            ]);
            abort(403, 'Forbidden');
        }

        Log::info('Meta Messenger/Instagram webhook verified');

        return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Event pesan masuk Page (Messenger) & Instagram DM.
     */
    public function handle(Request $request): Response
    {
        Log::info('Meta Messenger/Instagram webhook POST received', [
            'content_length' => strlen($request->getContent()),
            'has_signature' => $request->header('X-Hub-Signature-256') !== null,
        ]);

        if (! MetaWebhookSignature::isValid($request)) {
            Log::warning('Meta Messenger webhook rejected: invalid signature');
            abort(403, 'Invalid signature');
        }

        try {
            app(MetaMessengerInboundService::class)->processPayload($request->all());
        } catch (\Throwable $e) {
            Log::error('Meta Messenger webhook processing failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return response('EVENT_RECEIVED', 200);
    }
}
