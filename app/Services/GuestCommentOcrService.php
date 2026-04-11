<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ekstraksi field dari foto formulir guest comment.
 * Provider: config('ai.guest_comment_ocr.provider') jika diisi, else config('ai.provider') (gemini|openai|claude).
 * Model Gemini OCR bisa di-override dengan GUEST_COMMENT_GEMINI_MODEL (mis. flash murah) tanpa mengubah dashboard.
 */
class GuestCommentOcrService
{
    private const RATINGS = ['poor', 'average', 'good', 'excellent'];

    private const JSON_SCHEMA_HINT = <<<'TXT'
Balas HANYA dengan objek JSON valid (tanpa markdown), kunci persis:
{
  "rating_service": null atau "poor"|"average"|"good"|"excellent",
  "rating_food": sama,
  "rating_beverage": sama,
  "rating_cleanliness": sama,
  "rating_staff": sama (perhatian karyawan / attentiveness of staff),
  "rating_value": sama (harga sepadan / value for money),
  "comment_text": string atau null,
  "guest_name": string atau null,
  "guest_address": string atau null,
  "guest_phone": string atau null,
  "guest_dob": "YYYY-MM-DD" atau null jika tidak terbaca / tidak ada di form,
  "visit_date": string bebas seperti tertulis (tanggal kunjungan / date of visit / Tanggal berkunjung),
  "praised_staff_name": string atau null,
  "praised_staff_outlet": string atau null,
  "marketing_source": string atau null
}

Khusus marketing_source (form Tempayan / sejenis):
- Jika dicentang salah satu opsi tetap (Sosial Media, Media Cetak, Media Elektronik, Brosur): isi persis label opsi itu.
- Jika dicentang Lainnya dan ada tulisan tangan di kotak/garis samping "Lainnya": gabungkan jadi SATU string di marketing_source, misalnya "Lainnya: rekomendasi teman" atau "Lainnya — Google Maps". Jangan buang isi tulisan tangan.
- Jika hanya Lainnya tercentang tanpa teks terbaca, isi "Lainnya" saja.

DUA VARIAN FORM DI LAPANGAN (isi field yang ADA di foto; sisanya null):

A) Form Inggris (mis. Justus Steak House): judul baris Poor / Average / Good / Excellent. Bawah: Address, Whatsapp/Phone, Date of Birth, Date of visit, lalu blok "staff helpful" → praised_staff_name + praised_staff_outlet (Outlet). marketing_source null.

B) Form Indonesia (mis. Tempayan): judul baris Buruk / Cukup / Baik / Sangat Baik — map ke poor / average / good / excellent. Bawah: Whatsapp/Telp, Tanggal berkunjung; ada pertanyaan "Dari mana anda mengetahui ...?" dengan centang (Sosial Media, Media Cetak, …) → isi marketing_source (lihat aturan Lainnya di atas). praised_staff_name & praised_staff_outlet biasanya null. guest_address & guest_dob sering tidak ada → null.

Mapping baris rating (nama bisa Inggris atau Indonesia, urutan grid 6 baris):
- Baris 1 → rating_service (Quality of service / Kualitas Pelayanan)
- Baris 2 → rating_food (Food / Kualitas Makanan)
- Baris 3 → rating_beverage (Beverage / Kualitas Minuman)
- Baris 4 → rating_cleanliness (Cleanliness / Kebersihan)
- Baris 5 → rating_staff (Staff attentiveness / Perhatian Karyawan)
- Baris 6 → rating_value (Value for money / Harga sepadan dengan kualitas)

