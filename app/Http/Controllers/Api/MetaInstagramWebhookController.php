<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Meta\MetaMessengerInboundService;
use App\Services\Meta\MetaWebhookSignature;
use App\Support\MetaWebhookTrace;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Webhook Instagram API with Instagram Login (alur YouTube / API setup with Instagram login).
 */
class MetaInstagramWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        MetaWebhookTrace::write('instagram-login', 'GET', $request, 'verify');

        $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        $verifyToken = config('services.meta.webhook_verify_token');

        if ($mode !== 'subscribe' || $verifyToken === null || $verifyToken === '' || $token !== $verifyToken) {
            Log::warning('Meta Instagram Login webhook verify failed', [
                'mode' => $mode,
                'token_match' => $token === $verifyToken,
            ]);
            abort(403, 'Forbidden');
        }

        Log::info('Meta Instagram Login webhook verified');

        return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function handle(Request $request): Response
    {
        MetaWebhookTrace::write('instagram-login', 'POST', $request);

        Log::info('Meta Instagram Login webhook POST received', [
            'content_length' => strlen($request->getContent()),
            'has_signature' => $request->header('X-Hub-Signature-256') !== null,
        ]);

        if (! MetaWebhookSignature::isValid($request)) {
            MetaWebhookTrace::write('instagram-login', 'POST', $request, 'sig_invalid');
            Log::warning('Meta Instagram Login webhook rejected: invalid signature');
            abort(403, 'Invalid signature');
        }

        try {
            app(MetaMessengerInboundService::class)->processPayload($request->all());
        } catch (\Throwable $e) {
            Log::error('Meta Instagram Login webhook processing failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return response('EVENT_RECEIVED', 200);
    }
}
