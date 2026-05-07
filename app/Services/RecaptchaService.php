<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecaptchaService
{
    public function enabled(): bool
    {
        return filled(config('services.recaptcha.site_key'))
            && filled(config('services.recaptcha.secret_key'));
    }

    public function verify(?string $token, ?string $ip = null, ?string $expectedAction = null): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('services.recaptcha.timeout', 5))
                ->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => config('services.recaptcha.secret_key'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]);

            if (! $response->ok()) {
                return false;
            }

            $payload = $response->json();

            if (! ($payload['success'] ?? false)) {
                return false;
            }

            if ($expectedAction && ($payload['action'] ?? null) !== $expectedAction) {
                return false;
            }

            if (array_key_exists('score', $payload)) {
                $minScore = (float) config('services.recaptcha.min_score', 0.5);
                if ((float) $payload['score'] < $minScore) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('reCAPTCHA verification failed', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
