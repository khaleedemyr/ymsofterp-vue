<?php

namespace App\Services\Meta;

use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Pantau webhook WA Cloud API: deteksi override asing, auto-remediate, notifikasi admin.
 */
class MetaWhatsAppWebhookGuardService
{
    public function __construct(
        private MetaWhatsAppClient $client
    ) {}

    /**
     * @return array{
     *   ok: bool,
     *   anomalies: list<string>,
     *   remediated: bool,
     *   notified: bool,
     *   webhook_configuration: array<string, mixed>,
     *   expected_callback: string
     * }
     */
    public function checkAndRemediate(bool $autoRemediate = true): array
    {
        $expected = $this->expectedCallbackUrl();
        $expectedHost = parse_url($expected, PHP_URL_HOST) ?: 'ymsofterp.com';
        $erpAppId = (string) config('services.meta.app_id', '1302269045204850');

        $config = $this->client->getPhoneWebhookConfiguration();
        $applicationUrl = (string) ($config['application'] ?? '');
        $overrideUrl = (string) ($config['whatsapp_business_account'] ?? '');

        $anomalies = [];

        if ($applicationUrl === '') {
            $anomalies[] = 'Callback application kosong — Webhooks App Dashboard belum terpasang.';
        } elseif (! $this->urlLooksTrusted($applicationUrl, $expectedHost)) {
            $anomalies[] = 'Callback application diganti ke URL asing: '.$applicationUrl;
        }

        if ($overrideUrl !== '' && ! $this->urlLooksTrusted($overrideUrl, $expectedHost)) {
            $anomalies[] = 'Override WABA terdeteksi (chat live dialihkan): '.$overrideUrl;
        }

        $subscribedApps = [];
        try {
            $subscribedApps = $this->client->listSubscribedApps();
        } catch (\Throwable $e) {
            $anomalies[] = 'Gagal baca subscribed_apps: '.$e->getMessage();
        }

        $hasErp = false;
        $foreignApps = [];
        foreach ($subscribedApps as $row) {
            $meta = $row['whatsapp_business_api_data'] ?? $row;
            $id = (string) ($meta['id'] ?? '');
            $name = (string) ($meta['name'] ?? '?');
            if ($id === $erpAppId) {
                $hasErp = true;
            } else {
                $foreignApps[] = "{$name} ({$id})";
            }
        }

        if ($subscribedApps !== [] && ! $hasErp) {
            $anomalies[] = 'App YMSoft ERP tidak ada di subscribed_apps WABA.';
        }

        foreach ($foreignApps as $appLabel) {
            // Sleekflow / n8n / app asing — info saja, tidak selalu berbahaya jika ERP juga subscribed
            if (str_contains($appLabel, '812364635796464')) {
                $anomalies[] = 'App asing masih subscribe WABA: '.$appLabel;
            }
        }

        $remediated = false;
        $clearedOverrideUrl = null;
        if ($autoRemediate && $this->needsRemediate($overrideUrl, $expectedHost, $hasErp, $subscribedApps)) {
            $clearedOverrideUrl = ($overrideUrl !== '' && ! $this->urlLooksTrusted($overrideUrl, $expectedHost))
                ? $overrideUrl
                : null;
            try {
                $this->client->subscribeWabaToApp();
                $remediated = true;
                // Re-check setelah subscribe kosong body (hapus override)
                $config = $this->client->getPhoneWebhookConfiguration();
                $overrideUrl = (string) ($config['whatsapp_business_account'] ?? '');
                if ($overrideUrl !== '' && ! $this->urlLooksTrusted($overrideUrl, $expectedHost)) {
                    $anomalies[] = 'Auto-remediate dijalankan, tapi override masih ada: '.$overrideUrl;
                } else {
                    $anomalies = array_values(array_filter(
                        $anomalies,
                        fn (string $line) => ! str_starts_with($line, 'Override WABA terdeteksi')
                    ));
                    if ($clearedOverrideUrl) {
                        $anomalies[] = 'Override asing sudah dihapus otomatis (sebelumnya: '.$clearedOverrideUrl.')';
                    }
                    Log::warning('Meta WhatsApp webhook override auto-remediated', [
                        'expected' => $expected,
                        'previous_override' => $clearedOverrideUrl,
                    ]);
                }
            } catch (\Throwable $e) {
                $anomalies[] = 'Auto-remediate gagal: '.$e->getMessage();
                Log::error('Meta WhatsApp webhook auto-remediate failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Setelah remediate sukses, baris info "sudah dihapus" tidak dianggap gagal
        $blocking = array_values(array_filter(
            $anomalies,
            fn (string $line) => ! str_starts_with($line, 'Override asing sudah dihapus otomatis')
        ));
        $ok = $blocking === []
            && $this->urlLooksTrusted((string) ($config['application'] ?? ''), $expectedHost)
            && ((string) ($config['whatsapp_business_account'] ?? '') === ''
                || $this->urlLooksTrusted((string) $config['whatsapp_business_account'], $expectedHost));

        $notified = false;
        if ($anomalies !== []) {
            $notified = $this->notifyAdmins($anomalies, $remediated, $config, $expected);
        }

        return [
            'ok' => $ok,
            'anomalies' => $anomalies,
            'remediated' => $remediated,
            'notified' => $notified,
            'webhook_configuration' => $config,
            'expected_callback' => $expected,
        ];
    }

    private function expectedCallbackUrl(): string
    {
        $configured = trim((string) config('services.meta.whatsapp_expected_webhook_url', ''));
        if ($configured !== '') {
            return $configured;
        }

        return rtrim((string) config('app.url'), '/').'/api/webhooks/meta/whatsapp';
    }

    private function urlLooksTrusted(string $url, string $expectedHost): bool
    {
        if ($url === '') {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);
        $expectedHost = strtolower($expectedHost);

        if ($host === $expectedHost || str_ends_with($host, '.'.$expectedHost)) {
            return str_contains($url, '/api/webhooks/meta/whatsapp');
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $subscribedApps
     */
    private function needsRemediate(string $overrideUrl, string $expectedHost, bool $hasErp, array $subscribedApps): bool
    {
        if ($overrideUrl !== '' && ! $this->urlLooksTrusted($overrideUrl, $expectedHost)) {
            return true;
        }

        if ($subscribedApps !== [] && ! $hasErp) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<string>  $anomalies
     * @param  array<string, mixed>  $config
     */
    private function notifyAdmins(array $anomalies, bool $remediated, array $config, string $expected): bool
    {
        $userIds = $this->alertUserIds();
        if ($userIds === []) {
            return false;
        }

        $fingerprint = sha1(json_encode([
            'anomalies' => $anomalies,
            'remediated' => $remediated,
            'config' => $config,
        ], JSON_UNESCAPED_SLASHES) ?: '');

        $cacheKey = 'meta_wa_webhook_guard_alert:'.$fingerprint;
        if (Cache::has($cacheKey)) {
            return false;
        }

        $blockingCount = count(array_filter(
            $anomalies,
            fn (string $line) => ! str_starts_with($line, 'Override asing sudah dihapus otomatis')
                && ! str_starts_with($line, 'Sistem sudah mencoba')
        ));

        $title = ($remediated && $blockingCount === 0)
            ? 'WhatsApp webhook: override asing dihapus otomatis'
            : 'PERINGATAN: WhatsApp webhook mencurigakan';

        $lines = $anomalies;
        if ($remediated) {
            array_unshift($lines, 'Sistem sudah mencoba subscribe ulang WABA (hapus override).');
        }
        $lines[] = 'Callback yang diharapkan: '.$expected;
        $lines[] = 'application: '.((string) ($config['application'] ?? '(kosong)'));
        $lines[] = 'waba_override: '.((string) ($config['whatsapp_business_account'] ?? '(tidak ada)'));

        $message = implode("\n", $lines);
        $sent = false;

        foreach ($userIds as $userId) {
            $notification = NotificationService::create([
                'user_id' => $userId,
                'type' => 'meta_whatsapp_webhook_alert',
                'title' => $title,
                'message' => mb_substr($message, 0, 1900),
                'url' => url('/crm/omnichannel-inbox'),
            ]);
            if ($notification) {
                $sent = true;
            }
        }

        if ($sent) {
            $ttlHours = max(1, (int) config('services.meta.whatsapp_webhook_alert_dedupe_hours', 6));
            Cache::put($cacheKey, true, now()->addHours($ttlHours));
            Log::warning('Meta WhatsApp webhook guard alert sent', [
                'user_ids' => $userIds,
                'anomalies' => $anomalies,
                'remediated' => $remediated,
            ]);
        }

        return $sent;
    }

    /**
     * @return list<int>
     */
    private function alertUserIds(): array
    {
        $raw = (string) config('services.meta.whatsapp_webhook_alert_user_ids', '26');
        $ids = array_map('intval', preg_split('/\s*,\s*/', $raw) ?: []);

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }
}