Tulisan tangan di kotak komentar → comment_text. Nomor telepon apa adanya → guest_phone.
TXT;

    public function __construct(
        private AIBudgetService $budgetService
    ) {}

    /**
     * @return array{raw_text: string, fields: array<string, mixed>}
     */
    public function extract(string $absolutePath): array
    {
        $empty = $this->emptyResult();

        if (! config('ai.guest_comment_ocr.enabled', true)) {
            return $empty;
        }

        if (! is_readable($absolutePath)) {
            Log::warning('GuestCommentOcrService: file tidak terbaca', ['path' => $absolutePath]);

            return $empty;
        }

        $provider = $this->guestCommentOcrProvider();

        try {
            return match ($provider) {
                'claude' => $this->extractWithClaude($absolutePath),
                'openai' => $this->extractWithOpenAi($absolutePath),
                default => $this->extractWithGemini($absolutePath),
            };
        } catch (\Throwable $e) {
            Log::error('GuestCommentOcrService gagal', [
                'provider' => $provider,
                'message' => $e->getMessage(),
                'path' => $absolutePath,
            ]);

            return $empty;
        }
    }

    /**
     * Provider khusus guest comment OCR, atau fallback ke AI global.
     */
    private function guestCommentOcrProvider(): string
    {
        $override = config('ai.guest_comment_ocr.provider');
        if (is_string($override) && trim($override) !== '') {
            return strtolower(trim($override));
        }

        return strtolower((string) config('ai.provider', 'gemini'));
    }

    private function httpClient(int $timeout)
    {
        $client = Http::timeout($timeout)->withHeaders(['Content-Type' => 'application/json']);
        if (config('app.env') === 'local' || config('app.debug')) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }

    /**
     * Kecilkan gambar hanya untuk payload API (biaya vision ~proporsional piksel). Berkas di storage tidak diubah.
     * Butuh php-gd atau php-imagick; tanpa keduanya gambar penuh dikirim (mahal).
     *
     * @return array{bytes: string, mime: string}
     */
    private function prepareImageForVisionOcr(string $absolutePath): array
    {
        $raw = file_get_contents($absolutePath);
        if ($raw === false) {
            return ['bytes' => '', 'mime' => 'image/jpeg'];
        }

        $originalMime = mime_content_type($absolutePath) ?: 'image/jpeg';
        $maxEdge = max(512, min(4096, (int) config('ai.guest_comment_ocr.max_image_edge_px', 1600)));
        $quality = max(60, min(95, (int) config('ai.guest_comment_ocr.jpeg_quality', 80)));

        $dims = @getimagesize($absolutePath);
        $w = isset($dims[0]) ? (int) $dims[0] : 0;
        $h = isset($dims[1]) ? (int) $dims[1] : 0;

        if ($w <= 0 || $h <= 0) {
            $this->logVisionPayloadDebug($absolutePath, 0, 0, $maxEdge, $raw, $raw, 'passthrough_no_dimensions');

            return ['bytes' => $raw, 'mime' => $originalMime];
        }

        if ($w <= $maxEdge && $h <= $maxEdge) {
            $this->logVisionPayloadDebug($absolutePath, $w, $h, $maxEdge, $raw, $raw, 'passthrough_under_max');

            return ['bytes' => $raw, 'mime' => $originalMime];
        }

        $jpeg = $this->downscaleImageWithGd($raw, $w, $h, $maxEdge, $quality);
        if ($jpeg !== null) {
            $this->logVisionPayloadDebug($absolutePath, $w, $h, $maxEdge, $raw, $jpeg, 'downscaled_gd');

            return ['bytes' => $jpeg, 'mime' => 'image/jpeg'];
        }

        $jpeg = $this->downscaleImageWithImagick($raw, $maxEdge, $quality);
        if ($jpeg !== null) {
            $this->logVisionPayloadDebug($absolutePath, $w, $h, $maxEdge, $raw, $jpeg, 'downscaled_imagick');

            return ['bytes' => $jpeg, 'mime' => 'image/jpeg'];
        }

        Log::warning('GuestCommentOcrService: resize OCR tidak jalan — gambar penuh dikirim ke API (biaya vision tinggi). Aktifkan ekstensi PHP gd atau imagick.', [
            'file' => basename($absolutePath),
            'w' => $w,
            'h' => $h,
            'max_edge_config' => $maxEdge,
            'gd' => extension_loaded('gd'),
            'imagick' => extension_loaded('imagick'),
        ]);
        $this->logVisionPayloadDebug($absolutePath, $w, $h, $maxEdge, $raw, $raw, 'full_image_no_resize_helper');

        return ['bytes' => $raw, 'mime' => $originalMime];
    }

    private function logVisionPayloadDebug(
        string $absolutePath,
        int $w,
        int $h,
        int $maxEdge,
        string $bytesIn,
        string $bytesOut,
        string $mode,
    ): void {
        if (! config('ai.guest_comment_ocr.debug_log')) {
            return;
        }

        Log::info('GuestCommentOcrService vision payload (debug)', [
            'file' => basename($absolutePath),
            'original_px' => $w > 0 && $h > 0 ? "{$w}x{$h}" : 'unknown',
            'max_edge' => $maxEdge,
            'mode' => $mode,
            'bytes_in' => strlen($bytesIn),
            'bytes_out' => strlen($bytesOut),
            'php_sapi' => PHP_SAPI,
            'gd_loaded' => extension_loaded('gd'),
        ]);
    }

    /**
     * @return string|null JPEG binary atau null jika gagal / tidak tersedia
     */
    private function downscaleImageWithGd(string $raw, int $w, int $h, int $maxEdge, int $quality): ?string
    {
        if (! extension_loaded('gd') || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $im = @imagecreatefromstring($raw);
        if ($im === false) {
            return null;
        }

        if ($w >= $h) {
            $nw = $maxEdge;
            $nh = (int) max(1, round($h * $maxEdge / $w));
        } else {
            $nh = $maxEdge;
            $nw = (int) max(1, round($w * $maxEdge / $h));
        }

        $dst = imagecreatetruecolor($nw, $nh);
        if ($dst === false) {
            imagedestroy($im);

            return null;
        }

        imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($im);

        ob_start();
        imagejpeg($dst, null, $quality);
        $jpeg = ob_get_clean();
        imagedestroy($dst);

        if ($jpeg === false || $jpeg === '') {
            return null;
        }

        return $jpeg;
    }

    /**
     * @return string|null JPEG binary atau null jika gagal / tidak tersedia
     */
    private function downscaleImageWithImagick(string $raw, int $maxEdge, int $quality): ?string
    {
        if (! extension_loaded('imagick')) {
            return null;
        }

        try {
            $im = new \Imagick;
            $im->readImageBlob($raw);
            $im->setImageColorspace(\Imagick::COLORSPACE_SRGB);
            $im->stripImage();
            $im->setImageFormat('jpeg');
            $im->setImageCompressionQuality($quality);
            $im->thumbnailImage($maxEdge, $maxEdge, true);
            $blob = $im->getImageBlob();
            $im->clear();
            $im->destroy();

            if ($blob === false || $blob === '') {
                return null;
            }

            return $blob;
        } catch (\Throwable $e) {
            Log::warning('GuestCommentOcrService: Imagick downscale gagal', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array{raw_text: string, fields: array<string, mixed>}
     */
    private function extractWithGemini(string $absolutePath): array
    {
        $key = config('ai.gemini.api_key');
        if (! $key) {
            Log::warning('GuestCommentOcrService: Gemini API key kosong (sama dengan dashboard AI).');

            return $this->emptyResult();
        }

        $ocrModel = config('ai.guest_comment_ocr.gemini_model');
        $model = (is_string($ocrModel) && trim($ocrModel) !== '')
            ? trim($ocrModel)
            : (string) config('ai.gemini.model', 'gemini-1.5-pro');
        $timeout = (int) config('ai.guest_comment_ocr.timeout', 120);

        $prepared = $this->prepareImageForVisionOcr($absolutePath);
        $bytes = $prepared['bytes'];
        $mime = $prepared['mime'];
        if ($bytes === '') {
            return $this->emptyResult();
        }
        $b64 = base64_encode($bytes);

        $userPrompt = "Ini foto formulir komentar tamu restoran. Bentuk kertas bisa beda (Inggris atau Indonesia); ikuti petunjuk varian A/B di skema. Baca tulisan tangan dan centang rating.\n\n".self::JSON_SCHEMA_HINT;

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent?key='.urlencode($key);

        $response = $this->httpClient($timeout)->post($url, [
            'contents' => [[
                'parts' => [
                    ['text' => $userPrompt],
                    ['inline_data' => ['mime_type' => $mime, 'data' => $b64]],
                ],
            ]],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => min(8192, (int) config('ai.gemini.max_tokens', 4096)),
                'responseMimeType' => 'application/json',
            ],
        ]);

        if (! $response->successful()) {
            Log::error('Gemini GuestComment OCR HTTP error', [
                'model' => $model,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Gemini OCR error: '.$response->status());
        }

        $candidates = $response->json('candidates');
        if (empty($candidates)) {
            Log::warning('Gemini GuestComment OCR: tidak ada candidates', ['body' => $response->json()]);

            return $this->emptyResult();
        }

        $text = $response->json('candidates.0.content.parts.0.text') ?? '';
        $parsed = $this->decodeJsonObject($text);

        return $this->buildResult($text, $parsed);
    }

    /**
     * @return array{raw_text: string, fields: array<string, mixed>}
     */
    private function extractWithOpenAi(string $absolutePath): array
    {
        $key = config('ai.openai.api_key');
        if (! $key) {
            Log::warning('GuestCommentOcrService: OpenAI API key kosong (sama dengan dashboard AI).');

            return $this->emptyResult();
        }

        $model = config('ai.openai.model', 'gpt-4o');
        $timeout = (int) config('ai.guest_comment_ocr.timeout', 120);

        $prepared = $this->prepareImageForVisionOcr($absolutePath);
        $bytes = $prepared['bytes'];
        $mime = $prepared['mime'];
        if ($bytes === '') {
            return $this->emptyResult();
        }
        $b64 = base64_encode($bytes);
        $dataUrl = 'data:'.$mime.';base64,'.$b64;

        $userPrompt = "Ini foto formulir komentar tamu restoran. Bentuk kertas bisa beda (Inggris atau Indonesia); ikuti petunjuk varian A/B di skema. Baca tulisan tangan dan centang rating.\n\n".self::JSON_SCHEMA_HINT;

        $response = $this->httpClient($timeout)
            ->withToken($key)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0.2,
                'max_tokens' => min(4096, (int) config('ai.openai.max_tokens', 4096)),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Anda membantu digitalisasi formulir guest comment. Jawaban hanya JSON valid sesuai skema user.',
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $userPrompt],
                            ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            Log::error('OpenAI GuestComment OCR HTTP error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('OpenAI OCR error: '.$response->status());
        }

        $text = $response->json('choices.0.message.content') ?? '';
        $parsed = $this->decodeJsonObject($text);

        return $this->buildResult($text, $parsed);
    }

    /**
     * @return array{raw_text: string, fields: array<string, mixed>}
     */
    private function extractWithClaude(string $absolutePath): array
    {
        if ($this->budgetService->isBudgetExceeded()) {
            Log::warning('GuestCommentOcrService: budget Claude habis (sama dengan batas dashboard AI).');

            return $this->emptyResult();
        }

        $key = config('ai.claude.api_key');
        if (! $key) {
            Log::warning('GuestCommentOcrService: Anthropic API key kosong (sama dengan dashboard AI).');

            return $this->emptyResult();
        }

        $configured = (string) config('ai.claude.model', 'claude-haiku-4-5-20251001');
        $fallbacks = [
            'claude-haiku-4-5-20251001',
            'claude-3-5-haiku-20241022',
            'claude-3-haiku-20240307',
        ];
        $models = array_values(array_unique(array_filter(array_merge([$configured], $fallbacks))));

        $lastException = null;
        foreach ($models as $model) {
            try {
                return $this->extractWithClaudeModel($absolutePath, $model, $key);
            } catch (\Throwable $e) {
                $lastException = $e;
                Log::warning('GuestCommentOcrService: Claude OCR model gagal, coba berikutnya', [
                    'model' => $model,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($lastException) {
            Log::error('GuestCommentOcrService: semua model Claude OCR gagal', [
                'message' => $lastException->getMessage(),
            ]);
        }

        return $this->emptyResult();
    }

    /**
     * Satu percobaan Messages API (vision) dengan model tertentu.
     *
     * @return array{raw_text: string, fields: array<string, mixed>}
     */
    private function extractWithClaudeModel(string $absolutePath, string $model, string $key): array
    {
        $timeout = (int) config('ai.guest_comment_ocr.timeout', 120);

        $prepared = $this->prepareImageForVisionOcr($absolutePath);
        $bytes = $prepared['bytes'];
        $mime = $prepared['mime'];
        if ($bytes === '') {
            throw new \RuntimeException('Berkas gambar tidak terbaca untuk Claude OCR.');
        }
        if (! preg_match('#^image/(jpeg|png|gif|webp)$#', $mime)) {
            $mime = 'image/jpeg';
        }
        $b64 = base64_encode($bytes);

        $userPrompt = "Ini foto formulir komentar tamu restoran. Bentuk kertas bisa beda (Inggris atau Indonesia); ikuti petunjuk varian A/B di skema. Baca tulisan tangan dan centang rating.\n\n".self::JSON_SCHEMA_HINT;

        $url = 'https://api.anthropic.com/v1/messages';
        $requestBody = [
            'model' => $model,
            'max_tokens' => min(4096, (int) config('ai.claude.max_tokens', 4096)),
            'temperature' => 0.2,
            'system' => 'Anda hanya mengembalikan JSON valid sesuai permintaan user. Tanpa markdown.',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $userPrompt,
                        ],
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $mime,
                                'data' => $b64,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->httpClient($timeout)
            ->withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
            ])
            ->post($url, $requestBody);

        if (! $response->successful()) {
            Log::error('Claude GuestComment OCR HTTP error', [
                'model' => $model,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Claude OCR error: '.$response->status());
        }

        $data = $response->json();
        $text = $this->claudeAssistantTextFromResponse(is_array($data) ? $data : []);
        if ($text === '') {
            throw new \RuntimeException('Jawaban Claude kosong.');
        }

        $inputTokens = (int) ($data['usage']['input_tokens'] ?? 0);
        $outputTokens = (int) ($data['usage']['output_tokens'] ?? 0);
        if ($inputTokens > 0 || $outputTokens > 0) {
            $cost = $this->budgetService->calculateCost('claude', $inputTokens, $outputTokens);
            $this->budgetService->logUsage('claude', 'guest_comment_ocr', $inputTokens, $outputTokens, $cost['total_cost_usd'], $cost['total_cost_rupiah']);
        } else {
            Log::warning('GuestCommentOcrService: respons Claude tanpa usage tokens, biaya tidak dicatat', ['model' => $model]);
        }

        if (config('ai.guest_comment_ocr.debug_log')) {
            Log::info('GuestCommentOcrService Claude usage (debug)', [
                'model' => $model,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'image_bytes_to_api' => strlen($bytes),
            ]);
        }

        $parsed = $this->decodeJsonObject($text);

        return $this->buildResult($text, $parsed);
    }

    /**
     * Gabungkan semua blok type "text" (bukan hanya content[0]) untuk kompatibilitas respons multi-bagian.
     */
    private function claudeAssistantTextFromResponse(array $data): string
    {
        $content = $data['content'] ?? null;
        if (! is_array($content)) {
            return '';
        }
        $parts = [];
        foreach ($content as $block) {
            if (! is_array($block)) {
                continue;
            }
            if (($block['type'] ?? '') === 'text' && isset($block['text']) && is_string($block['text'])) {
                $parts[] = $block['text'];
            }
        }

        return trim(implode("\n", $parts));
    }

    private function decodeJsonObject(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/m', $text, $m)) {
            $text = trim($m[1]);
        }
        try {
            $data = json_decode($text, true, 512, JSON_THROW_ON_ERROR);

            return is_array($data) ? $data : null;
        } catch (\JsonException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>|null  $parsed
     * @return array{raw_text: string, fields: array<string, mixed>}
     */
    private function buildResult(string $rawText, ?array $parsed): array
    {
        $fields = $this->emptyFields();
        if ($parsed === null) {
            return ['raw_text' => $rawText, 'fields' => $fields];
        }

        foreach (array_keys($fields) as $key) {
            if (! array_key_exists($key, $parsed)) {
                continue;
            }
            $v = $parsed[$key];
            if ($v === null || $v === '') {
                continue;
            }
            if (str_starts_with($key, 'rating_')) {
                $norm = $this->normalizeRating($v);
                if ($norm !== null) {
                    $fields[$key] = $norm;
                }
            } elseif ($key === 'guest_dob') {
                $fields[$key] = $this->normalizeDateString($v);
            } else {
                $fields[$key] = is_string($v) ? trim($v) : $v;
            }
        }

        return [
            'raw_text' => $rawText !== '' ? $rawText : json_encode($parsed, JSON_UNESCAPED_UNICODE),
            'fields' => $fields,
        ];
    }

    private function normalizeRating(mixed $v): ?string
    {
        if (! is_string($v)) {
            return null;
        }
        $s = strtolower(trim($v));
        $map = [
            'poor' => 'poor', 'buruk' => 'poor',
            'average' => 'average', 'cukup' => 'average', 'fair' => 'average',
            'good' => 'good', 'baik' => 'good',
            'excellent' => 'excellent', 'sangat baik' => 'excellent', 'very good' => 'excellent',
        ];
        if (isset($map[$s])) {
            return $map[$s];
        }
        if (in_array($s, self::RATINGS, true)) {
            return $s;
        }

        return null;
    }

    private function normalizeDateString(mixed $v): ?string
    {
        if (! is_string($v) || trim($v) === '') {
            return null;
        }
        $t = strtotime($v);

        return $t ? date('Y-m-d', $t) : null;
    }

    /**
     * @return array<string, null>
     */
    private function emptyFields(): array
    {
        return [
            'rating_service' => null,
            'rating_food' => null,
            'rating_beverage' => null,
            'rating_cleanliness' => null,
            'rating_staff' => null,
            'rating_value' => null,
            'comment_text' => null,
            'guest_name' => null,
            'guest_address' => null,
            'guest_phone' => null,
            'guest_dob' => null,
            'visit_date' => null,
            'praised_staff_name' => null,
            'praised_staff_outlet' => null,
            'marketing_source' => null,
        ];
    }

    /**
     * @return array{raw_text: string, fields: array<string, mixed>}
     */
    private function emptyResult(): array
    {
        return ['raw_text' => '', 'fields' => $this->emptyFields()];
    }
}
