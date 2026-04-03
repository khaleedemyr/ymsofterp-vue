<?php

namespace App\Http\Controllers;

use App\Exports\GoogleReviewAiReportExport;
use App\Jobs\ProcessGoogleReviewAiReportJob;
use App\Services\ApifyGoogleReviewsService;
use App\Services\GooglePlacesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class GoogleReviewController extends Controller
{
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

        return inertia('google-review/Index', [
            'outlets' => $outlets,
        ]);
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
        ]);

        \Log::info('GoogleReviewController@scrapeReviewsApify', [
            'place_id' => $request->input('place_id'),
            'max_reviews' => $request->input('max_reviews'),
            'is_inertia' => $request->hasHeader('X-Inertia'),
        ]);

        try {
            $placeId = $request->input('place_id');
            $maxReviews = (int)($request->input('max_reviews') ?? 200);

            $started = $this->apifyService->startScrapeByPlaceId($placeId, $maxReviews, 'newest');

            $userId = optional($request->user())->id ?? 'guest';
            $cacheKey = $this->apifyCacheKey($userId, $placeId);
            Cache::put($cacheKey, [
                'datasetId' => $started['datasetId'],
                'placeId' => $placeId,
                'place' => $started['place'],
                'itemCount' => $started['itemCount'],
            ], now()->addHours(6));

            $payload = [
                'success' => true,
                'place_id' => $placeId,
                'dataset_id' => $started['datasetId'],
                'place' => $started['place'],
                'item_count' => $started['itemCount'],
                'max_reviews' => $maxReviews,
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
        ]);

        try {
            $datasetId = $request->input('dataset_id');
            $page = (int)($request->input('page') ?? 1);
            $perPage = (int)($request->input('per_page') ?? 20);

            $data = $this->apifyService->getReviewsPageFromDataset($datasetId, $page, $perPage);
            return response()->json([
                'success' => true,
                'reviews' => $data['reviews'],
                'meta' => $data['meta'],
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
        ]);

        $datasetId = $request->input('dataset_id');
        $filename = 'google-reviews-' . date('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($datasetId) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            $this->apifyService->exportDatasetReviewsToCsv($datasetId, function (array $row) use ($out) {
                fputcsv($out, $row);
            });

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

    protected function userIsSuperadmin($user): bool
    {
        return $user && $user->id_role === '5af56935b011a' && $user->status === 'A';
    }

    protected function userCanAccessReport(int $reportId): bool
    {
        $report = DB::table('google_review_ai_reports')->where('id', $reportId)->first();
        if (! $report) {
            return false;
        }
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        if ($this->userIsSuperadmin($user)) {
            return true;
        }

        return (int) $report->user_id === (int) $user->id;
    }

    public function aiReportsIndex(Request $request)
    {
        $user = auth()->user();
        $q = DB::table('google_review_ai_reports')->orderByDesc('id');
        if (! $this->userIsSuperadmin($user)) {
            $q->where('user_id', $user->id);
        }
        $reports = $q->paginate(20)->through(function ($r) {
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

    public function aiReportStore(Request $request)
    {
        $placeInput = $request->input('place', []);
        if (is_array($placeInput) && array_key_exists('rating', $placeInput) && $placeInput['rating'] !== null && $placeInput['rating'] !== '') {
            $placeInput['rating'] = (string) $placeInput['rating'];
            $request->merge(['place' => $placeInput]);
        }

        $request->validate([
            'source' => 'required|string|in:apify_dataset,places_api,scraper_inline',
            'dataset_id' => 'required_if:source,apify_dataset|nullable|string|max:128',
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
        ]);

        $source = $request->input('source');
        $place = $request->input('place', []);
        $payload = null;
        if (in_array($source, ['places_api', 'scraper_inline'], true)) {
            $reviews = $request->input('reviews', []);
            if (! is_array($reviews) || count($reviews) === 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Daftar review kosong.',
                ], 422);
            }
            $payload = json_encode($reviews, JSON_UNESCAPED_UNICODE);
        }

        $reportId = DB::table('google_review_ai_reports')->insertGetId([
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
        ]);

        $sync = (bool) config('google_review.ai_dispatch_sync', false);
        if ($sync) {
            ProcessGoogleReviewAiReportJob::dispatchSync($reportId);
        } else {
            ProcessGoogleReviewAiReportJob::dispatch($reportId);
        }

        return response()->json([
            'success' => true,
            'id' => $reportId,
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
        ]);
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

        $items = $itemsQuery->paginate(100)->through(function ($row) {
            $topics = $row->topics;
            if (is_string($topics)) {
                $topics = json_decode($topics, true) ?? [];
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
            ];
        });

        $initialLog = [];
        if (! empty($report->progress_log)) {
            $decoded = json_decode($report->progress_log, true);
            $initialLog = is_array($decoded) ? $decoded : [];
        }

        return Inertia::render('google-review/AiReportShow', [
            'report' => [
                'id' => $report->id,
                'status' => $report->status,
                'source' => $report->source,
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

        $filename = 'google-review-ai-' . $id . '-' . date('Ymd-His') . '.xlsx';

        return Excel::download(new GoogleReviewAiReportExport($rows), $filename);
    }
} 