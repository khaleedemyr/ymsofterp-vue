<?php

namespace App\Services\JustAcademy;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class NegativeCommentRadarService
{
    public const NICHES = [
        'staff_friendliness' => 'Staff Friendliness',
        'speed_serving_time' => 'Speed / Serving Time',
        'wrong_transaction' => 'Wrong Transaction',
        'food_quality' => 'Food Quality',
        'cleanliness' => 'Cleanliness',
        'grooming' => 'Grooming',
    ];

    private const NEGATIVE_SEVERITIES = [
        'minor', 'major', 'critical', 'mild_negative', 'negative', 'severe',
    ];

    private const MAX_CASES_PER_RUN = 300;

    private const AI_CHUNK_SIZE = 25;

    /**
     * @return list<array{code: string, label: string}>
     */
    public function nicheCatalog(): array
    {
        $out = [];
        foreach (self::NICHES as $code => $label) {
            $out[] = ['code' => $code, 'label' => $label];
        }

        return $out;
    }

    public function tablesReady(): bool
    {
        return Schema::hasTable('ncr_runs') && Schema::hasTable('ncr_classifications');
    }

    /**
     * @return array{date_from: string, date_to: string, id_outlet: int|null}
     */
    public function normalizeFilters(array $input): array
    {
        $dateTo = ! empty($input['date_to'])
            ? (string) $input['date_to']
            : now()->toDateString();
        $dateFrom = ! empty($input['date_from'])
            ? (string) $input['date_from']
            : now()->subDays(30)->toDateString();

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $outlet = isset($input['id_outlet']) && $input['id_outlet'] !== '' && $input['id_outlet'] !== null
            ? (int) $input['id_outlet']
            : null;

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'id_outlet' => $outlet && $outlet > 0 ? $outlet : null,
        ];
    }

    public function latestRun(?int $runId = null): ?object
    {
        if (! $this->tablesReady()) {
            return null;
        }

        if ($runId) {
            return DB::table('ncr_runs')->where('id', $runId)->first();
        }

        return DB::table('ncr_runs')->where('status', 'done')->orderByDesc('id')->first()
            ?? DB::table('ncr_runs')->orderByDesc('id')->first();
    }

    /**
     * @return Collection<int, object>
     */
    public function recentRuns(int $limit = 12): Collection
    {
        if (! $this->tablesReady()) {
            return collect();
        }

        return DB::table('ncr_runs')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array{date_from: string, date_to: string, id_outlet: int|null}  $filters
     * @return Collection<int, object>
     */
    public function fetchNegativeCases(array $filters): Collection
    {
        $query = DB::table('feedback_cases as c')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'c.id_outlet')
            ->whereIn('c.severity', self::NEGATIVE_SEVERITIES)
            ->whereDate('c.event_at', '>=', $filters['date_from'])
            ->whereDate('c.event_at', '<=', $filters['date_to'])
            ->select([
                'c.id',
                'c.id_outlet',
                'o.nama_outlet',
                'c.author_name',
                'c.severity',
                'c.summary_id',
                'c.raw_text',
                'c.topics',
                'c.event_at',
                'c.source_type',
            ])
            ->orderByDesc('c.event_at')
            ->limit(self::MAX_CASES_PER_RUN);

        if (! empty($filters['id_outlet'])) {
            $query->where('c.id_outlet', (int) $filters['id_outlet']);
        }

        return $query->get();
    }

    /**
     * @param  array{date_from: string, date_to: string, id_outlet: int|null}  $filters
     */
    public function runAnalysis(array $filters, int $userId): object
    {
        if (! $this->tablesReady()) {
            throw new \RuntimeException('Tabel ncr_runs / ncr_classifications belum dibuat. Jalankan SQL create_negative_comment_radar_tables.sql dulu.');
        }

        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '300');

        $cases = $this->fetchNegativeCases($filters);
        $provider = $this->resolveProvider();

        $runId = DB::table('ncr_runs')->insertGetId([
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
            'id_outlet' => $filters['id_outlet'],
            'status' => 'processing',
            'total_cases' => $cases->count(),
            'classified_count' => 0,
            'niche_summary' => null,
            'training_recommendations' => null,
            'ai_provider' => $provider,
            'error_message' => null,
            'created_by' => $userId > 0 ? $userId : null,
            'started_at' => now(),
            'finished_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $classified = $this->classifyCases($cases, $provider);
            $rows = [];
            $now = now();

            foreach ($classified as $item) {
                $rows[] = [
                    'run_id' => $runId,
                    'feedback_case_id' => (int) $item['feedback_case_id'],
                    'primary_niche' => $item['primary_niche'],
                    'niches' => json_encode($item['niches'], JSON_UNESCAPED_UNICODE),
                    'confidence' => $item['confidence'],
                    'severity' => $item['severity'],
                    'id_outlet' => $item['id_outlet'],
                    'outlet_name' => $item['outlet_name'],
                    'event_at' => $item['event_at'],
                    'author_name' => $item['author_name'],
                    'summary_text' => $item['summary_text'],
                    'raw_excerpt' => $item['raw_excerpt'],
                    'ai_reason' => $item['ai_reason'],
                    'training_hint' => $item['training_hint'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows !== []) {
                foreach (array_chunk($rows, 100) as $chunk) {
                    DB::table('ncr_classifications')->insert($chunk);
                }
            }

            $nicheSummary = $this->buildNicheSummary(collect($classified));
            $recommendations = $this->buildTrainingRecommendations(collect($classified), $nicheSummary, $provider);

            DB::table('ncr_runs')->where('id', $runId)->update([
                'status' => 'done',
                'classified_count' => count($classified),
                'niche_summary' => json_encode($nicheSummary, JSON_UNESCAPED_UNICODE),
                'training_recommendations' => json_encode($recommendations, JSON_UNESCAPED_UNICODE),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('NegativeCommentRadar run failed', [
                'run_id' => $runId,
                'message' => $e->getMessage(),
            ]);
            DB::table('ncr_runs')->where('id', $runId)->update([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 1000),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
            throw $e;
        }

        return DB::table('ncr_runs')->where('id', $runId)->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function presentRun(?object $run, ?string $activeNiche = null): array
    {
        if (! $run) {
            return [
                'run' => null,
                'niches' => $this->emptyNicheCards(),
                'hot_issues' => [],
                'recommendations' => null,
                'items' => [],
                'active_niche' => $activeNiche,
            ];
        }

        $summary = $this->decodeJson($run->niche_summary ?? null);
        $recommendations = $this->decodeJson($run->training_recommendations ?? null);
        $nicheCards = $this->emptyNicheCards();
        $total = max(1, (int) ($run->classified_count ?? 0));

        foreach ($nicheCards as &$card) {
            $code = $card['code'];
            $count = (int) ($summary[$code]['count'] ?? 0);
            $card['count'] = $count;
            $card['pct'] = round(($count / $total) * 100, 1);
            $card['heat'] = $this->heatLevel($count, $total);
            $card['sample_hints'] = array_values(array_filter((array) ($summary[$code]['hints'] ?? [])));
        }
        unset($card);

        usort($nicheCards, fn ($a, $b) => $b['count'] <=> $a['count']);

        $hotIssues = array_values(array_filter($nicheCards, fn ($c) => $c['count'] > 0));

        $itemsQuery = DB::table('ncr_classifications')
            ->where('run_id', (int) $run->id)
            ->orderByDesc('confidence')
            ->orderByDesc('event_at');

        if ($activeNiche && isset(self::NICHES[$activeNiche])) {
            $itemsQuery->where(function ($q) use ($activeNiche) {
                $q->where('primary_niche', $activeNiche)
                    ->orWhereRaw('JSON_CONTAINS(niches, JSON_QUOTE(?))', [$activeNiche]);
            });
        }

        $items = $itemsQuery->limit(200)->get()->map(function ($row) {
            $niches = $this->decodeJson($row->niches);
            if (! is_array($niches)) {
                $niches = [$row->primary_niche];
            }

            return [
                'id' => (int) $row->id,
                'feedback_case_id' => (int) $row->feedback_case_id,
                'primary_niche' => (string) $row->primary_niche,
                'primary_niche_label' => self::NICHES[$row->primary_niche] ?? $row->primary_niche,
                'niches' => array_values(array_map(
                    fn ($c) => ['code' => $c, 'label' => self::NICHES[$c] ?? $c],
                    $niches
                )),
                'confidence' => $row->confidence !== null ? (float) $row->confidence : null,
                'severity' => (string) ($row->severity ?? ''),
                'id_outlet' => $row->id_outlet !== null ? (int) $row->id_outlet : null,
                'outlet_name' => (string) ($row->outlet_name ?? '-'),
                'event_at' => $row->event_at,
                'author_name' => (string) ($row->author_name ?? ''),
                'summary_text' => (string) ($row->summary_text ?? ''),
                'raw_excerpt' => (string) ($row->raw_excerpt ?? ''),
                'ai_reason' => (string) ($row->ai_reason ?? ''),
                'training_hint' => (string) ($row->training_hint ?? ''),
            ];
        })->values()->all();

        return [
            'run' => [
                'id' => (int) $run->id,
                'date_from' => (string) $run->date_from,
                'date_to' => (string) $run->date_to,
                'id_outlet' => $run->id_outlet !== null ? (int) $run->id_outlet : null,
                'status' => (string) $run->status,
                'total_cases' => (int) $run->total_cases,
                'classified_count' => (int) $run->classified_count,
                'ai_provider' => (string) ($run->ai_provider ?? ''),
                'error_message' => $run->error_message,
                'started_at' => $run->started_at,
                'finished_at' => $run->finished_at,
                'created_at' => $run->created_at,
            ],
            'niches' => $nicheCards,
            'hot_issues' => $hotIssues,
            'recommendations' => $recommendations,
            'items' => $items,
            'active_niche' => $activeNiche,
        ];
    }

    /**
     * @param  Collection<int, object>  $cases
     * @return list<array<string, mixed>>
     */
    private function classifyCases(Collection $cases, string $provider): array
    {
        if ($cases->isEmpty()) {
            return [];
        }

        $prepared = $cases->values()->map(function ($case, $idx) {
            $raw = preg_replace('/\s+/u', ' ', (string) ($case->raw_text ?? ''));
            $summary = (string) ($case->summary_id ?? '');
            $topics = $this->decodeJson($case->topics ?? null);
            $topicStr = is_array($topics) ? implode(', ', array_map('strval', $topics)) : '';

            return [
                'idx' => $idx,
                'feedback_case_id' => (int) $case->id,
                'id_outlet' => $case->id_outlet !== null ? (int) $case->id_outlet : null,
                'outlet_name' => (string) ($case->nama_outlet ?? '-'),
                'author_name' => (string) ($case->author_name ?? ''),
                'severity' => (string) ($case->severity ?? ''),
                'event_at' => $case->event_at,
                'summary_text' => Str::limit($summary !== '' ? $summary : $raw, 280),
                'raw_excerpt' => Str::limit($raw, 450),
                'topics' => $topicStr,
                'text_for_ai' => Str::limit(trim($summary.' '.$raw.' '.$topicStr), 700),
            ];
        });

        $aiByIdx = [];
        foreach ($prepared->chunk(self::AI_CHUNK_SIZE) as $chunk) {
            $chunkItems = $chunk->values()->all();
            try {
                $mapped = $this->classifyChunkWithAi($chunkItems, $provider);
                foreach ($mapped as $idx => $result) {
                    $aiByIdx[(int) $idx] = $result;
                }
            } catch (\Throwable $e) {
                Log::warning('NCR AI chunk failed, using keyword fallback', [
                    'message' => $e->getMessage(),
                    'count' => count($chunkItems),
                ]);
            }
        }

        $out = [];
        foreach ($prepared as $row) {
            $idx = (int) $row['idx'];
            $ai = $aiByIdx[$idx] ?? null;
            if (! $ai) {
                $ai = $this->keywordFallback($row['text_for_ai'], $row['topics']);
            }

            $niches = $this->normalizeNicheList($ai['niches'] ?? []);
            if ($niches === []) {
                $fallback = $this->keywordFallback($row['text_for_ai'], $row['topics']);
                $niches = $fallback['niches'];
                $ai['reason'] = $ai['reason'] ?? $fallback['reason'];
                $ai['training_hint'] = $ai['training_hint'] ?? $fallback['training_hint'];
                $ai['confidence'] = $ai['confidence'] ?? $fallback['confidence'];
            }

            $primary = $ai['primary_niche'] ?? ($niches[0] ?? 'staff_friendliness');
            if (! isset(self::NICHES[$primary])) {
                $primary = $niches[0];
            }
            if (! in_array($primary, $niches, true)) {
                array_unshift($niches, $primary);
                $niches = array_values(array_unique($niches));
            }

            $out[] = [
                'feedback_case_id' => $row['feedback_case_id'],
                'primary_niche' => $primary,
                'niches' => array_slice($niches, 0, 3),
                'confidence' => round(max(0, min(100, (float) ($ai['confidence'] ?? 55))), 2),
                'severity' => $row['severity'],
                'id_outlet' => $row['id_outlet'],
                'outlet_name' => $row['outlet_name'],
                'event_at' => $row['event_at'],
                'author_name' => $row['author_name'],
                'summary_text' => $row['summary_text'],
                'raw_excerpt' => $row['raw_excerpt'],
                'ai_reason' => Str::limit((string) ($ai['reason'] ?? ''), 480),
                'training_hint' => Str::limit((string) ($ai['training_hint'] ?? $this->defaultTrainingHint($primary)), 380),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<int, array{primary_niche: string, niches: list<string>, confidence: float, reason: string, training_hint: string}>
     */
    private function classifyChunkWithAi(array $items, string $provider): array
    {
        $payload = [];
        foreach ($items as $item) {
            $payload[] = [
                'idx' => (int) $item['idx'],
                'outlet' => (string) ($item['outlet_name'] ?? ''),
                'severity' => (string) ($item['severity'] ?? ''),
                'topics' => (string) ($item['topics'] ?? ''),
                'text' => (string) ($item['text_for_ai'] ?? ''),
            ];
        }

        $jsonInput = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $nicheList = implode(', ', array_keys(self::NICHES));
        $prompt = <<<PROMPT
Anda adalah analist Voice of Customer untuk restoran F&B (Justus).
Tugas: klasifikasikan setiap komentar NEGATIF ke niche training berikut (kode persis):
- staff_friendliness: keramahan, sikap, komunikasi staff, kurang ramah, kasar, tidak menyapa
- speed_serving_time: lama menunggu, lambat, antrian, serving time, order lama
- wrong_transaction: salah charge, salah order, salah bayar, bill salah, transaksi keliru
- food_quality: rasa, porsi, dingin, gosong, basi, kualitas makanan/minuman
- cleanliness: kotor, lalat, toilet, meja kotor, higiene area
- grooming: penampilan staff, seragam, rambut, kuku, bau badan, tidak rapi

Data JSON:
{$jsonInput}

Aturan:
1. primary_niche wajib salah satu: {$nicheList}
2. niches: 1–3 niche paling relevan (termasuk primary)
3. confidence 0–100
4. reason: 1 kalimat singkat Bahasa Indonesia
5. training_hint: saran topik training singkat untuk trainer (Bahasa Indonesia)

Output HANYA JSON valid tanpa markdown:
{"items":[{"idx":0,"primary_niche":"food_quality","niches":["food_quality","cleanliness"],"confidence":82,"reason":"...","training_hint":"..."}]}
Semua idx input harus muncul tepat sekali.
PROMPT;

        $raw = $this->invokeAi($prompt, $provider);
        $decoded = $this->extractJson($raw);
        if (! is_array($decoded) || ! isset($decoded['items']) || ! is_array($decoded['items'])) {
            throw new \RuntimeException('Respons AI tidak valid untuk klasifikasi NCR.');
        }

        $byIdx = [];
        foreach ($decoded['items'] as $item) {
            if (! is_array($item) || ! array_key_exists('idx', $item)) {
                continue;
            }
            $niches = $this->normalizeNicheList($item['niches'] ?? []);
            $primary = (string) ($item['primary_niche'] ?? '');
            if (! isset(self::NICHES[$primary])) {
                $primary = $niches[0] ?? 'staff_friendliness';
            }
            if ($niches === []) {
                $niches = [$primary];
            }
            $byIdx[(int) $item['idx']] = [
                'primary_niche' => $primary,
                'niches' => $niches,
                'confidence' => (float) ($item['confidence'] ?? 70),
                'reason' => (string) ($item['reason'] ?? ''),
                'training_hint' => (string) ($item['training_hint'] ?? ''),
            ];
        }

        return $byIdx;
    }

    /**
     * @return array{primary_niche: string, niches: list<string>, confidence: float, reason: string, training_hint: string}
     */
    private function keywordFallback(string $text, string $topics = ''): array
    {
        $hay = mb_strtolower(trim($text.' '.$topics));
        $scores = array_fill_keys(array_keys(self::NICHES), 0);

        $rules = [
            'staff_friendliness' => ['tidak ramah', 'kurang ramah', 'kasar', 'cuek', 'tidak menyapa', 'unfriendly', 'attitude', 'sombong', 'galak', 'pelayanan buruk', 'service buruk', 'staff'],
            'speed_serving_time' => ['lama', 'lambat', 'nunggu', 'menunggu', 'antrian', 'serving', 'lambat datang', 'order lama', 'slow', 'wait', 'kelamaan'],
            'wrong_transaction' => ['salah charge', 'salah bayar', 'salah hitung', 'bill salah', 'tagihan', 'salah order', 'order salah', 'transaksi', 'double charge', 'overcharge', 'kurang bayar'],
            'food_quality' => ['rasa', 'hambar', 'asin', 'gosong', 'basi', 'dingin', 'porsi', 'tidak enak', 'makanan', 'minuman', 'quality', 'busuk', 'mentah'],
            'cleanliness' => ['kotor', 'lalat', 'kecoa', 'bau', 'toilet', 'higiene', 'hygiene', 'bersih', 'noda', 'debu', 'sampah'],
            'grooming' => ['seragam', 'rambut', 'kuku', 'bau badan', 'tidak rapi', 'grooming', 'penampilan', 'kumis', 'jenggot', 'baju kotor'],
        ];

        foreach ($rules as $niche => $keywords) {
            foreach ($keywords as $kw) {
                if ($kw !== '' && str_contains($hay, $kw)) {
                    $scores[$niche] += 2;
                }
            }
        }

        arsort($scores);
        $ranked = array_keys(array_filter($scores, fn ($s) => $s > 0));
        if ($ranked === []) {
            $ranked = ['staff_friendliness'];
        }

        $primary = $ranked[0];
        $niches = array_slice($ranked, 0, 2);

        return [
            'primary_niche' => $primary,
            'niches' => $niches,
            'confidence' => 48.0,
            'reason' => 'Klasifikasi fallback berbasis kata kunci (AI tidak tersedia / gagal).',
            'training_hint' => $this->defaultTrainingHint($primary),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $classified
     * @return array<string, array{count: int, hints: list<string>}>
     */
    private function buildNicheSummary(Collection $classified): array
    {
        $summary = [];
        foreach (array_keys(self::NICHES) as $code) {
            $summary[$code] = ['count' => 0, 'hints' => []];
        }

        foreach ($classified as $row) {
            $seen = [];
            foreach (($row['niches'] ?? []) as $niche) {
                if (! isset($summary[$niche]) || isset($seen[$niche])) {
                    continue;
                }
                $seen[$niche] = true;
                $summary[$niche]['count']++;
                $hint = trim((string) ($row['training_hint'] ?? ''));
                if ($hint !== '' && count($summary[$niche]['hints']) < 5) {
                    $summary[$niche]['hints'][] = $hint;
                }
            }
        }

        return $summary;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $classified
     * @param  array<string, array{count: int, hints: list<string>}>  $nicheSummary
     * @return array{overview: string, by_niche: array<string, string>, priority: list<string>}
     */
    private function buildTrainingRecommendations(Collection $classified, array $nicheSummary, string $provider): array
    {
        $byNiche = [];
        $priority = [];
        $sorted = $nicheSummary;
        uasort($sorted, fn ($a, $b) => ($b['count'] ?? 0) <=> ($a['count'] ?? 0));

        foreach ($sorted as $code => $meta) {
            if (($meta['count'] ?? 0) <= 0) {
                continue;
            }
            $priority[] = $code;
            $hint = $meta['hints'][0] ?? $this->defaultTrainingHint($code);
            $byNiche[$code] = sprintf(
                'Hot issue %s (%d kasus): %s',
                self::NICHES[$code] ?? $code,
                (int) $meta['count'],
                $hint
            );
        }

        $overview = $priority === []
            ? 'Tidak ada komentar negatif pada rentang yang dipilih.'
            : 'Prioritas training trainer: '.implode(', ', array_map(
                fn ($c) => self::NICHES[$c] ?? $c,
                array_slice($priority, 0, 3)
            )).'. Fokus pada hot issue teratas untuk menyusun materi Just Academy.';

        // Optional enrichment overview via AI (short)
        if ($classified->isNotEmpty() && $priority !== []) {
            try {
                $topLines = [];
                foreach (array_slice($priority, 0, 3) as $code) {
                    $topLines[] = (self::NICHES[$code] ?? $code).': '.(int) ($nicheSummary[$code]['count'] ?? 0).' kasus';
                }
                $samples = $classified->take(8)->map(fn ($r) => '- ['.($r['primary_niche'] ?? '').'] '.Str::limit((string) ($r['summary_text'] ?? ''), 120))->implode("\n");
                $prompt = "Buat 2–3 kalimat rekomendasi training untuk trainer F&B Just Academy berdasarkan hot issue berikut:\n"
                    .implode("\n", $topLines)."\nContoh komentar:\n{$samples}\n"
                    ."Jawab ringkas Bahasa Indonesia, tanpa markdown, fokus action plan training.";
                $aiOverview = trim($this->invokeAi($prompt, $provider));
                if ($aiOverview !== '') {
                    $overview = Str::limit(preg_replace('/\s+/u', ' ', $aiOverview) ?? $overview, 600);
                }
            } catch (\Throwable $e) {
                Log::info('NCR overview AI skipped', ['message' => $e->getMessage()]);
            }
        }

        return [
            'overview' => $overview,
            'by_niche' => $byNiche,
            'priority' => $priority,
        ];
    }

    /**
     * @return list<array{code: string, label: string, count: int, pct: float, heat: string, sample_hints: list<string>}>
     */
    private function emptyNicheCards(): array
    {
        $cards = [];
        foreach (self::NICHES as $code => $label) {
            $cards[] = [
                'code' => $code,
                'label' => $label,
                'count' => 0,
                'pct' => 0.0,
                'heat' => 'cold',
                'sample_hints' => [],
            ];
        }

        return $cards;
    }

    private function heatLevel(int $count, int $total): string
    {
        if ($count <= 0) {
            return 'cold';
        }
        $pct = ($count / max(1, $total)) * 100;
        if ($pct >= 25 || $count >= 15) {
            return 'hot';
        }
        if ($pct >= 12 || $count >= 6) {
            return 'warm';
        }

        return 'mild';
    }

    private function defaultTrainingHint(string $niche): string
    {
        return match ($niche) {
            'staff_friendliness' => 'Training Soft Skill: greeting, eye contact, empati, dan recovery service.',
            'speed_serving_time' => 'Training Speed of Service: sequencing dapur/bar, communication runner, target serving time.',
            'wrong_transaction' => 'Training Kasir & Order Accuracy: konfirmasi order, double-check bill, SOP koreksi transaksi.',
            'food_quality' => 'Training Food Quality: standar rasa, holding time, plating, QC sebelum serve.',
            'cleanliness' => 'Training Hygiene & Cleanliness: opening/closing checklist, toilet, area meja & floor.',
            'grooming' => 'Training Grooming Standards: seragam, rambut, kuku, personal hygiene checklist harian.',
            default => 'Susun modul training sesuai pola keluhan yang muncul.',
        };
    }

    /**
     * @param  mixed  $niches
     * @return list<string>
     */
    private function normalizeNicheList($niches): array
    {
        if (! is_array($niches)) {
            return [];
        }

        $out = [];
        foreach ($niches as $n) {
            $code = strtolower(trim((string) $n));
            $code = str_replace([' ', '-'], '_', $code);
            $aliases = [
                'speedity' => 'speed_serving_time',
                'speed' => 'speed_serving_time',
                'serving_time' => 'speed_serving_time',
                'service_speed' => 'speed_serving_time',
                'friendliness' => 'staff_friendliness',
                'staff' => 'staff_friendliness',
                'transaction' => 'wrong_transaction',
                'billing' => 'wrong_transaction',
                'food' => 'food_quality',
                'hygiene' => 'cleanliness',
                'clean' => 'cleanliness',
            ];
            $code = $aliases[$code] ?? $code;
            if (isset(self::NICHES[$code])) {
                $out[] = $code;
            }
        }

        return array_values(array_unique($out));
    }

    private function resolveProvider(): string
    {
        $p = strtolower((string) config('ai.provider', 'gemini'));

        return in_array($p, ['gemini', 'openai', 'claude'], true) ? $p : 'gemini';
    }

    private function invokeAi(string $prompt, string $provider): string
    {
        return match ($provider) {
            'openai' => $this->invokeOpenAi($prompt),
            'claude' => $this->invokeClaude($prompt),
            default => $this->invokeGemini($prompt),
        };
    }

    private function invokeGemini(string $prompt): string
    {
        $apiKey = (string) config('ai.gemini.api_key');
        if ($apiKey === '') {
            throw new \RuntimeException('GOOGLE_GEMINI_API_KEY kosong.');
        }

        $model = (string) config('ai.gemini.model', 'gemini-1.5-flash');
        $model = preg_replace('#^models/#', '', $model) ?: 'gemini-1.5-flash';
        // Prefer flash for classification cost/latency if pro configured
        if (str_contains($model, '1.5-pro') || str_contains($model, '2.0-pro')) {
            $model = 'gemini-1.5-flash';
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent?key='.urlencode($apiKey);
        $http = Http::timeout(120);
        if (config('app.env') === 'local' || config('app.debug')) {
            $http = $http->withoutVerifying();
        }

        $response = $http->post($url, [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 4096,
            ],
        ]);

        if (! $response->successful()) {
            Log::error('NCR Gemini failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Gemini API error: '.$response->status());
        }

        $parts = $response->json('candidates.0.content.parts') ?? [];
        $text = '';
        if (is_array($parts)) {
            foreach ($parts as $part) {
                if (is_array($part) && isset($part['text'])) {
                    $text .= $part['text'];
                }
            }
        }

        if (trim($text) === '') {
            throw new \RuntimeException('Jawaban Gemini kosong.');
        }

        return trim($text);
    }

    private function invokeOpenAi(string $prompt): string
    {
        $apiKey = (string) config('ai.openai.api_key');
        if ($apiKey === '') {
            throw new \RuntimeException('OPENAI_API_KEY kosong.');
        }

        $model = (string) config('ai.openai.model', 'gpt-4o-mini');
        $http = Http::timeout(120)->withToken($apiKey);
        if (config('app.env') === 'local' || config('app.debug')) {
            $http = $http->withoutVerifying();
        }

        $response = $http->post('https://api.openai.com/v1/chat/completions', [
            'model' => $model,
            'temperature' => 0.2,
            'messages' => [
                ['role' => 'system', 'content' => 'You classify F&B negative guest comments into training niches. Reply with JSON only.'],
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('OpenAI API error: '.$response->status());
        }

        $text = trim((string) ($response->json('choices.0.message.content') ?? ''));
        if ($text === '') {
            throw new \RuntimeException('Jawaban OpenAI kosong.');
        }

        return $text;
    }

    private function invokeClaude(string $prompt): string
    {
        $apiKey = (string) config('ai.claude.api_key');
        if ($apiKey === '') {
            throw new \RuntimeException('ANTHROPIC_API_KEY kosong.');
        }

        $model = (string) config('ai.claude.model', 'claude-haiku-4-5-20251001');
        $http = Http::timeout(120)->withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ]);
        if (config('app.env') === 'local' || config('app.debug')) {
            $http = $http->withoutVerifying();
        }

        $response = $http->post('https://api.anthropic.com/v1/messages', [
            'model' => $model,
            'max_tokens' => 4096,
            'temperature' => 0.2,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Claude API error: '.$response->status());
        }

        $text = trim((string) ($response->json('content.0.text') ?? ''));
        if ($text === '') {
            throw new \RuntimeException('Jawaban Claude kosong.');
        }

        return $text;
    }

    private function extractJson(string $text): ?array
    {
        $text = trim($text);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $text, $m)) {
            $text = trim($m[1]);
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function decodeJson(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
