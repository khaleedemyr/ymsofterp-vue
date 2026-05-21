<?php

namespace App\Services\Meta;

use Illuminate\Http\Request;

final class MetaWebhookSignature
{
    public static function isValid(Request $request): bool
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
