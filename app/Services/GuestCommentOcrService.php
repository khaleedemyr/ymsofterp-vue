<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ekstraksi field dari foto formulir guest comment.
 * Memakai provider AI yang sama dengan Sales Outlet Dashboard / Google Review AI:
 * config('ai.provider') = gemini | openai | claude, plus model & API key dari config/ai.php.
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

DUA VARIAN FORM DI LAPANGAN (isi field yang ADA di foto; sisanya null):

A) Form Inggris (mis. Justus Steak House): judul baris Poor / Average / Good / Excellent. Bawah: Address, Whatsapp/Phone, Date of Birth, Date of visit, lalu blok "staff helpful" → praised_staff_name + praised_staff_outlet (Outlet). marketing_source null.

B) Form Indonesia (mis. Tempayan): judul baris Buruk / Cukup / Baik / Sangat Baik — map ke poor / average / good / excellent. Bawah: Whatsapp/Telp, Tanggal berkunjung; ada pertanyaan "Dari mana anda mengetahui ...?" dengan centang (Sosial Media, Media Cetak, …) → isi marketing_source dengan TEKS OPSI YANG DICENTANG saja. praised_staff_name & praised_staff_outlet biasanya null. guest_address & guest_dob sering tidak ada → null.

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

        $provider = strtolower((string) config('ai.provider', 'gemini'));

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

    private function httpClient(int $timeout)
    {
        $client = Http::timeout($timeout)->withHeaders(['Content-Type' => 'application/json']);
        if (config('app.env') === 'local' || config('app.debug')) {
            $client = $client->withoutVerifying();
        }

        return $client;
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

        $model = config('ai.gemini.model', 'gemini-1.5-pro');
        $timeout = (int) config('ai.guest_comment_ocr.timeout', 120);

        $mime = mime_content_type($absolutePath) ?: 'image/jpeg';
        $bytes = file_get_contents($absolutePath);
        if ($bytes === false) {
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

        $mime = mime_content_type($absolutePath) ?: 'image/jpeg';
        $bytes = file_get_contents($absolutePath);
        if ($bytes === false) {
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

        $model = config('ai.claude.model', 'claude-haiku-4-5-20251001');
        $timeout = (int) config('ai.guest_comment_ocr.timeout', 120);

        $mime = mime_content_type($absolutePath) ?: 'image/jpeg';
        if (! preg_match('#^image/(jpeg|png|gif|webp)$#', $mime)) {
            $mime = 'image/jpeg';
        }
        $bytes = file_get_contents($absolutePath);
        if ($bytes === false) {
            return $this->emptyResult();
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
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $mime,
                                'data' => $b64,
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => $userPrompt,
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
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Claude OCR error: '.$response->status());
        }

        $data = $response->json();
        $text = $data['content'][0]['text'] ?? '';
        if ($text === '') {
            throw new \RuntimeException('Jawaban Claude kosong.');
        }

        $inputTokens = $data['usage']['input_tokens'] ?? (int) (strlen($b64) / 4);
        $outputTokens = $data['usage']['output_tokens'] ?? (int) (strlen($text) / 4);
        $cost = $this->budgetService->calculateCost('claude', $inputTokens, $outputTokens);
        $this->budgetService->logUsage('claude', 'guest_comment_ocr', $inputTokens, $outputTokens, $cost['total_cost_usd'], $cost['total_cost_rupiah']);

        $parsed = $this->decodeJsonObject($text);

        return $this->buildResult($text, $parsed);
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
