<?php

namespace App\Services\Omni;

use App\Models\OmniConversation;
use App\Services\Meta\MetaWhatsAppClient;
use App\Support\OmniFlowDefinition;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OmniWhatsappOutboundService
{
    /**
     * Kirim pesan WhatsApp sesuai message_mode (teks, interactive, media).
     *
     * @param  array<string, mixed>  $config
     * @return array{
     *   result: array<string, mixed>,
     *   message_type: string,
     *   body: string,
     *   meta_message_id: string,
     *   payload: array<string, mixed>
     * }
     */
    public function send(OmniConversation $conversation, string $body, array $config = []): array
    {
        if ((string) $conversation->channel !== 'whatsapp') {
            throw new RuntimeException('Mode pesan lanjutan hanya didukung untuk WhatsApp.');
        }

        $merged = array_merge($config, ['body' => $body]);
        $merged = OmniFlowDefinition::normalizeSendMessageConfig($merged);
        $messageMode = (string) ($merged['message_mode'] ?? 'text');

        if (in_array($messageMode, ['image', 'document'], true)) {
            return $this->sendMedia($conversation, $merged, $messageMode);
        }

        $body = trim((string) ($merged['body'] ?? ''));
        if ($body === '') {
            throw new RuntimeException('Isi pesan wajib diisi.');
        }

        $body = $this->replacePlaceholders($body, $conversation);
        $wa = app(MetaWhatsAppClient::class);
        $previewSuffix = '';

        if ($messageMode === 'quick_reply') {
            $buttons = $this->normalizeQuickReplyButtons($merged['buttons'] ?? []);
            $result = $wa->sendInteractiveReplyButtons(
                $conversation->external_contact_id,
                $body,
                $buttons,
                $conversation->phone_number_id
            );
            $messageType = 'interactive';
            $previewSuffix = ' · Tombol: '.implode(', ', array_column($buttons, 'title'));
        } elseif ($messageMode === 'cta_url') {
            $cta = is_array($merged['cta_url'] ?? null) ? $merged['cta_url'] : [];
            $displayText = $this->replacePlaceholders(trim((string) ($cta['display_text'] ?? 'Buka link')), $conversation);
            $url = $this->replacePlaceholders(trim((string) ($cta['url'] ?? '')), $conversation);
            $result = $wa->sendInteractiveCtaUrl(
                $conversation->external_contact_id,
                $body,
                $displayText,
                $url,
                $conversation->phone_number_id
            );
            $messageType = 'interactive';
            $previewSuffix = ' · ['.$displayText.']';
        } else {
            $result = $wa->sendText(
                $conversation->external_contact_id,
                $body,
                $conversation->phone_number_id
            );
            $messageType = 'text';
        }

        $displayBody = mb_substr($body.$previewSuffix, 0, 500);
        $payload = is_array($result) ? $result : [];

        return [
            'result' => $result,
            'message_type' => $messageType,
            'body' => $displayBody,
            'meta_message_id' => (string) ($result['messages'][0]['id'] ?? ''),
            'payload' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{
     *   result: array<string, mixed>,
     *   message_type: string,
     *   body: string,
     *   meta_message_id: string,
     *   payload: array<string, mixed>
     * }
     */
    private function sendMedia(OmniConversation $conversation, array $config, string $messageMode): array
    {
        $storagePath = trim((string) ($config['media_path'] ?? ''));
        if ($storagePath === '' || ! Storage::disk('public')->exists($storagePath)) {
            throw new RuntimeException('Berkas lampiran tidak ditemukan. Unggah ulang di pengaturan template.');
        }

        $absolutePath = Storage::disk('public')->path($storagePath);
        $mime = trim((string) ($config['media_mime'] ?? ''));
        if ($mime === '') {
            $mime = mime_content_type($absolutePath) ?: 'application/octet-stream';
        }
        $filename = trim((string) ($config['media_filename'] ?? ''));
        if ($filename === '') {
            $filename = basename($storagePath);
        }

        $caption = trim((string) ($config['body'] ?? ''));
        if ($caption !== '') {
            $caption = $this->replacePlaceholders($caption, $conversation);
        }

        $wa = app(MetaWhatsAppClient::class);
        $mediaId = $wa->uploadMedia($absolutePath, $mime, $conversation->phone_number_id);

        if ($messageMode === 'image') {
            $result = $wa->sendImage(
                $conversation->external_contact_id,
                $mediaId,
                $caption !== '' ? $caption : null,
                $conversation->phone_number_id
            );
            $messageType = 'image';
            $displayBody = $caption !== '' ? $caption : '[Gambar]';
        } else {
            $result = $wa->sendDocument(
                $conversation->external_contact_id,
                $mediaId,
                $caption !== '' ? $caption : null,
                $filename,
                $conversation->phone_number_id
            );
            $messageType = 'document';
            $displayBody = $caption !== '' ? $caption : '[PDF: '.$filename.']';
        }

        $localUrl = $this->publicStorageUrl($storagePath);
        $payload = array_merge(is_array($result) ? $result : [], [
            'local_media_url' => $localUrl,
            'media_filename' => $filename,
            'media_mime' => $mime,
        ]);

        return [
            'result' => $result,
            'message_type' => $messageType,
            'body' => $displayBody,
            'meta_message_id' => (string) ($result['messages'][0]['id'] ?? ''),
            'payload' => $payload,
        ];
    }

    /**
     * @param  mixed  $buttonsRaw
     * @return list<array{id: string, title: string}>
     */
    private function normalizeQuickReplyButtons(mixed $buttonsRaw): array
    {
        if (! is_array($buttonsRaw)) {
            return [];
        }

        $out = [];
        foreach ($buttonsRaw as $i => $btn) {
            if (! is_array($btn)) {
                continue;
            }
            $title = trim((string) ($btn['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $id = trim((string) ($btn['id'] ?? ''));
            if ($id === '') {
                $id = 'btn_'.($i + 1);
            }
            $out[] = [
                'id' => preg_replace('/[^a-zA-Z0-9_\-]/', '_', $id) ?: 'btn_'.($i + 1),
                'title' => $title,
            ];
            if (count($out) >= 3) {
                break;
            }
        }

        if ($out === []) {
            throw new RuntimeException('Tombol balas: isi minimal satu label tombol.');
        }

        return $out;
    }

    private function replacePlaceholders(string $text, OmniConversation $conversation): string
    {
        $nama = $conversation->contact_name ?: $conversation->contact_first_name ?: $conversation->display_phone ?: '';
        $namaDepan = $conversation->contact_first_name ?: $nama;

        return str_replace(
            ['{{nama}}', '{{nomor}}', '{{nama_depan}}'],
            [$nama, (string) ($conversation->display_phone ?? ''), $namaDepan],
            $text
        );
    }

    private function publicStorageUrl(string $storedPath): string
    {
        $relative = Storage::disk('public')->url($storedPath);

        return str_starts_with($relative, 'http') ? $relative : url($relative);
    }
}
