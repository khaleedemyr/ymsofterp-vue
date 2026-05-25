<?php

namespace App\Services\Meta;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MetaWhatsAppClient
{
    public function sendText(string $toWaId, string $text, ?string $phoneNumberId = null): array
    {
        $token = config('services.meta.whatsapp_access_token');
        $phoneNumberId = $phoneNumberId ?: config('services.meta.whatsapp_phone_number_id');
        $version = config('services.meta.graph_api_version', 'v25.0');

        if (! $token || ! $phoneNumberId) {
            throw new RuntimeException('Meta WhatsApp API credentials are not configured.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $toWaId,
                'type' => 'text',
                'text' => [
                    'body' => $text,
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Meta WhatsApp send failed: '.$response->body(),
                $response->status()
            );
        }

        return $response->json();
    }

    public function uploadMedia(string $absolutePath, string $mimeType, ?string $phoneNumberId = null): string
    {
        $token = config('services.meta.whatsapp_access_token');
        $phoneNumberId = $phoneNumberId ?: config('services.meta.whatsapp_phone_number_id');
        $version = config('services.meta.graph_api_version', 'v25.0');

        if (! $token || ! $phoneNumberId) {
            throw new RuntimeException('Meta WhatsApp API credentials are not configured.');
        }

        $response = Http::withToken($token)
            ->attach('file', file_get_contents($absolutePath), basename($absolutePath))
            ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/media", [
                'messaging_product' => 'whatsapp',
                'type' => $mimeType,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Meta WhatsApp media upload failed: '.$response->body(),
                $response->status()
            );
        }

        $id = (string) ($response->json('id') ?? '');
        if ($id === '') {
            throw new RuntimeException('Meta WhatsApp media upload returned no id.');
        }

