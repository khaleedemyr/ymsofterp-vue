<?php

namespace App\Services\Omni;

use App\Models\OmniConversation;
use App\Services\AIBudgetService;
use App\Support\OmniChatSpellfix;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OmniAiWritingService
{
    public function __construct(
        private readonly AIBudgetService $budgetService
    ) {}

    /**
     * @param  array{contact_name?: string, channel?: string, recent_messages?: list<array{direction: string, body: string}>}|null  $context
     */
    public function transform(
        string $action,
        string $text,
        ?string $tone = null,
        ?string $listStyle = null,
        ?string $customPrompt = null,
        ?array $context = null
    ): string {
        if (! config('omnichannel.ai_writing.enabled', true)) {
            throw new RuntimeException('AI Writing Assistant tidak aktif.');
        }

        $text = trim($text);
        if ($text === '') {
            throw new RuntimeException('Tulis dulu teks di kotak balasan, lalu pilih aksi AI.');
        }

        $provider = $this->resolveProvider();
        $this->assertProviderConfigured($provider);

        if ($provider === 'claude' && $this->budgetService->isBudgetExceeded()) {
            throw new RuntimeException('Budget AI bulan ini sudah habis. Coba lagi bulan depan atau gunakan provider Gemini.');
        }

        if ($action === 'grammar') {
            $context = null;
        }

        $ruleFixed = $action === 'grammar' ? OmniChatSpellfix::apply($text) : $text;

        if ($action === 'grammar' && mb_strlen($text) <= 24 && $ruleFixed !== $text) {
            return $ruleFixed;
        }

        $prompt = $this->buildPrompt($action, $text, $tone, $listStyle, $customPrompt, $context);
        $lowTemperature = $action === 'grammar';

        $result = match ($provider) {
            'claude' => $this->invokeClaude($prompt, $lowTemperature),
            'openai' => $this->invokeOpenAi($prompt, $lowTemperature),
            default => $this->invokeGemini($prompt, $lowTemperature),
        };

        $result = $this->sanitizeOutput($result);
        if ($result === '') {
            throw new RuntimeException('AI tidak mengembalikan teks. Coba lagi.');
        }

        if ($action === 'grammar') {
            if (! OmniChatSpellfix::isAcceptableCorrection($text, $result)) {
                return $ruleFixed !== $text ? $ruleFixed : $text;
            }
            if (OmniChatSpellfix::isAcceptableCorrection($text, $ruleFixed)
                && ! OmniChatSpellfix::isAcceptableCorrection($ruleFixed, $result)) {
                return $ruleFixed;
            }
        }

        return $result;
    }

    /**
     * @return list<array{direction: string, body: string}>
     */
    public function recentMessageContext(OmniConversation $conversation, int $limit = 8): array
    {
        return $conversation->messages()
            ->whereIn('direction', ['inbound', 'outbound'])
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['direction', 'body', 'message_type'])
            ->reverse()
            ->map(fn ($m) => [
                'direction' => (string) $m->direction,
                'body' => trim((string) ($m->body ?: '['.$m->message_type.']')),
            ])
            ->filter(fn ($row) => $row['body'] !== '')
            ->values()
            ->all();
    }

    private function resolveProvider(): string
    {
        foreach ([
            config('omnichannel.ai_writing.provider'),
            config('ai.google_review_classify.provider'),
            config('ai.guest_comment_ocr.provider'),
            config('ai.provider'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return strtolower(trim($candidate));
            }
        }

        return 'gemini';
    }

    private function resolveGeminiModel(): string
    {
        foreach ([
            config('omnichannel.ai_writing.gemini_model'),
            config('ai.google_review_classify.gemini_model'),
            config('ai.guest_comment_ocr.gemini_model'),
            config('ai.gemini.model'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                $model = trim($candidate);
                if (str_starts_with($model, 'models/')) {
                    $model = substr($model, strlen('models/'));
                }

                return $model;
            }
        }

        return 'gemini-2.5-flash';
    }

    private function assertProviderConfigured(string $provider): void
    {
        if ($provider === 'claude' && ! config('ai.claude.api_key')) {
            throw new RuntimeException('ANTHROPIC_API_KEY belum diisi di .env');
        }
        if ($provider === 'openai' && ! config('ai.openai.api_key')) {
            throw new RuntimeException('OPENAI_API_KEY belum diisi di .env');
        }
        if ($provider === 'gemini' && ! config('ai.gemini.api_key')) {
            throw new RuntimeException('GOOGLE_GEMINI_API_KEY belum diisi di .env');
        }
    }

    /**
     * @param  array{contact_name?: string, channel?: string, recent_messages?: list<array{direction: string, body: string}>}|null  $context
     */
    private function buildPrompt(
        string $action,
        string $text,
        ?string $tone,
        ?string $listStyle,
        ?string $customPrompt,
        ?array $context
    ): string {
        $contextBlock = $this->formatContextBlock($context);
        $rules = "Aturan: Hanya keluarkan teks hasil akhir (tanpa judul, tanpa tanda kutip pembuka/penutup, tanpa penjelasan). "
            ."Pertahankan makna. Untuk chat WhatsApp pelanggan restoran/hospitality, sopan dan jelas.";

        $task = match ($action) {
            'grammar' => 'Koreksi HANYA typo/ejaan pada teks masukan. DILARANG menulis balasan baru, mengganti makna, atau menambah kata (mis. jangan ubah "ape?" menjadi "Silakan?"). '
                .'Slang chat Indonesia yang umum boleh dirapikan ringan (ape→apa, gmn→gimana). '
                .'Jika teks sudah wajar, kembalikan persis seperti masukan. Panjang hasil hampir sama. '
                .'Contoh benar: "ape?" → "Apa?"; "yu" → "Yu" atau tetap "yu". Contoh salah: "ape?" → "Silakan?".',
            'tone' => 'Ubah nada/tone teks menjadi: '.$this->toneLabel($tone).'.',
            'translate_to_en' => 'Terjemahkan ke Bahasa Inggris yang natural untuk customer service.',
            'translate_to_id' => 'Terjemahkan ke Bahasa Indonesia yang natural untuk layanan pelanggan.',
            'simplify' => 'Sederhanakan kalimat; lebih mudah dipahami; tetap sopan.',
            'expand' => 'Perluas sedikit; tambah detail sopan yang relevan; jangan bertele-tele.',
            'shorten' => 'Persingkat; inti pesan tetap jelas.',
            'to_list' => $listStyle === 'numbered'
                ? 'Ubah menjadi daftar bernomor (1. 2. 3.).'
                : 'Ubah menjadi bullet list (gunakan "- " di awal baris).',
            'custom' => 'Ikuti instruksi pengguna: '.trim((string) $customPrompt),
            default => throw new RuntimeException('Aksi AI tidak dikenali.'),
        };

        if ($action === 'custom' && trim((string) $customPrompt) === '') {
            throw new RuntimeException('Instruksi custom wajib diisi.');
        }

        return implode("\n\n", array_filter([
            'Anda asisten menulis balasan chat omnichannel untuk staf CS.',
            $contextBlock,
            'Tugas: '.$task,
            $rules,
            'Teks masukan:',
            $text,
        ]));
    }

    /**
     * @param  array{contact_name?: string, channel?: string, recent_messages?: list<array{direction: string, body: string}>}|null  $context
     */
    private function formatContextBlock(?array $context): ?string
    {
        if ($context === null || $context === []) {
            return null;
        }

        $lines = [];
        if (! empty($context['contact_name'])) {
            $lines[] = 'Nama pelanggan: '.$context['contact_name'];
        }
        if (! empty($context['channel'])) {
            $lines[] = 'Channel: '.$context['channel'];
        }
        foreach ($context['recent_messages'] ?? [] as $msg) {
            $who = $msg['direction'] === 'inbound' ? 'Pelanggan' : 'Agen';
            $lines[] = $who.': '.$msg['body'];
        }

        if ($lines === []) {
            return null;
        }

        return "Konteks percakapan (untuk referensi, jangan salin mentah):\n".implode("\n", $lines);
    }

    private function toneLabel(?string $tone): string
    {
        return match ($tone) {
            'friendly' => 'ramah dan hangat',
            'formal' => 'formal dan profesional',
            'empathetic' => 'empati dan pengertian',
            'casual' => 'santai namun sopan',
            default => 'profesional dan sopan',
        };
    }

    private function sanitizeOutput(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^```[\w]*\s*/', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;

        if (
            (str_starts_with($text, '"') && str_ends_with($text, '"'))
            || (str_starts_with($text, "'") && str_ends_with($text, "'"))
        ) {
            $text = substr($text, 1, -1);
        }

        return trim($text);
    }

    private function invokeGemini(string $prompt, bool $lowTemperature = false): string
    {
        $apiKey = (string) config('ai.gemini.api_key');
        $model = $this->resolveGeminiModel();
        $temperature = $lowTemperature
            ? 0.1
            : (float) config('omnichannel.ai_writing.temperature', 0.4);

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent?key='.urlencode($apiKey);
        $body = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => (int) config('omnichannel.ai_writing.max_tokens', 2048),
            ],
        ];

        $http = Http::timeout((int) config('omnichannel.ai_writing.timeout', 60))
            ->withHeaders(['Content-Type' => 'application/json']);
        if (config('app.env') === 'local' || config('app.debug')) {
            $http = $http->withoutVerifying();
        }

        $response = $http->post($url, $body);
        if (! $response->successful()) {
            Log::error('Omni AI Writing Gemini failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException('Gemini API error: '.$response->status());
        }

        $parts = $response->json('candidates.0.content.parts') ?? [];
        $text = $this->concatGeminiParts(is_array($parts) ? $parts : []);
        if ($text === '') {
            throw new RuntimeException('Jawaban Gemini kosong.');
        }

        return $text;
    }

    /**
     * @param  array<int, mixed>  $parts
     */
    private function concatGeminiParts(array $parts): string
    {
        $out = [];
        foreach ($parts as $part) {
            if (is_array($part) && isset($part['text']) && is_string($part['text']) && $part['text'] !== '') {
                $out[] = $part['text'];
            }
        }

        return trim(implode("\n", $out));
    }

    private function invokeClaude(string $prompt, bool $lowTemperature = false): string
    {
        $apiKey = (string) config('ai.claude.api_key');
        $model = (string) config('omnichannel.ai_writing.claude_model', config('ai.claude.model', 'claude-haiku-4-5-20251001'));
        $temperature = $lowTemperature
            ? 0.1
            : (float) config('omnichannel.ai_writing.temperature', 0.4);

        $http = Http::timeout((int) config('omnichannel.ai_writing.timeout', 60))
            ->withHeaders([
                'Content-Type' => 'application/json',
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ]);
        if (config('app.env') === 'local' || config('app.debug')) {
            $http = $http->withoutVerifying();
        }

        $response = $http->post('https://api.anthropic.com/v1/messages', [
            'model' => $model,
            'max_tokens' => (int) config('omnichannel.ai_writing.max_tokens', 2048),
            'temperature' => $temperature,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        if (! $response->successful()) {
            Log::error('Omni AI Writing Claude failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException('Claude API error: '.$response->status());
        }

        $data = $response->json();
        $text = trim((string) ($data['content'][0]['text'] ?? ''));
        if ($text === '') {
            throw new RuntimeException('Jawaban Claude kosong.');
        }

        $inputTokens = (int) ($data['usage']['input_tokens'] ?? (int) (strlen($prompt) / 4));
        $outputTokens = (int) ($data['usage']['output_tokens'] ?? (int) (strlen($text) / 4));
        $cost = $this->budgetService->calculateCost('claude', $inputTokens, $outputTokens);
        $this->budgetService->logUsage(
            'claude',
            'omnichannel_ai_writing',
            $inputTokens,
            $outputTokens,
            $cost['total_cost_usd'],
            $cost['total_cost_rupiah']
        );

        return $text;
    }

    private function invokeOpenAi(string $prompt, bool $lowTemperature = false): string
    {
        $apiKey = (string) config('ai.openai.api_key');
        $model = (string) config('omnichannel.ai_writing.openai_model', config('ai.openai.model', 'gpt-4o-mini'));
        $temperature = $lowTemperature
            ? 0.1
            : (float) config('omnichannel.ai_writing.temperature', 0.4);

        $http = Http::timeout((int) config('omnichannel.ai_writing.timeout', 60))
            ->withToken($apiKey)
            ->acceptJson();
        if (config('app.env') === 'local' || config('app.debug')) {
            $http = $http->withoutVerifying();
        }

        $response = $http->post('https://api.openai.com/v1/chat/completions', [
            'model' => $model,
            'temperature' => $temperature,
            'max_tokens' => (int) config('omnichannel.ai_writing.max_tokens', 2048),
            'messages' => [
                ['role' => 'system', 'content' => 'Anda asisten menulis balasan chat customer service. Hanya keluarkan teks hasil akhir.'],
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if (! $response->successful()) {
            Log::error('Omni AI Writing OpenAI failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException('OpenAI API error: '.$response->status());
        }

        $text = trim((string) ($response->json('choices.0.message.content') ?? ''));
        if ($text === '') {
            throw new RuntimeException('Jawaban OpenAI kosong.');
        }

        return $text;
    }
}
