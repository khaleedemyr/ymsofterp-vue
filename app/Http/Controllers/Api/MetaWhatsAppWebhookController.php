<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Meta\MetaWhatsAppInboundService;
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
        if (! $this->isValidSignature($request)) {
            Log::warning('Meta WhatsApp webhook rejected: invalid signature');
            abort(403, 'Invalid signature');
        }

        try {
            app(MetaWhatsAppInboundService::class)->processPayload($request->all());
        } catch (\Throwable $e) {
            Log::error('Meta WhatsApp webhook processing failed', [
                'error' => $e->getMessage(),
            ]);
        }

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

        $payload = $request->getContent();

        if ($payload === '') {
            $payload = file_get_contents('php://input') ?: '';
        }

        $expected = 'sha256='.hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }
}
