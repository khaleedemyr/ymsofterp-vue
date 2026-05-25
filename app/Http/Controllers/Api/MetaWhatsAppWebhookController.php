<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Meta\MetaWebhookSignature;
use App\Services\Meta\MetaWhatsAppInboundService;
use App\Support\MetaWebhookTrace;
use App\Support\MetaWhatsAppWebhookArchive;
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
        MetaWebhookTrace::write('whatsapp', 'GET', $request, 'verify');

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
        MetaWebhookTrace::write('whatsapp', 'POST', $request);

        // Arsip dulu (sebelum cek signature) supaya bisa di-replay jika META_APP_SECRET salah.
        $archivedPath = MetaWhatsAppWebhookArchive::storeFromRequest($request);

        Log::info('Meta WhatsApp webhook POST received', [
            'content_length' => strlen($request->getContent()),
            'has_signature' => $request->header('X-Hub-Signature-256') !== null,
            'archived' => $archivedPath !== null ? basename($archivedPath) : null,
        ]);

        if (! MetaWebhookSignature::isValid($request)) {
            MetaWebhookTrace::write('whatsapp', 'POST', $request, 'sig_invalid');
            Log::warning('Meta WhatsApp webhook rejected: invalid signature', [
                'hint' => 'Periksa META_APP_SECRET app YMSoft ERP (1302269045204850), lalu replay arsip: php artisan meta:sync-whatsapp-inbox --replay',
            ]);
            abort(403, 'Invalid signature');
        }

        try {
            app(MetaWhatsAppInboundService::class)->processPayload($request->all());
            MetaWebhookTrace::write('whatsapp', 'POST', $request, 'processed');
        } catch (\Throwable $e) {
            MetaWebhookTrace::write('whatsapp', 'POST', $request, 'error');
            Log::error('Meta WhatsApp webhook processing failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return response('EVENT_RECEIVED', 200);
    }
}
