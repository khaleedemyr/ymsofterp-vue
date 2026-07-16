<?php

namespace App\Http\Controllers;

use App\Exports\GoogleReviewAiReportExport;
use App\Jobs\ProcessGoogleReviewAiReportJob;
use App\Jobs\ProcessInstagramCommentAiReportJob;
use App\Models\GoogleReviewManualReview;
use App\Services\ApifyGoogleReviewsService;
use App\Services\GooglePlacesService;
use App\Services\GoogleReviewDeduper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class GoogleReviewController extends Controller
{
    private function getBlockedManualReviewIds(): array
    {
        $rows = DB::table('google_review_ai_reports')
            ->select('source_payload')
            ->where('source', 'manual_db')
            ->whereIn('status', ['pending', 'processing', 'completed'])
            ->whereNotNull('source_payload')
            ->orderByDesc('id')
            ->get();

        $blocked = [];
        foreach ($rows as $row) {
            $payload = json_decode((string) $row->source_payload, true);
            $ids = is_array($payload['manual_review_ids'] ?? null) ? $payload['manual_review_ids'] : [];
            foreach ($ids as $id) {
                $intId = (int) $id;
                if ($intId > 0) {
                    $blocked[$intId] = true;
                }
            }
        }

        return array_map('intval', array_keys($blocked));
    }

    private function encodeTextForLegacyUtf8(string $text): string
    {
        // Store as JSON-unicode escaped ASCII (e.g. emoji => \ud83d\udc99)
        // so it can be saved in non-utf8mb4 columns without DB changes.
        $encoded = json_encode($text);
        if (! is_string($encoded) || strlen($encoded) < 2) {
            return $text;
        }

        return substr($encoded, 1, -1);
    }

    private function decodeTextFromLegacyUtf8(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        $decoded = json_decode('"'.addcslashes($text, "\\\"").'"');
        return is_string($decoded) ? $decoded : $text;
    }

    protected $placesService;
    protected $apifyService;

    public function __construct(GooglePlacesService $placesService, ApifyGoogleReviewsService $apifyService)
    {
        $this->placesService = $placesService;
        $this->apifyService = $apifyService;
    }

    public function index()
    {
        $outlets = \DB::table('tbl_data_outlet')
            ->select('id_outlet as id', 'nama_outlet', 'place_id')
            ->whereNotNull('place_id')
            ->get();

        $instagramProfiles = collect(config('instagram.profiles', []))
            ->map(fn ($meta, $key) => [
                'key' => $key,
                'label' => (string) ($meta['label'] ?? $key),
                'url' => (string) ($meta['url'] ?? ''),
            ])
            ->values()
            ->all();

        try {
            $igPosts = (int) DB::table('instagram_posts')->count();
            $igComments = (int) DB::table('instagram_comments')->count();
        } catch (\Throwable) {
            $igPosts = 0;
            $igComments = 0;
        }

        return inertia('google-review/Index', [
            'outlets' => $outlets,
            'instagramProfiles' => $instagramProfiles,
            'instagramProcessQueue' => (string) config('instagram.process_queue', 'instagram-scraper'),
            'instagramDispatchSync' => (bool) config('instagram.dispatch_sync', false),
            'queueDefaultConnection' => (string) config('queue.default', 'sync'),
            'instagramStats' => [
                'posts' => $igPosts,
                'comments' => $igComments,
            ],
            'dashboardData' => $this->buildDashboardData(),
        ]);
    }

    public function manualIndex(Request $request)
    {
        $perPage = (int) ($request->query('per_page') ?? 20);
        $perPage = max(10, min(100, $perPage));

        $outlets = \DB::table('tbl_data_outlet')
            ->select('id_outlet as id', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        $query = GoogleReviewManualReview::query();
        if ($request->filled('id_outlet')) {
            $query->where('id_outlet', (int) $request->id_outlet);
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', (int) $request->is_active);
        }
        if ($request->filled('q')) {
            $q = '%'.$request->q.'%';
            $query->where(function ($sub) use ($q) {
                $sub->where('author', 'like', $q)
                    ->orWhere('text', 'like', $q)
                    ->orWhere('nama_outlet', 'like', $q);
            });
        }

        $blockedManualReviewIds = $this->getBlockedManualReviewIds();
        $blockedLookup = array_fill_keys($blockedManualReviewIds, true);

        $reviews = $query->orderByDesc('id')->paginate($perPage)->withQueryString();
        $reviews->setCollection(
            $reviews->getCollection()->map(function ($row) use ($blockedLookup) {
                $row->text = $this->decodeTextFromLegacyUtf8($row->text);
                $row->ai_blocked = isset($blockedLookup[(int) $row->id]);
                return $row;
            })
        );

        return Inertia::render('google-review/Manual', [
            'outlets' => $outlets,
            'reviews' => $reviews,
            'blockedManualReviewIds' => $blockedManualReviewIds,
            'filters' => [
                'id_outlet' => $request->id_outlet ?? '',
                'is_active' => $request->is_active ?? '',
                'q' => $request->q ?? '',
                'per_page' => $perPage,
            ],
        ]);
    }

    public function manualStore(Request $request)
    {
        $data = $request->validate([
            'id_outlet' => 'nullable|integer',
            'author' => 'required|string|max:255',
            'rating' => 'required|numeric|min:1|max:5',
            'review_date' => 'required|date',
            'text' => 'required|string',
            'profile_photo' => 'nullable|url|max:1024',
            'is_active' => 'required|boolean',
        ]);
        $encodedText = $this->encodeTextForLegacyUtf8((string) $data['text']);

        $outlet = null;
        if (! empty($data['id_outlet'])) {
            $outlet = DB::table('tbl_data_outlet')
                ->select('id_outlet', 'nama_outlet')
                ->where('id_outlet', (int) $data['id_outlet'])
                ->first();
        }

        $created = GoogleReviewManualReview::create([
            'id_outlet' => $outlet?->id_outlet,
            'nama_outlet' => $outlet?->nama_outlet,
            'author' => $data['author'],
            'rating' => (string) $data['rating'],
            'review_date' => $data['review_date'],
            'text' => $encodedText,
            'profile_photo' => $data['profile_photo'] ?? null,
            'is_active' => (bool) $data['is_active'],
            'created_by' => auth()->user()->name ?? null,
            'updated_by' => auth()->user()->name ?? null,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Manual review berhasil ditambahkan.',
                'data' => array_merge($created->toArray(), [
                    'text' => $this->decodeTextFromLegacyUtf8($created->text),
                ]),
            ]);
        }
        return redirect()->route('google-review.manual.index')->with('success', 'Manual review berhasil ditambahkan.');
    }

    public function manualUpdate(Request $request, int $id)
    {
        $review = GoogleReviewManualReview::findOrFail($id);
        $data = $request->validate([
            'id_outlet' => 'nullable|integer',
            'author' => 'required|string|max:255',
            'rating' => 'required|numeric|min:1|max:5',
            'review_date' => 'required|date',
            'text' => 'required|string',
            'profile_photo' => 'nullable|url|max:1024',
            'is_active' => 'required|boolean',
        ]);
        $encodedText = $this->encodeTextForLegacyUtf8((string) $data['text']);

        $outlet = null;
        if (! empty($data['id_outlet'])) {
            $outlet = DB::table('tbl_data_outlet')
                ->select('id_outlet', 'nama_outlet')
                ->where('id_outlet', (int) $data['id_outlet'])
                ->first();
        }

        $review->update([
            'id_outlet' => $outlet?->id_outlet,
            'nama_outlet' => $outlet?->nama_outlet,
            'author' => $data['author'],
            'rating' => (string) $data['rating'],
            'review_date' => $data['review_date'],
            'text' => $encodedText,
            'profile_photo' => $data['profile_photo'] ?? null,
            'is_active' => (bool) $data['is_active'],
            'updated_by' => auth()->user()->name ?? null,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Manual review berhasil diperbarui.',
                'data' => array_merge($review->fresh()->toArray(), [
                    'text' => $this->decodeTextFromLegacyUtf8($review->fresh()->text),
                ]),
            ]);
        }
        return redirect()->route('google-review.manual.index')->with('success', 'Manual review berhasil diperbarui.');
    }

    public function manualDestroy(Request $request, int $id)
    {
        GoogleReviewManualReview::findOrFail($id)->delete();
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Manual review berhasil dihapus.',
            ]);
        }
        return redirect()->route('google-review.manual.index')->with('success', 'Manual review berhasil dihapus.');
    }

    public function dashboard()
    {
        return Inertia::render('google-review/Dashboard', $this->buildDashboardData());
    }

    public function apiOutlets(Request $request)
    {
        try {
            $outlets = DB::table('tbl_data_outlet')
                ->select('id_outlet as id', 'nama_outlet', 'place_id')
                ->whereNotNull('place_id')
                ->orderBy('nama_outlet')
                ->get();

            return response()->json([
                'success' => true,
                'outlets' => $outlets,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data outlet.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Snapshot data untuk mobile / Approval App (dashboard + konfigurasi Instagram).
     */
    public function apiWorkspace(Request $request)
    {
        try {
            $igPosts = (int) DB::table('instagram_posts')->count();
            $igComments = (int) DB::table('instagram_comments')->count();
        } catch (\Throwable) {
            $igPosts = 0;
            $igComments = 0;
        }

        $instagramProfiles = collect(config('instagram.profiles', []))
            ->map(fn ($meta, $key) => [
                'key' => $key,
                'label' => (string) ($meta['label'] ?? $key),
                'url' => (string) ($meta['url'] ?? ''),
            ])
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'instagram_profiles' => $instagramProfiles,
            'instagram_stats' => [
                'posts' => $igPosts,
                'comments' => $igComments,
            ],
            'instagram_process_queue' => (string) config('instagram.process_queue', 'instagram-scraper'),
            'instagram_dispatch_sync' => (bool) config('instagram.dispatch_sync', false),
            'queue_default_connection' => (string) config('queue.default', 'sync'),
            'dashboard' => $this->buildDashboardData(),
        ]);
    }

    /**
     * Daftar manual review + outlet (JSON) untuk Approval App.
     */
    public function apiManualIndex(Request $request)
    {
        $perPage = (int) ($request->query('per_page') ?? 20);
        $perPage = max(10, min(100, $perPage));

        $outlets = DB::table('tbl_data_outlet')
            ->select('id_outlet as id', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        $query = GoogleReviewManualReview::query();
        if ($request->filled('id_outlet')) {
            $query->where('id_outlet', (int) $request->id_outlet);
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', (int) $request->is_active);
        }
        if ($request->filled('q')) {
            $q = '%'.$request->q.'%';
            $query->where(function ($sub) use ($q) {
                $sub->where('author', 'like', $q)
                    ->orWhere('text', 'like', $q)
                    ->orWhere('nama_outlet', 'like', $q);
            });
        }

        $blockedManualReviewIds = $this->getBlockedManualReviewIds();
        $blockedLookup = array_fill_keys($blockedManualReviewIds, true);

        $reviews = $query->orderByDesc('id')->paginate($perPage)->withQueryString();
        $reviews->setCollection(
            $reviews->getCollection()->map(function ($row) use ($blockedLookup) {
                $row->text = $this->decodeTextFromLegacyUtf8($row->text);
                $row->ai_blocked = isset($blockedLookup[(int) $row->id]);

                return $row;
            })
        );

        return response()->json([
            'success' => true,
            'outlets' => $outlets,
            'reviews' => $reviews,
            'blocked_manual_review_ids' => $blockedManualReviewIds,
            'filters' => [
                'id_outlet' => $request->id_outlet ?? '',
                'is_active' => $request->is_active ?? '',
                'q' => $request->q ?? '',
                'per_page' => $perPage,
            ],
        ]);
    }

    protected function buildDashboardData(): array
    {
        $today = now();
        $since30 = $today->copy()->subDays(29)->startOfDay();
        $since14 = $today->copy()->subDays(13)->startOfDay();

        $cards = [
            'instagram_posts' => 0,
            'instagram_comments' => 0,
            'ai_reports_completed' => 0,
            'ai_items_total' => 0,
        ];
        $sentiment = [
            'google' => ['positive' => 0, 'neutral' => 0, 'minor' => 0, 'major' => 0, 'critical' => 0],
            'instagram' => ['positive' => 0, 'neutral' => 0, 'minor' => 0, 'major' => 0, 'critical' => 0],
        ];
        $daily = [];
        $topProfiles = [];
        $topNegativeTopics = [
            'google' => [],
            'instagram' => [],
        ];
        $profileRisk = [];
        $aiInsights = [];
        $weeklySpike = [
            'instagram_comments' => ['current_7d' => 0, 'previous_7d' => 0, 'change_pct' => 0.0],
            'instagram_negative' => ['current_7d' => 0, 'previous_7d' => 0, 'change_pct' => 0.0],
        ];
        $recommendedActions = [];

        try {
            $cards['instagram_posts'] = (int) DB::table('instagram_posts')->count();
            $cards['instagram_comments'] = (int) DB::table('instagram_comments')->count();
            $cards['ai_reports_completed'] = (int) DB::table('google_review_ai_reports')->where('status', 'completed')->count();
            $cards['ai_items_total'] = (int) DB::table('google_review_ai_items')->count();
        } catch (\Throwable) {
        }

        try {
            $rows = DB::table('google_review_ai_items as i')
                ->join('google_review_ai_reports as r', 'r.id', '=', 'i.report_id')
                ->select('r.source', 'i.severity', DB::raw('COUNT(*) as total'))
                ->where('r.status', 'completed')
                ->whereIn('r.source', ['apify_dataset', 'places_api', 'scraper_inline', 'manual_db', 'instagram_comments_db'])
                ->where('r.created_at', '>=', $since30)
                ->groupBy('r.source', 'i.severity')
                ->get();
            foreach ($rows as $r) {
                $bucket = $r->source === 'instagram_comments_db' ? 'instagram' : 'google';
                $sev = $this->normalizeAiSeverityBucket((string) $r->severity);
                if (isset($sentiment[$bucket][$sev])) {
                    $sentiment[$bucket][$sev] += (int) $r->total;
                }
            }
        } catch (\Throwable) {
        }

        try {
            $topicRows = DB::table('google_review_ai_items as i')
                ->join('google_review_ai_reports as r', 'r.id', '=', 'i.report_id')
                ->select('r.source', 'i.severity', 'i.topics')
                ->where('r.status', 'completed')
                ->whereIn('r.source', ['apify_dataset', 'places_api', 'scraper_inline', 'manual_db', 'instagram_comments_db'])
                ->whereIn('i.severity', ['minor', 'major', 'critical', 'mild_negative', 'negative', 'severe'])
                ->where('r.created_at', '>=', $since30)
                ->limit(4000)
                ->get();
            $topicAgg = [
                'google' => [],
                'instagram' => [],
            ];
            foreach ($topicRows as $row) {
                $bucket = $row->source === 'instagram_comments_db' ? 'instagram' : 'google';
                $topics = is_string($row->topics) ? (json_decode($row->topics, true) ?: []) : [];
                if (! is_array($topics) || $topics === []) {
                    $topics = ['other'];
                }
                foreach ($topics as $topic) {
                    $t = trim((string) $topic);
                    if ($t === '') {
                        continue;
                    }
                    $topicAgg[$bucket][$t] = (int) ($topicAgg[$bucket][$t] ?? 0) + 1;
                }
            }
            foreach (['google', 'instagram'] as $bucket) {
                arsort($topicAgg[$bucket]);
                $topNegativeTopics[$bucket] = collect($topicAgg[$bucket])
                    ->map(fn ($v, $k) => ['topic' => (string) $k, 'total' => (int) $v])
                    ->take(6)
                    ->values()
                    ->all();
            }
        } catch (\Throwable) {
        }

        try {
            $commentRows = DB::table('instagram_comments')
                ->select(DB::raw('DATE(commented_at) as d'), DB::raw('COUNT(*) as total'))
                ->whereNotNull('commented_at')
                ->where('commented_at', '>=', $since14)
                ->groupBy(DB::raw('DATE(commented_at)'))
                ->get();
            $aiRows = DB::table('google_review_ai_items as i')
                ->join('google_review_ai_reports as r', 'r.id', '=', 'i.report_id')
                ->select(DB::raw('DATE(i.created_at) as d'), DB::raw('COUNT(*) as total'))
                ->where('r.source', 'instagram_comments_db')
                ->where('r.status', 'completed')
                ->where('i.created_at', '>=', $since14)
                ->groupBy(DB::raw('DATE(i.created_at)'))
                ->get();

            $commentMap = [];
            foreach ($commentRows as $r) {
                $commentMap[(string) $r->d] = (int) $r->total;
            }
            $aiMap = [];
            foreach ($aiRows as $r) {
                $aiMap[(string) $r->d] = (int) $r->total;
            }
            for ($i = 13; $i >= 0; $i--) {
                $d = $today->copy()->subDays($i)->toDateString();
                $daily[] = [
                    'date' => $d,
                    'instagram_comments' => $commentMap[$d] ?? 0,
                    'ai_classified' => $aiMap[$d] ?? 0,
                ];
            }
        } catch (\Throwable) {
        }

        try {
            $topProfiles = DB::table('instagram_comments as c')
                ->join('instagram_posts as p', 'p.id', '=', 'c.instagram_post_id')
                ->select('p.profile_key', DB::raw('COUNT(*) as total_comments'))
                ->groupBy('p.profile_key')
                ->orderByDesc('total_comments')
                ->limit(10)
                ->get()
                ->map(fn ($r) => ['profile_key' => (string) $r->profile_key, 'total_comments' => (int) $r->total_comments])
                ->values()
                ->all();
        } catch (\Throwable) {
            $topProfiles = [];
        }

        try {
            $riskRows = DB::table('google_review_ai_items as i')
                ->join('google_review_ai_reports as r', 'r.id', '=', 'i.report_id')
                ->select(
                    'i.source_account',
                    DB::raw("SUM(CASE WHEN i.severity IN ('negative','severe','major','critical') THEN 1 ELSE 0 END) as neg_count"),
                    DB::raw('COUNT(*) as total_count')
                )
                ->where('r.status', 'completed')
                ->where('r.source', 'instagram_comments_db')
                ->where('r.created_at', '>=', $since30)
                ->whereNotNull('i.source_account')
                ->groupBy('i.source_account')
                ->havingRaw('COUNT(*) >= 3')
                ->orderByDesc('neg_count')
                ->limit(8)
                ->get();
            $profileRisk = $riskRows->map(function ($r) {
                $total = max(1, (int) $r->total_count);
                $neg = (int) $r->neg_count;

                return [
                    'profile' => (string) $r->source_account,
                    'negative_count' => $neg,
                    'total_count' => $total,
                    'negative_rate' => round(($neg / $total) * 100, 1),
                ];
            })->values()->all();
        } catch (\Throwable) {
            $profileRisk = [];
        }

        try {
            $current7 = $today->copy()->subDays(6)->startOfDay();
            $prev7Start = $today->copy()->subDays(13)->startOfDay();
            $prev7End = $today->copy()->subDays(7)->endOfDay();

            $igCur = (int) DB::table('instagram_comments')
                ->whereNotNull('commented_at')
                ->where('commented_at', '>=', $current7)
                ->count();
            $igPrev = (int) DB::table('instagram_comments')
                ->whereNotNull('commented_at')
                ->whereBetween('commented_at', [$prev7Start, $prev7End])
                ->count();

            $negCur = (int) DB::table('google_review_ai_items as i')
                ->join('google_review_ai_reports as r', 'r.id', '=', 'i.report_id')
                ->where('r.source', 'instagram_comments_db')
                ->where('r.status', 'completed')
                ->whereIn('i.severity', ['minor', 'major', 'critical', 'mild_negative', 'negative', 'severe'])
                ->where('i.created_at', '>=', $current7)
                ->count();
            $negPrev = (int) DB::table('google_review_ai_items as i')
                ->join('google_review_ai_reports as r', 'r.id', '=', 'i.report_id')
                ->where('r.source', 'instagram_comments_db')
                ->where('r.status', 'completed')
                ->whereIn('i.severity', ['minor', 'major', 'critical', 'mild_negative', 'negative', 'severe'])
                ->whereBetween('i.created_at', [$prev7Start, $prev7End])
                ->count();

            $pct = function (int $cur, int $prev): float {
                if ($prev <= 0) {
                    return $cur > 0 ? 100.0 : 0.0;
                }

                return round((($cur - $prev) / $prev) * 100, 1);
            };
            $weeklySpike['instagram_comments'] = [
                'current_7d' => $igCur,
                'previous_7d' => $igPrev,
                'change_pct' => $pct($igCur, $igPrev),
            ];
            $weeklySpike['instagram_negative'] = [
                'current_7d' => $negCur,
                'previous_7d' => $negPrev,
                'change_pct' => $pct($negCur, $negPrev),
            ];
        } catch (\Throwable) {
        }

        $googleTotal = array_sum($sentiment['google']);
        $instagramTotal = array_sum($sentiment['instagram']);
        $googleNeg = (int) (($sentiment['google']['minor'] ?? 0) + ($sentiment['google']['major'] ?? 0) + ($sentiment['google']['critical'] ?? 0));
        $igNeg = (int) (($sentiment['instagram']['minor'] ?? 0) + ($sentiment['instagram']['major'] ?? 0) + ($sentiment['instagram']['critical'] ?? 0));
        $googleNegRate = $googleTotal > 0 ? round(($googleNeg / $googleTotal) * 100, 1) : 0.0;
        $igNegRate = $instagramTotal > 0 ? round(($igNeg / $instagramTotal) * 100, 1) : 0.0;
        $topGoogleTopic = $topNegativeTopics['google'][0]['topic'] ?? 'other';
        $topIgTopic = $topNegativeTopics['instagram'][0]['topic'] ?? 'other';
        $aiInsights[] = [
            'title' => 'Ringkasan sentimen',
            'detail' => "Google negatif {$googleNegRate}% ({$googleNeg}/{$googleTotal}), Instagram negatif {$igNegRate}% ({$igNeg}/{$instagramTotal}).",
        ];
        $aiInsights[] = [
            'title' => 'Isu utama 30 hari terakhir',
            'detail' => "Google dominan: {$topGoogleTopic}. Instagram dominan: {$topIgTopic}.",
        ];
        if (! empty($profileRisk)) {
            $worst = $profileRisk[0];
            $aiInsights[] = [
                'title' => 'Profil IG perlu perhatian',
                'detail' => "{$worst['profile']} punya rasio negatif {$worst['negative_rate']}% ({$worst['negative_count']}/{$worst['total_count']}).",
            ];
        }

        $topicActionMap = [
            'service' => 'Review SOP service dan lakukan coaching shift frontliner harian.',
            'food_quality' => 'Audit konsistensi rasa/plating dan checklist quality sebelum serving.',
            'hygiene' => 'Tambah inspeksi kebersihan area produksi dan dining tiap shift.',
            'price' => 'Perjelas value promo dan update komunikasi harga di kanal digital.',
            'wait_time' => 'Perbaiki flow kitchen-pass dan monitor tiket saat jam sibuk.',
            'reservation' => 'Perketat SLA admin reservasi dan auto-reply di jam operasional.',
        ];
        foreach (['instagram', 'google'] as $bucket) {
            $top = $topNegativeTopics[$bucket][0]['topic'] ?? null;
            if ($top && isset($topicActionMap[$top])) {
                $recommendedActions[] = [
                    'channel' => $bucket,
                    'topic' => $top,
                    'action' => $topicActionMap[$top],
                ];
            }
        }

        return [
            'cards' => $cards,
            'sentiment' => $sentiment,
            'daily' => $daily,
            'topProfiles' => $topProfiles,
            'topNegativeTopics' => $topNegativeTopics,
            'profileRisk' => $profileRisk,
            'aiInsights' => $aiInsights,
            'weeklySpike' => $weeklySpike,
            'recommendedActions' => $recommendedActions,
            'range' => ['sentiment_days' => 30, 'daily_days' => 14],
        ];
    }

    public function dashboardDrilldown(Request $request)
    {
        $request->validate([
            'channel' => 'required|string|in:google,instagram',
            'metric' => 'required|string|in:sentiment,topic',
            'key' => 'required|string|max:100',
            'days' => 'nullable|integer|min:1|max:90',
            'limit' => 'nullable|integer|min:10|max:300',
            'page' => 'nullable|integer|min:1',
            'q' => 'nullable|string|max:200',
            'sort' => 'nullable|string|in:date_desc,date_asc,severity_desc,severity_asc',
        ]);

        $channel = (string) $request->query('channel');
        $metric = (string) $request->query('metric');
        $key = trim((string) $request->query('key'));
        $days = (int) ($request->query('days') ?? 30);
        $limit = (int) ($request->query('limit') ?? 120);
        $page = max(1, (int) ($request->query('page') ?? 1));
        $keyword = trim((string) ($request->query('q') ?? ''));
        $sort = (string) ($request->query('sort') ?? 'date_desc');

        $q = $this->buildDashboardDrilldownQuery($channel, $metric, $key, $days, $keyword);
        if ($q === null) {
            return response()->json(['success' => false, 'error' => 'Parameter drilldown tidak valid.'], 422);
        }

        $total = (int) (clone $q)->count();
        if ($sort === 'date_asc') {
            $q->orderBy('i.created_at');
        } elseif ($sort === 'severity_asc') {
            $q->orderBy('i.severity')->orderByDesc('i.created_at');
        } elseif ($sort === 'severity_desc') {
            $q->orderByDesc('i.severity')->orderByDesc('i.created_at');
        } else {
            $q->orderByDesc('i.created_at');
        }
        $rows = $q
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'author' => (string) ($r->author ?? ''),
                'text' => (string) ($r->text ?? ''),
                'review_date' => (string) ($r->review_date ?? ''),
                'severity' => (string) ($r->severity ?? ''),
                'summary_id' => (string) ($r->summary_id ?? ''),
                'source' => (string) ($r->source ?? ''),
                'source_account' => (string) ($r->source_account ?? ''),
                'source_post_url' => (string) ($r->source_post_url ?? ''),
                'source_post_shortcode' => (string) ($r->source_post_shortcode ?? ''),
            ])
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'channel' => $channel,
            'metric' => $metric,
            'key' => $key,
            'q' => $keyword,
            'sort' => $sort,
            'meta' => [
                'page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'last_page' => max(1, (int) ceil(max(1, $total) / $limit)),
            ],
            'count' => count($rows),
            'items' => $rows,
        ]);
    }

    public function dashboardDrilldownExport(Request $request)
    {
        $request->validate([
            'channel' => 'required|string|in:google,instagram',
            'metric' => 'required|string|in:sentiment,topic',
            'key' => 'required|string|max:100',
            'days' => 'nullable|integer|min:1|max:90',
            'q' => 'nullable|string|max:200',
            'sort' => 'nullable|string|in:date_desc,date_asc,severity_desc,severity_asc',
        ]);

        $channel = (string) $request->query('channel');
        $metric = (string) $request->query('metric');
        $key = trim((string) $request->query('key'));
        $days = (int) ($request->query('days') ?? 30);
        $keyword = trim((string) ($request->query('q') ?? ''));
        $sort = (string) ($request->query('sort') ?? 'date_desc');

        $q = $this->buildDashboardDrilldownQuery($channel, $metric, $key, $days, $keyword);
        if ($q === null) {
            abort(422, 'Parameter drilldown tidak valid.');
        }

        $filename = "dashboard-drilldown-{$channel}-{$metric}-".preg_replace('/[^a-z0-9_\-]+/i', '-', strtolower($key)).'-'.date('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['id', 'author', 'review_date', 'severity', 'summary_id', 'text', 'source', 'source_account', 'source_post_url']);

            $streamQ = clone $q;
            if ($sort === 'date_asc') {
                $streamQ->orderBy('i.created_at');
            } elseif ($sort === 'severity_asc') {
                $streamQ->orderBy('i.severity')->orderByDesc('i.created_at');
            } elseif ($sort === 'severity_desc') {
                $streamQ->orderByDesc('i.severity')->orderByDesc('i.created_at');
            } else {
                $streamQ->orderByDesc('i.created_at');
            }
            $streamQ->limit(2000)->chunk(300, function ($chunk) use ($out) {
                foreach ($chunk as $r) {
                    fputcsv($out, [
                        (int) $r->id,
                        (string) ($r->author ?? ''),
                        (string) ($r->review_date ?? ''),
                        (string) ($r->severity ?? ''),
                        (string) ($r->summary_id ?? ''),
                        (string) ($r->text ?? ''),
                        (string) ($r->source ?? ''),
                        (string) ($r->source_account ?? ''),
                        (string) ($r->source_post_url ?? ''),
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function buildDashboardDrilldownQuery(string $channel, string $metric, string $key, int $days, string $keyword = '')
    {
        $since = now()->subDays(max(0, $days - 1))->startOfDay();
        $sources = $channel === 'instagram'
            ? ['instagram_comments_db']
            : ['apify_dataset', 'places_api', 'scraper_inline', 'manual_db'];

        $q = DB::table('google_review_ai_items as i')
            ->join('google_review_ai_reports as r', 'r.id', '=', 'i.report_id')
            ->select([
                'i.id',
                'i.author',
                'i.text',
                'i.review_date',
                'i.severity',
                'i.summary_id',
                'i.source_account',
                'i.source_post_url',
                'i.source_post_shortcode',
                'r.source',
                'i.created_at',
            ])
            ->where('r.status', 'completed')
            ->whereIn('r.source', $sources)
            ->where('r.created_at', '>=', $since);

        if ($metric === 'sentiment') {
            $allowed = ['positive', 'neutral', 'minor', 'major', 'critical', 'mild_negative', 'negative', 'severe'];
            if (! in_array($key, $allowed, true)) {
                return null;
            }
            $q->where('i.severity', $key);
        } else {
            $q->whereRaw('JSON_CONTAINS(i.topics, JSON_QUOTE(?))', [$key]);
        }

        if ($keyword !== '') {
            $q->where(function ($w) use ($keyword) {
                $w->where('i.text', 'like', '%'.$keyword.'%')
                    ->orWhere('i.author', 'like', '%'.$keyword.'%')
                    ->orWhere('i.summary_id', 'like', '%'.$keyword.'%');
            });
        }

        return $q;
    }

    public function scrapeReviews(Request $request)
    {
        $request->validate([
            'place_id' => 'required|string'
        ]);

        \Log::info('GoogleReviewController@scrapeReviews', [
            'place_id' => $request->input('place_id'),
            'is_inertia' => $request->hasHeader('X-Inertia'),
        ]);

        try {
            $placeId = $request->input('place_id');
            \Log::info('Place ID:', [$placeId]);
            $placeDetails = $this->placesService->getPlaceDetails($placeId);
            \Log::info('Place Details:', [$placeDetails]);

            if ($request->hasHeader('X-Inertia')) {
                \Log::info('Return inertia redirect with result', [
                    'result' => [
                        'success' => true,
                        'place' => [
                            'name' => $placeDetails['name'],
                            'address' => $placeDetails['address'],
                            'rating' => $placeDetails['rating'],
                            'location' => $placeDetails['location']
                        ],
                        'reviews' => $placeDetails['reviews']
                    ]
                ]);
                return redirect()->back()->with('result', [
                    'success' => true,
                    'place' => [
                        'name' => $placeDetails['name'],
                        'address' => $placeDetails['address'],
                        'rating' => $placeDetails['rating'],
                        'location' => $placeDetails['location']
                    ],
                    'reviews' => $placeDetails['reviews']
                ]);
            }

            \Log::info('Return JSON result', [
                'success' => true,
                'place' => [
                    'name' => $placeDetails['name'],
                    'address' => $placeDetails['address'],
                    'rating' => $placeDetails['rating'],
                    'location' => $placeDetails['location']
                ],
                'reviews' => $placeDetails['reviews']
            ]);
            return response()->json([
                'success' => true,
                'place' => [
                    'name' => $placeDetails['name'],
                    'address' => $placeDetails['address'],
                    'rating' => $placeDetails['rating'],
                    'location' => $placeDetails['location']
                ],
                'reviews' => $placeDetails['reviews']
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching place details: ' . $e->getMessage());

            if ($request->hasHeader('X-Inertia')) {
                \Log::info('Return inertia redirect with error', [
                    'result' => [
                        'success' => false,
                        'error' => $e->getMessage()
                    ]
                ]);
                return redirect()->back()->with('result', [
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }

            \Log::info('Return JSON error', [
                'success' => false,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getScrapedReviews(Request $request)
    {
        $jsonPath = base_path('reviews.json');
        if (!file_exists($jsonPath)) {
            return response()->json([
                'success' => false,
                'error' => 'File reviews.json tidak ditemukan',
                'reviews' => []
            ], 404);
        }
        $json = file_get_contents($jsonPath);
        $reviews = json_decode($json, true);
        return response()->json([
            'success' => true,
            'reviews' => $reviews,
        ]);
    }

    public function scrapeReviewsApify(Request $request)
    {
        $request->validate([
            'place_id' => 'required|string',
            'max_reviews' => 'nullable|integer|min:1|max:2000',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
        ]);

        \Log::info('GoogleReviewController@scrapeReviewsApify', [
            'place_id' => $request->input('place_id'),
            'max_reviews' => $request->input('max_reviews'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'is_inertia' => $request->hasHeader('X-Inertia'),
        ]);

        try {
            $placeId = $request->input('place_id');
            $maxReviews = (int)($request->input('max_reviews') ?? 200);
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            $started = $this->apifyService->startScrapeByPlaceId($placeId, $maxReviews, 'newest');

            $userId = optional($request->user())->id ?? 'guest';
            $cacheKey = $this->apifyCacheKey($userId, $placeId);
            Cache::put($cacheKey, [
                'datasetId' => $started['datasetId'],
                'placeId' => $placeId,
                'place' => $started['place'],
                'itemCount' => $started['itemCount'],
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
            ], now()->addHours(6));

            $payload = [
                'success' => true,
                'place_id' => $placeId,
                'dataset_id' => $started['datasetId'],
                'place' => $started['place'],
                'item_count' => $started['itemCount'],
                'max_reviews' => $maxReviews,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ];

            if ($request->hasHeader('X-Inertia')) {
                // Do NOT flash large review payload into session (DB session payload can overflow).
                return redirect()->back()->with('result', $payload);
            }

            return response()->json($payload);
        } catch (\Exception $e) {
            \Log::error('Error fetching Apify reviews: ' . $e->getMessage());

            $payload = [
                'success' => false,
                'error' => $e->getMessage(),
            ];

            if ($request->hasHeader('X-Inertia')) {
                return redirect()->back()->with('result', $payload);
            }

            return response()->json($payload, 500);
        }
    }

    public function apifyItems(Request $request)
    {
        $request->validate([
            'dataset_id' => 'required|string',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
        ]);

        try {
            $datasetId = $request->input('dataset_id');
            $page = (int)($request->input('page') ?? 1);
            $perPage = (int)($request->input('per_page') ?? 20);
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            $data = $this->apifyService->getReviewsPageFromDataset($datasetId, $page, $perPage, $dateFrom, $dateTo);
            return response()->json([
                'success' => true,
                'reviews' => $data['reviews'],
                'meta' => $data['meta'],
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching Apify dataset items: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function exportApify(Request $request)
    {
        $request->validate([
            'dataset_id' => 'required|string',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
        ]);

        $datasetId = $request->input('dataset_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $filename = 'google-reviews-' . date('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($datasetId, $dateFrom, $dateTo) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            $this->apifyService->exportDatasetReviewsToCsv($datasetId, function (array $row) use ($out) {
                fputcsv($out, $row);
            }, $dateFrom, $dateTo);

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function apifyCacheKey($userId, string $placeId): string
    {
        return 'google-review:apify:' . $userId . ':' . $placeId;
    }

    /**
     * Bantu debug kenapa laporan AI stuck di "pending" (biasanya worker antrian tidak jalan).
     *
     * @return array{queue_connection: string, jobs_pending_count: int|null, failed_jobs_24h: int|null}
     */
    protected function aiReportQueuedMessage(): string
    {
        return 'Laporan AI sedang diproses.';
    }

    /**
     * Laporan AI disimpan per tim (bukan per akun pembuat): semua user yang login
     * boleh melihat/mengekspor selama route dilindungi auth (sama aksesnya dengan menu Google Review).
     */
    protected function userCanAccessReport(int $reportId): bool
    {
        if (! auth()->check()) {
            return false;
        }

        return DB::table('google_review_ai_reports')->where('id', $reportId)->exists();
    }

    public function aiReportsIndex(Request $request)
    {
        $reports = DB::table('google_review_ai_reports')
            ->orderByDesc('id')
            ->paginate(20)
            ->through(function ($r) {
                return [
                    'id' => $r->id,
                    'status' => $r->status,
                    'source' => $r->source,
                    'place_name' => $r->place_name,
                    'nama_outlet' => $r->nama_outlet,
                    'review_count' => (int) $r->review_count,
                    'created_at' => $r->created_at,
                    'error_message' => $r->error_message,
                ];
            });

        return Inertia::render('google-review/AiReportsIndex', [
            'reports' => $reports,
        ]);
    }

    public function apiAiReportsIndex(Request $request)
    {
        $perPage = (int) ($request->query('per_page') ?? 20);
        $perPage = max(1, min(100, $perPage));

        $reports = DB::table('google_review_ai_reports')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(function ($r) {
                return [
                    'id' => (int) $r->id,
                    'status' => (string) $r->status,
                    'source' => (string) ($r->source ?? ''),
                    'place_name' => (string) ($r->place_name ?? ''),
                    'nama_outlet' => (string) ($r->nama_outlet ?? ''),
                    'review_count' => (int) $r->review_count,
                    'created_at' => $r->created_at,
                    'error_message' => (string) ($r->error_message ?? ''),
                ];
            });

        return response()->json([
            'success' => true,
            'reports' => $reports,
        ]);
    }

    public function aiReportStore(Request $request)
    {
        $placeInput = $request->input('place', []);
        if (is_array($placeInput) && array_key_exists('rating', $placeInput) && $placeInput['rating'] !== null && $placeInput['rating'] !== '') {
            $placeInput['rating'] = (string) $placeInput['rating'];
            $request->merge(['place' => $placeInput]);
        }

        $request->validate([
            'source' => 'required|string|in:apify_dataset,places_api,scraper_inline,manual_db,instagram_comments_db',
            'classification_mode' => 'nullable|string|in:ai,manual',
            'dataset_id' => 'required_if:source,apify_dataset|nullable|string|max:128',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'place_id' => 'nullable|string|max:255',
            'id_outlet' => 'nullable|integer',
            'nama_outlet' => 'nullable|string|max:255',
            'place' => 'nullable|array',
            'place.name' => 'nullable|string|max:512',
            'place.address' => 'nullable|string|max:1024',
            'place.rating' => 'nullable|string|max:64',
            'reviews' => [
                Rule::requiredIf(in_array($request->input('source'), ['places_api', 'scraper_inline'], true)),
                'array',
                'max:2000',
            ],
            'profile_keys' => 'nullable|array',
            'profile_keys.*' => 'string|max:64',
            'manual_review_ids' => 'nullable|array',
            'manual_review_ids.*' => 'integer',
        ]);

        $source = $request->input('source');
        $classificationMode = strtolower(trim((string) $request->input('classification_mode', 'ai')));
        if (! in_array($classificationMode, ['ai', 'manual'], true)) {
            $classificationMode = 'ai';
        }
        $manualOnly = $classificationMode === 'manual';
        $place = $request->input('place', []);
        $payload = null;
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        if (in_array($source, ['places_api', 'scraper_inline'], true)) {
            $reviews = $request->input('reviews', []);
            if (! is_array($reviews) || count($reviews) === 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Daftar review kosong.',
                ], 422);
            }
            // Payload tetap array review murni (dibaca job sebagai list). Mode disimpan di kolom classification_mode.
            $payload = json_encode($reviews, JSON_UNESCAPED_UNICODE);
        } elseif ($source === 'apify_dataset' && ($dateFrom || $dateTo)) {
            $payload = json_encode([
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'classification_mode' => $classificationMode,
            ], JSON_UNESCAPED_UNICODE);
        } elseif ($source === 'instagram_comments_db') {
            $payload = json_encode([
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'profile_keys' => array_values(array_filter((array) $request->input('profile_keys', []))),
                'classification_mode' => $classificationMode,
            ], JSON_UNESCAPED_UNICODE);
        } elseif ($source === 'manual_db') {
            $manualIds = array_values(array_filter(array_map('intval', (array) $request->input('manual_review_ids', []))));
            if ($manualIds === []) {
                return response()->json([
                    'success' => false,
                    'error' => 'Pilih minimal 1 manual review.',
                ], 422);
            }

            $blockedManualReviewIds = $this->getBlockedManualReviewIds();
            $blockedLookup = array_fill_keys($blockedManualReviewIds, true);
            $alreadyClassifiedIds = array_values(array_filter($manualIds, fn ($id) => isset($blockedLookup[(int) $id])));
            if ($alreadyClassifiedIds !== []) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ada review yang sudah/sedang diproses AI dan tidak bisa diklasifikasikan ulang.',
                    'blocked_ids' => $alreadyClassifiedIds,
                ], 422);
            }

            $payload = json_encode([
                'manual_review_ids' => $manualIds,
                'classification_mode' => $classificationMode,
            ], JSON_UNESCAPED_UNICODE);
        } elseif ($source === 'apify_dataset') {
            $payload = json_encode([
                'classification_mode' => $classificationMode,
            ], JSON_UNESCAPED_UNICODE);
        }

        $reportInsert = [
            'user_id' => auth()->id(),
            'status' => 'pending',
            'source' => $source,
            'place_id' => $request->input('place_id'),
            'id_outlet' => $request->input('id_outlet'),
            'nama_outlet' => $request->input('nama_outlet'),
            'dataset_id' => $request->input('dataset_id'),
            'place_name' => $place['name'] ?? null,
            'place_address' => $place['address'] ?? null,
            'place_rating' => isset($place['rating']) ? (string) $place['rating'] : null,
            'review_count' => 0,
            'source_payload' => $payload,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('google_review_ai_reports', 'classification_mode')) {
            $reportInsert['classification_mode'] = $classificationMode;
        }

        $reportId = DB::table('google_review_ai_reports')->insertGetId($reportInsert);

        // Mode MANUAL: langsung materialize item di request ini — tanpa queue/worker/Gemini.
        if ($manualOnly) {
            try {
                $count = $this->materializeManualClassificationReport($reportId);
                \Log::info('GoogleReview MANUAL report materialized inline', [
                    'report_id' => $reportId,
                    'source' => $source,
                    'item_count' => $count,
                    'user_id' => auth()->id(),
                ]);

                return response()->json([
                    'success' => true,
                    'id' => $reportId,
                    'classification_mode' => 'manual',
                    'status' => 'completed',
                    'review_count' => $count,
                    'message' => "Laporan manual siap. {$count} review menunggu input severity/topik di detail (tanpa AI/worker).",
                ]);
            } catch (\Throwable $e) {
                \Log::error('GoogleReview MANUAL materialize failed', [
                    'report_id' => $reportId,
                    'source' => $source,
                    'error' => $e->getMessage(),
                ]);
                DB::table('google_review_ai_reports')->where('id', $reportId)->update([
                    'status' => 'failed',
                    'error_message' => mb_substr($e->getMessage(), 0, 10000),
                    'progress_phase' => 'failed',
                    'updated_at' => now(),
                ]);

                return response()->json([
                    'success' => false,
                    'id' => $reportId,
                    'classification_mode' => 'manual',
                    'error' => $e->getMessage(),
                ], 500);
            }
        }

        $sync = (bool) config('google_review.ai_dispatch_sync', false);
        $queueConnection = (string) config('queue.default', 'sync');
        $queueName = (string) config('google_review.process_queue', 'google-review-ai');
        $classifyProvider = (string) (config('ai.google_review_classify.provider') ?: config('ai.provider'));
        $geminiModel = (string) (config('ai.google_review_classify.gemini_model') ?: config('ai.gemini.model', ''));
        $geminiKeySet = ! empty(config('ai.gemini.api_key'));

        \Log::info('GoogleReview AI report created', [
            'report_id' => $reportId,
            'source' => $source,
            'classification_mode' => 'ai',
            'sync' => $sync,
            'queue_connection' => $queueConnection,
            'queue_name' => $queueName,
            'classify_provider' => $classifyProvider,
            'gemini_model' => $geminiModel,
            'gemini_key_set' => $geminiKeySet,
            'dataset_id' => $request->input('dataset_id'),
            'user_id' => auth()->id(),
        ]);

        try {
            if ($sync) {
                if ($source === 'instagram_comments_db') {
                    ProcessInstagramCommentAiReportJob::dispatchSync($reportId, false);
                } else {
                    ProcessGoogleReviewAiReportJob::dispatchSync($reportId, false);
                }
            } else {
                if ($source === 'instagram_comments_db') {
                    ProcessInstagramCommentAiReportJob::dispatch($reportId, false);
                } else {
                    ProcessGoogleReviewAiReportJob::dispatch($reportId, false);
                }
            }

            \Log::info('GoogleReview AI report dispatched', [
                'report_id' => $reportId,
                'source' => $source,
                'classification_mode' => 'ai',
                'sync' => $sync,
                'queue_connection' => $queueConnection,
                'queue_name' => $queueName,
            ]);
        } catch (\Throwable $e) {
            \Log::error('GoogleReview AI dispatch failed', [
                'report_id' => $reportId,
                'source' => $source,
                'classification_mode' => 'ai',
                'sync' => $sync,
                'queue_connection' => $queueConnection,
                'queue_name' => $queueName,
                'error' => $e->getMessage(),
            ]);

            DB::table('google_review_ai_reports')->where('id', $reportId)->update([
                'status' => 'failed',
                'error_message' => mb_substr('Dispatch gagal: '.$e->getMessage(), 0, 10000),
                'progress_phase' => 'failed',
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => false,
                'id' => $reportId,
                'error' => 'Dispatch job gagal. Cek log server.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'id' => $reportId,
            'classification_mode' => 'ai',
            'message' => $sync
                ? 'Laporan AI diproses langsung (GOOGLE_REVIEW_AI_DISPATCH_SYNC). Refresh halaman detail jika browser sempat timeout.'
                : $this->aiReportQueuedMessage(),
        ]);
    }

    public function aiReportStatus(Request $request, int $id)
    {
        if (! $this->userCanAccessReport($id)) {
            return response()->json(['success' => false, 'error' => 'Tidak diizinkan'], 403);
        }
        $r = DB::table('google_review_ai_reports')->where('id', $id)->first();
        if (! $r) {
            return response()->json(['success' => false, 'error' => 'Tidak ditemukan'], 404);
        }

        $log = [];
        if (! empty($r->progress_log)) {
            $decoded = json_decode($r->progress_log, true);
            $log = is_array($decoded) ? $decoded : [];
        }

        $queueDiag = $this->aiReportQueueDiagnostics($id, $r);

        return response()->json([
            'success' => true,
            'status' => $r->status,
            'review_count' => (int) $r->review_count,
            'error_message' => $r->error_message,
            'raw_review_count' => (int) ($r->raw_review_count ?? 0),
            'dedupe_removed_count' => (int) ($r->dedupe_removed_count ?? 0),
            'progress_total' => (int) ($r->progress_total ?? 0),
            'progress_done' => (int) ($r->progress_done ?? 0),
            'progress_phase' => $r->progress_phase ?? null,
            'progress_log' => $log,
            'queue_connection' => $queueDiag['queue_connection'],
            'process_queue' => $queueDiag['process_queue'],
            'jobs_pending_in_queue' => $queueDiag['jobs_pending_in_queue'],
            'stuck_hint' => $queueDiag['stuck_hint'],
        ]);
    }

    /**
     * Bantu debug laporan AI stuck di "pending" (biasanya worker antrian tidak jalan).
     *
     * @return array{queue_connection: string, process_queue: string, jobs_pending_in_queue: int|null, stuck_hint: string|null}
     */
    protected function aiReportQueueDiagnostics(int $reportId, object $report): array
    {
        $connection = (string) config('queue.default', 'sync');
        $queueName = (string) config('google_review.process_queue', 'google-review-ai');
        $jobsPending = null;
        $stuckHint = null;

        if ($connection === 'database' && Schema::hasTable('jobs')) {
            $jobsPending = (int) DB::table('jobs')->where('queue', $queueName)->count();
        }

        if ($report->status === 'pending') {
            $logEmpty = empty($report->progress_log) || $report->progress_log === '[]';
            $ageSec = $report->created_at
                ? now()->diffInSeconds(\Illuminate\Support\Carbon::parse($report->created_at))
                : 0;
            if ($logEmpty && $ageSec >= 20) {
                $stuckHint = 'Job klasifikasi AI belum dijalankan. Worker antrian '
                    .'(`php artisan queue:work` / Supervisor) harus memproses antrean «'.$queueName.'». '
                    .'Di server: `php artisan google-review:process-ai-report '.$reportId.'` '
                    .'atau set `GOOGLE_REVIEW_AI_DISPATCH_SYNC=true` di .env lalu buat laporan baru.';
            }
        }

        return [
            'queue_connection' => $connection,
            'process_queue' => $queueName,
            'jobs_pending_in_queue' => $jobsPending,
            'stuck_hint' => $stuckHint,
        ];
    }

    public function aiReportShow(Request $request, int $id)
    {
        if (! $this->userCanAccessReport($id)) {
            abort(403);
        }
        $report = DB::table('google_review_ai_reports')->where('id', $id)->first();
        if (! $report) {
            abort(404);
        }

        $severity = $request->get('severity');
        $itemsQuery = DB::table('google_review_ai_items')->where('report_id', $id)->orderBy('sort_order');
        if ($severity !== null && $severity !== '') {
            $itemsQuery->where('severity', $severity);
        }

        $itemsPaginator = $itemsQuery->paginate(100);
        $itemRows = collect($itemsPaginator->items())->map(function ($row) {
            $topics = $row->topics;
            if (is_string($topics)) {
                $topics = json_decode($topics, true) ?? [];
            }
            $impactRaw = property_exists($row, 'impact') ? $row->impact : null;
            if ($impactRaw === null || $impactRaw === '') {
                $impact = [];
            } elseif (is_string($impactRaw)) {
                $impact = json_decode($impactRaw, true) ?: [];
            } else {
                $impact = is_array($impactRaw) ? $impactRaw : [];
            }
            if (! is_array($impact)) {
                $impact = [];
            }

            return [
                'id' => $row->id,
                'sort_order' => (int) $row->sort_order,
                'author' => $row->author,
                'rating' => $row->rating,
                'review_date' => $row->review_date,
                'text' => $row->text,
                'profile_photo' => $row->profile_photo,
                'severity' => $row->severity,
                'topics' => is_array($topics) ? $topics : [],
                'summary_id' => $row->summary_id,
                'follow_up_target' => property_exists($row, 'follow_up_target') ? ($row->follow_up_target !== null ? (string) $row->follow_up_target : null) : null,
                'impact' => array_values(array_filter(array_map('strval', $impact))),
                'source_item_id' => property_exists($row, 'source_item_id') ? (int) ($row->source_item_id ?? 0) : null,
                'source_account' => property_exists($row, 'source_account') ? $row->source_account : null,
                'source_post_url' => property_exists($row, 'source_post_url') ? $row->source_post_url : null,
                'source_post_shortcode' => property_exists($row, 'source_post_shortcode') ? $row->source_post_shortcode : null,
                'source_post_caption' => property_exists($row, 'source_post_caption') ? $row->source_post_caption : null,
                'nama_outlet' => null,
            ];
        });

        // Enrich Instagram rows for old reports that were created before source_* columns existed.
        if ($report->source === 'instagram_comments_db') {
            $hasSourceCols = Schema::hasColumn('google_review_ai_items', 'source_item_id')
                && Schema::hasColumn('google_review_ai_items', 'source_account')
                && Schema::hasColumn('google_review_ai_items', 'source_post_url');

            $idMap = [];
            if ($hasSourceCols) {
                $sourceIds = $itemRows->pluck('source_item_id')
                    ->filter(fn ($v) => is_numeric($v) && (int) $v > 0)
                    ->map(fn ($v) => (int) $v)
                    ->unique()
                    ->values()
                    ->all();
                if ($sourceIds !== []) {
                    $idMap = DB::table('instagram_comments as c')
                        ->join('instagram_posts as p', 'p.id', '=', 'c.instagram_post_id')
                        ->select('c.id', 'p.profile_key', 'p.post_url', 'p.short_code', 'p.caption')
                        ->whereIn('c.id', $sourceIds)
                        ->get()
                        ->keyBy('id')
                        ->all();
                }
            }

            $needsFallback = $itemRows->contains(function ($r) {
                return empty($r['source_account']) && ! empty($r['author']) && ! empty($r['text']);
            });

            $fallbackCandidates = collect();
            if ($needsFallback) {
                $authors = $itemRows->pluck('author')->filter()->unique()->values()->all();
                if ($authors !== []) {
                    $fallbackCandidates = DB::table('instagram_comments as c')
                        ->join('instagram_posts as p', 'p.id', '=', 'c.instagram_post_id')
                        ->select('c.username', 'c.text', 'c.commented_at', 'p.profile_key', 'p.post_url', 'p.short_code', 'p.caption')
                        ->whereIn('c.username', $authors)
                        ->orderByDesc('c.id')
                        ->limit(5000)
                        ->get();
                }
            }

            $itemRows = $itemRows->map(function ($r) use ($idMap, $fallbackCandidates) {
                if (! empty($r['source_account']) && ! empty($r['source_post_url'])) {
                    return $r;
                }

                $sid = (int) ($r['source_item_id'] ?? 0);
                if ($sid > 0 && isset($idMap[$sid])) {
                    $m = $idMap[$sid];
                    $r['source_account'] = $r['source_account'] ?: (string) ($m->profile_key ?? '');
                    $r['source_post_url'] = $r['source_post_url'] ?: (string) ($m->post_url ?? '');
                    $r['source_post_shortcode'] = $r['source_post_shortcode'] ?: (string) ($m->short_code ?? '');
                    $r['source_post_caption'] = $r['source_post_caption'] ?: (string) ($m->caption ?? '');
                    if ($r['rating'] === '' || $r['rating'] === null) {
                        $r['rating'] = (string) ($m->profile_key ?? '');
                    }

                    return $r;
                }

                if ($fallbackCandidates->isNotEmpty()) {
                    $author = trim((string) ($r['author'] ?? ''));
                    $text = trim((string) ($r['text'] ?? ''));
                    if ($author !== '' && $text !== '') {
                        $match = $fallbackCandidates->first(function ($c) use ($author, $text) {
                            return trim((string) $c->username) === $author
                                && trim((string) $c->text) === $text;
                        });
                        if ($match) {
                            $r['source_account'] = $r['source_account'] ?: (string) ($match->profile_key ?? '');
                            $r['source_post_url'] = $r['source_post_url'] ?: (string) ($match->post_url ?? '');
                            $r['source_post_shortcode'] = $r['source_post_shortcode'] ?: (string) ($match->short_code ?? '');
                            $r['source_post_caption'] = $r['source_post_caption'] ?: (string) ($match->caption ?? '');
                            if ($r['rating'] === '' || $r['rating'] === null) {
                                $r['rating'] = (string) ($match->profile_key ?? '');
                            }
                        }
                    }
                }

                return $r;
            });
        }
        if ($report->source === 'manual_db') {
            $hasSourceItemId = Schema::hasColumn('google_review_ai_items', 'source_item_id');
            $idMap = [];
            if ($hasSourceItemId) {
                $sourceIds = $itemRows->pluck('source_item_id')
                    ->filter(fn ($v) => is_numeric($v) && (int) $v > 0)
                    ->map(fn ($v) => (int) $v)
                    ->unique()
                    ->values()
                    ->all();
                if ($sourceIds !== []) {
                    $idMap = DB::table('google_review_manual_reviews')
                        ->select('id', 'nama_outlet')
                        ->whereIn('id', $sourceIds)
                        ->get()
                        ->keyBy('id')
                        ->all();
                }
            }

            $fallbackCandidates = DB::table('google_review_manual_reviews')
                ->select('author', 'review_date', 'text', 'nama_outlet')
                ->orderByDesc('id')
                ->limit(5000)
                ->get();
            $fallbackByAuthorDate = $fallbackCandidates
                ->filter(fn ($r) => trim((string) ($r->author ?? '')) !== '' && trim((string) ($r->review_date ?? '')) !== '')
                ->groupBy(fn ($r) => trim((string) $r->author).'|'.trim((string) $r->review_date))
                ->map(function ($group) {
                    $hit = $group->first(fn ($r) => trim((string) ($r->nama_outlet ?? '')) !== '');
                    return $hit ? (string) $hit->nama_outlet : '';
                });

            $itemRows = $itemRows->map(function ($r) use ($idMap, $fallbackCandidates, $report) {
                $sid = (int) ($r['source_item_id'] ?? 0);
                if ($sid > 0 && isset($idMap[$sid])) {
                    $r['nama_outlet'] = (string) ($idMap[$sid]->nama_outlet ?? '');
                    return $r;
                }

                $author = trim((string) ($r['author'] ?? ''));
                $reviewDate = trim((string) ($r['review_date'] ?? ''));
                $text = trim((string) ($r['text'] ?? ''));
                if ($author !== '' && $text !== '') {
                    $match = $fallbackCandidates->first(function ($c) use ($author, $reviewDate, $text) {
                        return trim((string) $c->author) === $author
                            && trim((string) $c->review_date) === $reviewDate
                            && trim((string) $c->text) === $text;
                    });
                    if ($match) {
                        $r['nama_outlet'] = (string) ($match->nama_outlet ?? '');
                        return $r;
                    }
                }

                $r['nama_outlet'] = (string) ($report->nama_outlet ?? '');
                return $r;
            });
        }
        $itemsPaginator->setCollection($itemRows->values());
        $items = $itemsPaginator;

        $initialLog = [];
        if (! empty($report->progress_log)) {
            $decoded = json_decode($report->progress_log, true);
            $initialLog = is_array($decoded) ? $decoded : [];
        }

        $queueDiag = $this->aiReportQueueDiagnostics($id, $report);

        return Inertia::render('google-review/AiReportShow', [
            'report' => [
                'id' => $report->id,
                'status' => $report->status,
                'source' => $report->source,
                'classification_mode' => property_exists($report, 'classification_mode')
                    ? (string) ($report->classification_mode ?? 'ai')
                    : 'ai',
                'place_id' => $report->place_id,
                'nama_outlet' => $report->nama_outlet,
                'place_name' => $report->place_name,
                'place_address' => $report->place_address,
                'place_rating' => $report->place_rating,
                'dataset_id' => $report->dataset_id,
                'review_count' => (int) $report->review_count,
                'error_message' => $report->error_message,
                'created_at' => $report->created_at,
                'raw_review_count' => (int) ($report->raw_review_count ?? 0),
                'dedupe_removed_count' => (int) ($report->dedupe_removed_count ?? 0),
                'progress_total' => (int) ($report->progress_total ?? 0),
                'progress_done' => (int) ($report->progress_done ?? 0),
                'progress_phase' => $report->progress_phase ?? null,
                'progress_log' => $initialLog,
            ],
            'items' => $items,
            'filters' => [
                'severity' => $severity ?? '',
            ],
            'queueDiagnostics' => $queueDiag,
        ]);
    }

    public function apiAiReportShow(Request $request, int $id)
    {
        if (! $this->userCanAccessReport($id)) {
            return response()->json(['success' => false, 'error' => 'Tidak diizinkan'], 403);
        }

        $report = DB::table('google_review_ai_reports')->where('id', $id)->first();
        if (! $report) {
            return response()->json(['success' => false, 'error' => 'Tidak ditemukan'], 404);
        }

        $severity = trim((string) ($request->query('severity') ?? ''));
        $perPage = (int) ($request->query('per_page') ?? 50);
        $perPage = max(1, min(200, $perPage));

        $itemsQuery = DB::table('google_review_ai_items')
            ->where('report_id', $id)
            ->orderBy('sort_order');

        if ($severity !== '') {
            $itemsQuery->where('severity', $severity);
        }

        $items = $itemsQuery
            ->paginate($perPage)
            ->through(function ($row) {
                $topics = $row->topics;
                if (is_string($topics)) {
                    $topics = json_decode($topics, true) ?? [];
                }
                $impactRaw = property_exists($row, 'impact') ? $row->impact : null;
                if ($impactRaw === null || $impactRaw === '') {
                    $impact = [];
                } elseif (is_string($impactRaw)) {
                    $impact = json_decode($impactRaw, true) ?: [];
                } else {
                    $impact = is_array($impactRaw) ? $impactRaw : [];
                }
                if (! is_array($impact)) {
                    $impact = [];
                }

                return [
                    'id' => (int) $row->id,
                    'sort_order' => (int) $row->sort_order,
                    'author' => (string) ($row->author ?? ''),
                    'rating' => $row->rating,
                    'review_date' => (string) ($row->review_date ?? ''),
                    'text' => (string) ($row->text ?? ''),
                    'profile_photo' => (string) ($row->profile_photo ?? ''),
                    'severity' => (string) ($row->severity ?? ''),
                    'topics' => is_array($topics) ? $topics : [],
                    'summary_id' => (string) ($row->summary_id ?? ''),
                    'follow_up_target' => property_exists($row, 'follow_up_target') ? ($row->follow_up_target !== null ? (string) $row->follow_up_target : null) : null,
                    'impact' => array_values(array_filter(array_map('strval', $impact))),
                    'source_item_id' => property_exists($row, 'source_item_id') ? (int) ($row->source_item_id ?? 0) : null,
                    'source_account' => property_exists($row, 'source_account') ? (string) ($row->source_account ?? '') : '',
                    'source_post_url' => property_exists($row, 'source_post_url') ? (string) ($row->source_post_url ?? '') : '',
                    'source_post_shortcode' => property_exists($row, 'source_post_shortcode') ? (string) ($row->source_post_shortcode ?? '') : '',
                    'source_post_caption' => property_exists($row, 'source_post_caption') ? (string) ($row->source_post_caption ?? '') : '',
                ];
            });

        $severityCounts = DB::table('google_review_ai_items')
            ->select('severity', DB::raw('COUNT(*) as total'))
            ->where('report_id', $id)
            ->groupBy('severity')
            ->pluck('total', 'severity');
        $sc = function (string $k) use ($severityCounts): int {
            return (int) ($severityCounts[$k] ?? 0);
        };

        $initialLog = [];
        if (! empty($report->progress_log)) {
            $decoded = json_decode($report->progress_log, true);
            $initialLog = is_array($decoded) ? $decoded : [];
        }

        return response()->json([
            'success' => true,
            'report' => [
                'id' => (int) $report->id,
                'status' => (string) $report->status,
                'source' => (string) ($report->source ?? ''),
                'classification_mode' => property_exists($report, 'classification_mode')
                    ? (string) ($report->classification_mode ?? 'ai')
                    : 'ai',
                'place_id' => (string) ($report->place_id ?? ''),
                'nama_outlet' => (string) ($report->nama_outlet ?? ''),
                'place_name' => (string) ($report->place_name ?? ''),
                'place_address' => (string) ($report->place_address ?? ''),
                'place_rating' => (string) ($report->place_rating ?? ''),
                'dataset_id' => (string) ($report->dataset_id ?? ''),
                'review_count' => (int) $report->review_count,
                'error_message' => (string) ($report->error_message ?? ''),
                'created_at' => $report->created_at,
                'raw_review_count' => (int) ($report->raw_review_count ?? 0),
                'dedupe_removed_count' => (int) ($report->dedupe_removed_count ?? 0),
                'progress_total' => (int) ($report->progress_total ?? 0),
                'progress_done' => (int) ($report->progress_done ?? 0),
                'progress_phase' => $report->progress_phase ?? null,
                'progress_log' => $initialLog,
            ],
            'severity_counts' => [
                'positive' => $sc('positive'),
                'neutral' => $sc('neutral'),
                'minor' => $sc('minor') + $sc('mild_negative'),
                'major' => $sc('major') + $sc('negative'),
                'critical' => $sc('critical') + $sc('severe'),
            ],
            'items' => $items,
            'filters' => [
                'severity' => $severity,
            ],
        ]);
    }

    public function aiReportExport(Request $request, int $id)
    {
        if (! $this->userCanAccessReport($id)) {
            abort(403);
        }
        $report = DB::table('google_review_ai_reports')->where('id', $id)->first();
        if (! $report || $report->status !== 'completed') {
            abort(404, 'Laporan belum selesai atau tidak ada.');
        }

        $rows = DB::table('google_review_ai_items')
            ->where('report_id', $id)
            ->orderBy('sort_order')
            ->get();

        if ($report->source === 'manual_db') {
            $hasSourceItemId = Schema::hasColumn('google_review_ai_items', 'source_item_id');
            $idMap = [];
            if ($hasSourceItemId) {
                $sourceIds = $rows->pluck('source_item_id')
                    ->filter(fn ($v) => is_numeric($v) && (int) $v > 0)
                    ->map(fn ($v) => (int) $v)
                    ->unique()
                    ->values()
                    ->all();
                if ($sourceIds !== []) {
                    $idMap = DB::table('google_review_manual_reviews')
                        ->select('id', 'nama_outlet')
                        ->whereIn('id', $sourceIds)
                        ->get()
                        ->keyBy('id')
                        ->all();
                }
            }

            $fallbackCandidates = DB::table('google_review_manual_reviews')
                ->select('author', 'review_date', 'text', 'nama_outlet')
                ->orderByDesc('id')
                ->limit(5000)
                ->get();
            $fallbackByAuthorDate = $fallbackCandidates
                ->filter(fn ($r) => trim((string) ($r->author ?? '')) !== '' && trim((string) ($r->review_date ?? '')) !== '')
                ->groupBy(fn ($r) => trim((string) $r->author).'|'.trim((string) $r->review_date))
                ->map(function ($group) {
                    $hit = $group->first(fn ($r) => trim((string) ($r->nama_outlet ?? '')) !== '');
                    return $hit ? (string) $hit->nama_outlet : '';
                });

            $rows = $rows->map(function ($row) use ($idMap, $fallbackCandidates, $fallbackByAuthorDate, $report) {
                $sid = property_exists($row, 'source_item_id') ? (int) ($row->source_item_id ?? 0) : 0;
                if ($sid > 0 && isset($idMap[$sid])) {
                    $row->nama_outlet = (string) ($idMap[$sid]->nama_outlet ?? '');
                    return $row;
                }

                $author = trim((string) ($row->author ?? ''));
                $reviewDate = trim((string) ($row->review_date ?? ''));
                $text = trim((string) ($row->text ?? ''));
                if ($author !== '' && $text !== '') {
                    $match = $fallbackCandidates->first(function ($c) use ($author, $reviewDate, $text) {
                        return trim((string) $c->author) === $author
                            && trim((string) $c->review_date) === $reviewDate
                            && trim((string) $c->text) === $text;
                    });
                    if ($match) {
                        $row->nama_outlet = (string) ($match->nama_outlet ?? '');
                        return $row;
                    }
                }

                $authorDateKey = $author.'|'.$reviewDate;
                if ($author !== '' && $reviewDate !== '' && ! empty($fallbackByAuthorDate[$authorDateKey])) {
                    $row->nama_outlet = (string) $fallbackByAuthorDate[$authorDateKey];
                    return $row;
                }

                $row->nama_outlet = (string) ($report->nama_outlet ?? '');
                return $row;
            });
        }

        $filename = 'google-review-ai-' . $id . '-' . date('Ymd-His') . '.xlsx';

        return Excel::download(new GoogleReviewAiReportExport($rows, $report->nama_outlet), $filename);
    }

    /**
     * Update klasifikasi item (manual override): severity, topics, follow_up, impact, summary.
     * Route lama /severity tetap didukung.
     */
    public function updateItemSeverity(Request $request, int $itemId)
    {
        return $this->updateItemClassification($request, $itemId);
    }

    public function updateItemClassification(Request $request, int $itemId)
    {
        $allowedSeverity = ['positive', 'neutral', 'minor', 'major', 'critical'];
        $allowedTopics = [
            'food_quality', 'service', 'hygiene', 'ambiance', 'price', 'wait_time',
            'parking', 'portion', 'noise', 'reservation', 'other',
        ];
        $allowedImpact = ['reputasi', 'finansial', 'operasional'];

        $request->validate([
            'severity' => 'nullable|in:'.implode(',', $allowedSeverity),
            'topics' => 'nullable|array|max:5',
            'topics.*' => 'string|in:'.implode(',', $allowedTopics),
            'follow_up_target' => 'nullable|in:customer,internal',
            'impact' => 'nullable|array',
            'impact.*' => 'string|in:'.implode(',', $allowedImpact),
            'summary_id' => 'nullable|string|max:500',
        ]);

        if (
            ! $request->filled('severity')
            && ! $request->has('topics')
            && ! $request->has('follow_up_target')
            && ! $request->has('impact')
            && ! $request->has('summary_id')
        ) {
            return response()->json([
                'success' => false,
                'error' => 'Tidak ada field klasifikasi yang dikirim.',
            ], 422);
        }

        $item = DB::table('google_review_ai_items')->where('id', $itemId)->first();
        if (! $item) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'error' => 'Item tidak ditemukan'], 404);
            }
            abort(404);
        }

        if (! $this->userCanAccessReport($item->report_id)) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'error' => 'Tidak diizinkan'], 403);
            }
            abort(403);
        }

        $update = ['updated_at' => now()];
        if ($request->filled('severity')) {
            $update['severity'] = $request->input('severity');
        }
        if ($request->has('topics')) {
            $topics = array_values(array_unique(array_filter((array) $request->input('topics', []))));
            $update['topics'] = json_encode($topics, JSON_UNESCAPED_UNICODE);
        }
        if ($request->has('summary_id')) {
            $update['summary_id'] = mb_substr(trim((string) $request->input('summary_id')), 0, 500);
        }
        if (Schema::hasColumn('google_review_ai_items', 'follow_up_target')) {
            if ($request->has('follow_up_target')) {
                $fu = trim((string) $request->input('follow_up_target', ''));
                $update['follow_up_target'] = $fu === '' ? null : $fu;
            }
            if ($request->has('impact')) {
                $impact = array_values(array_unique(array_filter((array) $request->input('impact', []))));
                $update['impact'] = json_encode($impact, JSON_UNESCAPED_UNICODE);
            }
        }

        DB::table('google_review_ai_items')->where('id', $itemId)->update($update);

        $fresh = DB::table('google_review_ai_items')->where('id', $itemId)->first();
        $topicsOut = [];
        if (! empty($fresh->topics)) {
            $decoded = is_string($fresh->topics) ? json_decode($fresh->topics, true) : $fresh->topics;
            $topicsOut = is_array($decoded) ? $decoded : [];
        }
        $impactOut = [];
        if (isset($fresh->impact) && $fresh->impact !== null && $fresh->impact !== '') {
            $decodedImp = is_string($fresh->impact) ? json_decode($fresh->impact, true) : $fresh->impact;
            $impactOut = is_array($decodedImp) ? $decodedImp : [];
        }

        return response()->json([
            'success' => true,
            'message' => 'Klasifikasi berhasil disimpan',
            'item' => [
                'id' => (int) $fresh->id,
                'severity' => $fresh->severity,
                'topics' => $topicsOut,
                'summary_id' => $fresh->summary_id,
                'follow_up_target' => $fresh->follow_up_target ?? null,
                'impact' => $impactOut,
            ],
        ]);
    }

    /**
     * Materialize laporan MANUAL di request HTTP (tanpa queue/worker/Gemini).
     * Item dibuat dengan severity awal dari rating; user melengkapi di UI detail.
     */
    private function materializeManualClassificationReport(int $reportId): int
    {
        $report = DB::table('google_review_ai_reports')->where('id', $reportId)->first();
        if (! $report) {
            throw new \RuntimeException('Laporan tidak ditemukan.');
        }

        $log = [[
            't' => now()->format('Y-m-d H:i:s'),
            'm' => 'Klasifikasi MANUAL langsung (tanpa worker/AI).',
        ]];

        $reviews = $this->collectReviewsForManualMaterialize($report);
        $rawCount = count($reviews);
        $log[] = [
            't' => now()->format('Y-m-d H:i:s'),
            'm' => "Review dimuat: {$rawCount} baris.",
        ];

        if ($report->source !== 'instagram_comments_db') {
            $deduped = GoogleReviewDeduper::dedupe($reviews);
            $reviews = $deduped['reviews'];
            $removed = (int) ($deduped['removed'] ?? 0);
            $log[] = [
                't' => now()->format('Y-m-d H:i:s'),
                'm' => $removed > 0
                    ? "Deduplikasi: {$removed} duplikat diabaikan ({$rawCount} → ".count($reviews).' unik).'
                    : 'Deduplikasi: tidak ada duplikat.',
            ];
        } else {
            $removed = 0;
        }

        if (count($reviews) === 0) {
            throw new \RuntimeException('Tidak ada review untuk diklasifikasi manual.');
        }

        $hasSourceItemId = Schema::hasColumn('google_review_ai_items', 'source_item_id');
        $hasSourceAccount = Schema::hasColumn('google_review_ai_items', 'source_account');
        $hasSourcePostUrl = Schema::hasColumn('google_review_ai_items', 'source_post_url');
        $hasSourcePostShortcode = Schema::hasColumn('google_review_ai_items', 'source_post_shortcode');
        $hasFuImpact = Schema::hasColumn('google_review_ai_items', 'follow_up_target');

        DB::table('google_review_ai_items')->where('report_id', $reportId)->delete();

        $batch = [];
        $now = now();
        foreach (array_values($reviews) as $idx => $row) {
            $row = is_array($row) ? $row : (array) $row;
            $severity = $this->defaultSeverityFromRating($row['rating'] ?? null);
            $text = trim((string) ($row['text'] ?? ''));
            $summary = $text === '' ? '' : mb_substr($text, 0, 180);
            $insert = [
                'report_id' => $reportId,
                'sort_order' => $idx,
                'author' => mb_substr((string) ($row['author'] ?? ''), 0, 255),
                'rating' => mb_substr((string) ($row['rating'] ?? ''), 0, 32),
                'review_date' => mb_substr((string) ($row['date'] ?? ''), 0, 255),
                'text' => $row['text'] ?? null,
                'profile_photo' => mb_substr((string) ($row['profile_photo'] ?? ''), 0, 1024),
                'severity' => $severity,
                'topics' => json_encode([]),
                'summary_id' => mb_substr($summary, 0, 500),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($hasFuImpact) {
                $insert['follow_up_target'] = in_array($severity, ['minor', 'major', 'critical'], true) ? 'internal' : null;
                $insert['impact'] = json_encode([]);
            }
            if ($hasSourceItemId) {
                $insert['source_item_id'] = (int) ($row['source_item_id'] ?? $row['_source_item_id'] ?? 0);
            }
            if ($hasSourceAccount) {
                $insert['source_account'] = mb_substr((string) ($row['_source_account'] ?? ''), 0, 64);
            }
            if ($hasSourcePostUrl) {
                $insert['source_post_url'] = mb_substr((string) ($row['_source_post_url'] ?? ''), 0, 512);
            }
            if ($hasSourcePostShortcode) {
                $insert['source_post_shortcode'] = mb_substr((string) ($row['_source_post_shortcode'] ?? ''), 0, 32);
            }
            $batch[] = $insert;
            if (count($batch) >= 150) {
                DB::table('google_review_ai_items')->insert($batch);
                $batch = [];
            }
        }
        if ($batch !== []) {
            DB::table('google_review_ai_items')->insert($batch);
        }

        $finalCount = count($reviews);
        $log[] = [
            't' => now()->format('Y-m-d H:i:s'),
            'm' => "Selesai (MANUAL). {$finalCount} review siap diisi severity/topik di detail.",
        ];

        $update = [
            'status' => 'completed',
            'review_count' => $finalCount,
            'raw_review_count' => $rawCount,
            'dedupe_removed_count' => $removed,
            'error_message' => null,
            'progress_phase' => 'manual_completed',
            'progress_total' => max(1, $finalCount),
            'progress_done' => $finalCount,
            'progress_log' => json_encode($log, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ];
        // Jangan hapus source_payload manual_db agar blocked-ids tetap bekerja.
        if ($report->source !== 'manual_db') {
            $update['source_payload'] = null;
        }

        DB::table('google_review_ai_reports')->where('id', $reportId)->update($update);

        return $finalCount;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectReviewsForManualMaterialize(object $report): array
    {
        if ($report->source === 'manual_db') {
            $meta = [];
            if (! empty($report->source_payload)) {
                $decoded = json_decode((string) $report->source_payload, true);
                $meta = is_array($decoded) ? $decoded : [];
            }
            $ids = array_values(array_filter(array_map('intval', (array) ($meta['manual_review_ids'] ?? []))));
            if ($ids === []) {
                throw new \RuntimeException('Manual review IDs kosong.');
            }

            return DB::table('google_review_manual_reviews')
                ->whereIn('id', $ids)
                ->where('is_active', 1)
                ->orderBy('id')
                ->get()
                ->map(fn ($row) => [
                    'source_item_id' => (int) ($row->id ?? 0),
                    'author' => (string) ($row->author ?? ''),
                    'rating' => (string) ($row->rating ?? ''),
                    'date' => (string) ($row->review_date ?? ''),
                    'text' => (string) ($row->text ?? ''),
                    'profile_photo' => (string) ($row->profile_photo ?? ''),
                ])
                ->values()
                ->all();
        }

        if ($report->source === 'apify_dataset') {
            if (empty($report->dataset_id)) {
                throw new \RuntimeException('dataset_id kosong.');
            }
            $meta = [];
            if (! empty($report->source_payload)) {
                $decoded = json_decode((string) $report->source_payload, true);
                $meta = is_array($decoded) ? $decoded : [];
            }
            $dateFrom = isset($meta['date_from']) ? (string) $meta['date_from'] : null;
            $dateTo = isset($meta['date_to']) ? (string) $meta['date_to'] : null;

            return array_values($this->apifyService->getAllReviewsFromDataset(
                (string) $report->dataset_id,
                null,
                $dateFrom,
                $dateTo
            ));
        }

        if ($report->source === 'instagram_comments_db') {
            return $this->collectInstagramCommentsForManual($report);
        }

        // places_api / scraper_inline — payload = list review
        if (empty($report->source_payload)) {
            throw new \RuntimeException('Payload review kosong.');
        }
        $reviews = json_decode((string) $report->source_payload, true);
        if (! is_array($reviews)) {
            throw new \RuntimeException('Payload review tidak valid.');
        }

        return array_values($reviews);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectInstagramCommentsForManual(object $report): array
    {
        $meta = [];
        if (! empty($report->source_payload)) {
            $decoded = json_decode((string) $report->source_payload, true);
            $meta = is_array($decoded) ? $decoded : [];
        }
        $dateFrom = isset($meta['date_from']) ? (string) $meta['date_from'] : null;
        $dateTo = isset($meta['date_to']) ? (string) $meta['date_to'] : null;
        $profileKeys = array_values(array_filter((array) ($meta['profile_keys'] ?? [])));

        $q = DB::table('instagram_comments')
            ->join('instagram_posts', 'instagram_posts.id', '=', 'instagram_comments.instagram_post_id')
            ->select([
                'instagram_comments.id as comment_id',
                'instagram_comments.username',
                'instagram_comments.text',
                'instagram_comments.commented_at',
                'instagram_posts.profile_key',
                'instagram_posts.post_url',
                'instagram_posts.short_code',
            ])
            ->whereRaw("TRIM(COALESCE(instagram_comments.text, '')) <> ''");

        if ($profileKeys !== []) {
            $q->whereIn('instagram_posts.profile_key', $profileKeys);
        }
        if ($dateFrom) {
            $q->whereDate('instagram_comments.commented_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $q->whereDate('instagram_comments.commented_at', '<=', $dateTo);
        }

        return $q->orderBy('instagram_comments.id')->get()->map(function ($r) {
            return [
                'author' => (string) ($r->username ?? ''),
                'rating' => '',
                'date' => (string) ($r->commented_at ?? ''),
                'text' => (string) ($r->text ?? ''),
                'profile_photo' => '',
                '_source_item_id' => (int) ($r->comment_id ?? 0),
                '_source_account' => (string) ($r->profile_key ?? ''),
                '_source_post_url' => (string) ($r->post_url ?? ''),
                '_source_post_shortcode' => (string) ($r->short_code ?? ''),
            ];
        })->values()->all();
    }

    private function defaultSeverityFromRating(mixed $rating): string
    {
        $raw = trim((string) $rating);
        if ($raw === '') {
            return 'neutral';
        }
        if (preg_match('/(\d+(?:[.,]\d+)?)/', $raw, $m)) {
            $n = (float) str_replace(',', '.', $m[1]);
            if ($n >= 4) {
                return 'positive';
            }
            if ($n >= 3) {
                return 'neutral';
            }
            if ($n >= 2) {
                return 'minor';
            }

            return 'major';
        }

        return 'neutral';
    }

    /**
     * Gabungkan label severity lama (mild_negative, …) ke bucket dashboard baru.
     */
    protected function normalizeAiSeverityBucket(string $s): string
    {
        $x = strtolower(trim($s));

        return match ($x) {
            'mild_negative' => 'minor',
            'negative' => 'major',
            'severe' => 'critical',
            default => $x,
        };
    }
} 