        return $id;
    }

    /**
     * Unduh media inbound dari Graph API (URL dari Meta kedaluwarsa — simpan ke storage lokal).
     *
     * @return array{local_media_url: string, media_filename: string, media_mime: string}|null
     */
    public function downloadMedia(string $mediaId, ?string $preferredFilename = null, ?string $phoneNumberId = null): ?array
    {
        $token = config('services.meta.whatsapp_access_token');
        $version = config('services.meta.graph_api_version', 'v25.0');

        if (! $token || $mediaId === '') {
            return null;
        }

        $metaResponse = Http::withToken($token)
            ->acceptJson()
            ->get("https://graph.facebook.com/{$version}/{$mediaId}");

        if (! $metaResponse->successful()) {
            Log::warning('Meta WhatsApp media metadata failed', [
                'media_id' => $mediaId,
                'status' => $metaResponse->status(),
                'body' => $metaResponse->body(),
            ]);

            return null;
        }

        $downloadUrl = (string) ($metaResponse->json('url') ?? '');
        $mime = (string) ($metaResponse->json('mime_type') ?? 'application/octet-stream');

        if ($downloadUrl === '') {
            return null;
        }

        $fileResponse = Http::withToken($token)->get($downloadUrl);

        if (! $fileResponse->successful()) {
            Log::warning('Meta WhatsApp media download failed', [
                'media_id' => $mediaId,
                'status' => $fileResponse->status(),
            ]);

            return null;
        }

        $filename = $preferredFilename ?: ('wa-media.'.$this->extensionFromMime($mime));
        $filename = $this->sanitizeFilename($filename);
        $storedPath = 'omni-inbound/'.date('Y/m').'/'.$mediaId.'_'.$filename;

        Storage::disk('public')->put($storedPath, $fileResponse->body());

        $relative = Storage::disk('public')->url($storedPath);
        $publicUrl = str_starts_with($relative, 'http') ? $relative : url($relative);

        return [
            'local_media_path' => $storedPath,
            'local_media_url' => $publicUrl,
            'media_filename' => $filename,
            'media_mime' => $mime,
        ];
    }

    public function sendImage(string $toWaId, string $mediaId, ?string $caption = null, ?string $phoneNumberId = null): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $toWaId,
            'type' => 'image',
            'image' => ['id' => $mediaId],
        ];
        if ($caption !== null && $caption !== '') {
            $payload['image']['caption'] = $caption;
        }

        return $this->postMessage($payload, $phoneNumberId);
    }

    /**
     * Kirim pesan template resmi WA (wajib untuk broadcast di luar jendela 24 jam).
     *
     * @param  list<string>  $bodyParameters
     * @return array<string, mixed>
     */
    public function sendTemplate(
        string $toWaId,
        string $templateName,
        string $languageCode = 'id',
        array $bodyParameters = [],
        ?string $phoneNumberId = null
    ): array {
        $components = [];
        if ($bodyParameters !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(
                    fn (string $text) => ['type' => 'text', 'text' => $text],
                    $bodyParameters
                ),
            ];
        }

        return $this->postMessage([
            'messaging_product' => 'whatsapp',
            'to' => $toWaId,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $languageCode],
                'components' => $components,
            ],
        ], $phoneNumberId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listMessageTemplates(?string $wabaId = null): array
    {
        $token = config('services.meta.whatsapp_access_token');
        $wabaId = $wabaId ?: config('services.meta.whatsapp_business_account_id');
        $version = config('services.meta.graph_api_version', 'v25.0');

        if (! $token || ! $wabaId) {
            return [];
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->get("https://graph.facebook.com/{$version}/{$wabaId}/message_templates", [
                'fields' => 'name,language,status,category',
                'limit' => 100,
            ]);

        if (! $response->successful()) {
            Log::warning('Meta WhatsApp list templates failed', ['body' => $response->body()]);

            return [];
        }

        $data = $response->json('data') ?? [];

        return is_array($data) ? $data : [];
    }

    /**
     * Ajukan template pesan baru ke Meta (status awal biasanya PENDING, menunggu review).
     *
     * @param  list<string>  $bodyExampleRow  Satu nilai contoh per variabel {{1}}, {{2}}, … (urutan sama)
     * @return array<string, mixed>
     */
    public function createMessageTemplate(
        string $name,
        string $category,
        string $language,
        string $bodyText,
        array $bodyExampleRow = [],
        ?string $wabaId = null
    ): array {
        $token = config('services.meta.whatsapp_access_token');
        $wabaId = $wabaId ?: config('services.meta.whatsapp_business_account_id');
        $version = config('services.meta.graph_api_version', 'v25.0');

        if (! $token || ! $wabaId) {
            throw new RuntimeException('Meta WhatsApp API credentials are not configured.');
        }

        $bodyComponent = [
            'type' => 'BODY',
            'text' => $bodyText,
        ];

        if (preg_match('/\{\{\d+\}\}/', $bodyText) !== 0) {
            if ($bodyExampleRow === []) {
                throw new RuntimeException('Isi contoh variabel untuk setiap {{1}}, {{2}}, … pada body template.');
            }
            $bodyComponent['example'] = [
                'body_text' => [array_values($bodyExampleRow)],
            ];
        }

        $payload = [
            'name' => $name,
            'category' => strtoupper($category),
            'language' => $language,
            'components' => [$bodyComponent],
        ];

        $response = Http::withToken($token)
            ->acceptJson()
            ->post("https://graph.facebook.com/{$version}/{$wabaId}/message_templates", $payload);

        if (! $response->successful()) {
            $message = $response->json('error.message') ?? $response->body();
            throw new RuntimeException('Meta template creation failed: '.$message, $response->status());
        }

        return $response->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getPhoneNumberDetails(?string $phoneNumberId = null): array
    {
        $token = config('services.meta.whatsapp_access_token');
        $phoneNumberId = $phoneNumberId ?: config('services.meta.whatsapp_phone_number_id');
        $version = config('services.meta.graph_api_version', 'v25.0');

        if (! $token || ! $phoneNumberId) {
            throw new RuntimeException('META_WHATSAPP_ACCESS_TOKEN dan META_WHATSAPP_PHONE_NUMBER_ID wajib diisi.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->get("https://graph.facebook.com/{$version}/{$phoneNumberId}", [
                'fields' => 'display_phone_number,verified_name,quality_rating,code_verification_status,platform_type',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Phone number lookup failed: '.$response->body(),
                $response->status()
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Subscription webhook level App (object + fields) — wajib ada messages untuk WA masuk.
     *
     * @return list<array<string, mixed>>
     */
    public function listAppWebhookSubscriptions(): array
    {
        $appId = config('services.meta.app_id');
        $appSecret = config('services.meta.app_secret');
        $version = config('services.meta.graph_api_version', 'v25.0');

        if (! $appId || ! $appSecret) {
            throw new RuntimeException('META_APP_ID dan META_APP_SECRET wajib diisi (app access token).');
        }

        // Endpoint /{app-id}/subscriptions memerlukan app access token: {app-id}|{app-secret}
        $appToken = trim((string) $appId).'|'.trim((string) $appSecret);

        $response = Http::acceptJson()
            ->get("https://graph.facebook.com/{$version}/{$appId}/subscriptions", [
                'access_token' => $appToken,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'App subscriptions failed: '.$response->body(),
                $response->status()
            );
        }

        $data = $response->json('data') ?? [];

        return is_array($data) ? $data : [];
    }

    /**
     * App mana saja yang subscribe webhook WABA ini (mis. Sleekflow vs ERP).
     *
     * @return list<array<string, mixed>>
     */
    public function listSubscribedApps(?string $wabaId = null): array
    {
        $token = config('services.meta.whatsapp_access_token');
        $wabaId = $wabaId ?: config('services.meta.whatsapp_business_account_id');
        $version = config('services.meta.graph_api_version', 'v25.0');

        if (! $token || ! $wabaId) {
            throw new RuntimeException('META_WHATSAPP_ACCESS_TOKEN dan META_WHATSAPP_BUSINESS_ACCOUNT_ID wajib diisi.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->get("https://graph.facebook.com/{$version}/{$wabaId}/subscribed_apps");

        if (! $response->successful()) {
            throw new RuntimeException(
                'List subscribed_apps failed: '.$response->body(),
                $response->status()
            );
        }

        $data = $response->json('data') ?? [];

        return is_array($data) ? $data : [];
    }

    /**
     * Subscribe app ERP (pemilik token) ke WABA — jalankan setelah Sleekflow dilepas.
     *
     * @return array<string, mixed>
     */
    public function subscribeWabaToApp(?string $wabaId = null): array
    {
        $token = config('services.meta.whatsapp_access_token');
        $wabaId = $wabaId ?: config('services.meta.whatsapp_business_account_id');
        $version = config('services.meta.graph_api_version', 'v25.0');

        if (! $token || ! $wabaId) {
            throw new RuntimeException('META_WHATSAPP_ACCESS_TOKEN dan META_WHATSAPP_BUSINESS_ACCOUNT_ID wajib diisi.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->post("https://graph.facebook.com/{$version}/{$wabaId}/subscribed_apps");

        if (! $response->successful()) {
            throw new RuntimeException(
                'Subscribe WABA failed: '.$response->body(),
                $response->status()
            );
        }

        return $response->json() ?? [];
    }

    public function sendDocument(string $toWaId, string $mediaId, ?string $caption = null, ?string $filename = null, ?string $phoneNumberId = null): array
    {
        $doc = ['id' => $mediaId];
        if ($caption !== null && $caption !== '') {
            $doc['caption'] = $caption;
        }
        if ($filename !== null && $filename !== '') {
            $doc['filename'] = $filename;
        }

        return $this->postMessage([
            'messaging_product' => 'whatsapp',
            'to' => $toWaId,
            'type' => 'document',
            'document' => $doc,
        ], $phoneNumberId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postMessage(array $payload, ?string $phoneNumberId = null): array
    {
        $token = config('services.meta.whatsapp_access_token');
        $phoneNumberId = $phoneNumberId ?: config('services.meta.whatsapp_phone_number_id');
        $version = config('services.meta.graph_api_version', 'v25.0');

        if (! $token || ! $phoneNumberId) {
            throw new RuntimeException('Meta WhatsApp API credentials are not configured.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Meta WhatsApp send failed: '.$response->body(),
                $response->status()
            );
        }

        return $response->json();
    }

    private function extensionFromMime(string $mime): string
    {
        return match (true) {
            str_contains($mime, 'jpeg'), $mime === 'image/jpg' => 'jpg',
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'webp') => 'webp',
            str_contains($mime, 'gif') => 'gif',
            str_contains($mime, 'pdf') => 'pdf',
            str_contains($mime, 'mpeg'), str_contains($mime, 'mp3') => 'mp3',
            str_contains($mime, 'ogg') => 'ogg',
            str_contains($mime, 'mp4'), str_contains($mime, 'video') => 'mp4',
            default => 'bin',
        };
    }

    private function sanitizeFilename(string $name): string
    {
        $name = preg_replace('/[^\p{L}\p{N}._\- ]/u', '_', $name) ?? 'file';
        $name = trim($name, ' .');

        return $name !== '' ? $name : 'file.bin';
    }
}
