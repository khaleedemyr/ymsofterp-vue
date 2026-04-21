<?php

namespace App\Http\Controllers;

use App\Models\GuestCommentForm;
use App\Models\Outlet;
use App\Services\AIAnalyticsService;
use App\Services\GuestCommentOcrService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Guest Comment (OCR) — API untuk Approval App (Bearer), selaras GuestCommentFormController web.
 */
class GuestCommentFormApiController extends Controller
{
    private const RATINGS = ['poor', 'average', 'good', 'excellent'];
    private const GSI_SUBJECT_COLUMNS = [
        'rating_service' => 'Service',
        'rating_food' => 'Food',
        'rating_beverage' => 'Beverage',
        'rating_cleanliness' => 'Cleanliness',
        'rating_staff' => 'Staff',
        'rating_value' => 'Value for Money',
    ];

    private function authorizeGuestCommentFormAccess(Request $request, GuestCommentForm $form): void
    {
        $userOutletId = (int) ($request->user()->id_outlet ?? 0);
        if ($userOutletId === 1) {
            return;
        }
        if ($userOutletId <= 0) {
            abort(403, 'Akun tidak memiliki outlet.');
        }
        $formOutletId = $form->id_outlet !== null ? (int) $form->id_outlet : null;
        if ($formOutletId === $userOutletId) {
            return;
        }
        if ($formOutletId === null && (int) $form->created_by === (int) $request->user()->id) {
            return;
        }
        abort(403, 'Anda tidak dapat mengakses data guest comment ini.');
    }

    private function serializeForm(GuestCommentForm $form): array
    {
        $form->loadMissing([
            'creator:id,nama_lengkap,avatar',
            'verifier:id,nama_lengkap,avatar',
            'outlet:id_outlet,nama_outlet',
        ]);
        $arr = $form->toArray();
        $arr['image_url'] = $form->image_path
            ? Storage::disk('public')->url($form->image_path)
            : null;

        return $arr;
    }

    public function meta(Request $request): JsonResponse
    {
        $userOutletId = (int) ($request->user()->id_outlet ?? 0);
        $canChooseOutlet = ($userOutletId === 1);

        $outlets = $canChooseOutlet
            ? Outlet::where('status', 'A')->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet'])
            : collect();

        $lockedOutlet = null;
        if (! $canChooseOutlet && $userOutletId > 0) {
            $lockedOutlet = Outlet::where('id_outlet', $userOutletId)->where('status', 'A')->first(['id_outlet', 'nama_outlet']);
        }

        return response()->json([
            'success' => true,
            'rating_options' => self::RATINGS,
            'can_choose_outlet' => $canChooseOutlet,
            'outlets' => $outlets,
            'locked_outlet' => $lockedOutlet,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $userOutletId = (int) ($request->user()->id_outlet ?? 0);
        $canChooseOutlet = ($userOutletId === 1);

        $query = GuestCommentForm::query()
            ->with([
                'creator:id,nama_lengkap,avatar',
                'verifier:id,nama_lengkap,avatar',
                'outlet:id_outlet,nama_outlet',
            ])
            ->orderByDesc('created_at');

        if ($canChooseOutlet) {
            if ($request->filled('id_outlet')) {
                $idOutlet = (int) $request->id_outlet;
                if ($idOutlet > 0) {
                    $query->where('guest_comment_forms.id_outlet', $idOutlet);
                }
            }
        } elseif ($userOutletId > 0) {
            $uid = (int) $request->user()->id;
            $query->where(function ($q) use ($userOutletId, $uid) {
                $q->where('guest_comment_forms.id_outlet', $userOutletId)
                    ->orWhere(function ($q2) use ($uid) {
                        $q2->whereNull('guest_comment_forms.id_outlet')
                            ->where('guest_comment_forms.created_by', $uid);
                    });
            });
        } else {
            $query->whereRaw('0 = 1');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('guest_comment_forms.created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('guest_comment_forms.created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $s = '%'.$request->search.'%';
            $query->where(function ($q) use ($s) {
                $q->where('guest_name', 'like', $s)
                    ->orWhere('guest_phone', 'like', $s)
                    ->orWhere('comment_text', 'like', $s)
                    ->orWhere('marketing_source', 'like', $s)
                    ->orWhere('status', 'like', $s);
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min(50, max(1, (int) $request->get('per_page', 15)));
        $forms = $query->paginate($perPage)->withQueryString();
        $forms->getCollection()->transform(fn (GuestCommentForm $f) => $this->serializeForm($f));

        $outlets = $canChooseOutlet
            ? Outlet::where('status', 'A')->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet'])
            : collect();

        $lockedOutlet = null;
        if (! $canChooseOutlet && $userOutletId > 0) {
            $lockedOutlet = Outlet::where('id_outlet', $userOutletId)->where('status', 'A')->first(['id_outlet', 'nama_outlet']);
        }

        return response()->json([
            'success' => true,
            'forms' => $forms,
            'can_choose_outlet' => $canChooseOutlet,
            'outlets' => $outlets,
            'locked_outlet' => $lockedOutlet,
        ]);
    }

    public function gsiDashboard(Request $request): JsonResponse
    {
        $userOutletId = (int) ($request->user()->id_outlet ?? 0);
        $canChooseOutlet = ($userOutletId === 1);

        $month = trim((string) $request->get('month', now()->format('Y-m')));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }
        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $lastStart = $monthStart->copy()->subMonth()->startOfMonth();
        $lastEnd = $monthStart->copy()->subMonth()->endOfMonth();

        $selectedOutletId = 0;
        if ($canChooseOutlet) {
            $selectedOutletId = (int) $request->get('id_outlet', 0);
        } elseif ($userOutletId > 0) {
            $selectedOutletId = $userOutletId;
        }

        $current = $this->buildGsiSubjectStats(self::GSI_SUBJECT_COLUMNS, $monthStart, $monthEnd, $selectedOutletId, $canChooseOutlet, $userOutletId);
        $last = $this->buildGsiSubjectStats(self::GSI_SUBJECT_COLUMNS, $lastStart, $lastEnd, $selectedOutletId, $canChooseOutlet, $userOutletId);

        $rows = [];
        foreach (self::GSI_SUBJECT_COLUMNS as $column => $label) {
            $c = $current['subjects'][$column] ?? [];
            $p = $last['subjects'][$column] ?? [];
            $rows[] = [
                'subject' => $label,
                'excellent' => (int) ($c['excellent'] ?? 0),
                'good' => (int) ($c['good'] ?? 0),
                'average' => (int) ($c['average'] ?? 0),
                'poor' => (int) ($c['poor'] ?? 0),
                'abstain' => (int) ($c['abstain'] ?? 0),
                'total_responses' => (int) ($c['responses'] ?? 0),
                'mtd_pct' => $c['mtd_pct'] ?? null,
                'last_month_pct' => $p['mtd_pct'] ?? null,
            ];
        }

        $overallCurrent = $current['overall_pct'];
        $overallLast = $last['overall_pct'];
        $overallDelta = ($overallCurrent !== null && $overallLast !== null)
            ? round($overallCurrent - $overallLast, 2)
            : null;

        $issueInsights = $this->buildGsiIssueInsights($monthStart, $monthEnd, $selectedOutletId, $canChooseOutlet, $userOutletId);

        $outlets = $canChooseOutlet
            ? Outlet::where('status', 'A')->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet'])
            : collect();
        $lockedOutlet = null;
        if (! $canChooseOutlet && $userOutletId > 0) {
            $lockedOutlet = Outlet::where('id_outlet', $userOutletId)
                ->where('status', 'A')
                ->first(['id_outlet', 'nama_outlet']);
        }
        $selectedOutlet = null;
        if ($selectedOutletId > 0) {
            $selectedOutlet = Outlet::where('id_outlet', $selectedOutletId)
                ->where('status', 'A')
                ->first(['id_outlet', 'nama_outlet']);
        }

        return response()->json([
            'success' => true,
            'filters' => [
                'month' => $month,
                'id_outlet' => $canChooseOutlet ? ($selectedOutletId > 0 ? $selectedOutletId : null) : $selectedOutletId,
            ],
            'can_choose_outlet' => $canChooseOutlet,
            'outlets' => $outlets,
            'locked_outlet' => $lockedOutlet,
            'selected_outlet' => $selectedOutlet,
            'summary' => [
                'total_forms' => (int) ($current['total_forms'] ?? 0),
                'overall_mtd_pct' => $overallCurrent,
                'overall_last_month_pct' => $overallLast,
                'overall_delta_pct' => $overallDelta,
                'min_target_pct' => 85,
            ],
            'rows' => $rows,
            'trend' => $this->buildGsiMonthlyTrend(self::GSI_SUBJECT_COLUMNS, $monthStart, $selectedOutletId, $canChooseOutlet, $userOutletId, 6),
            'outlet_ranking' => $canChooseOutlet && $selectedOutletId <= 0
                ? $this->buildGsiOutletRanking(self::GSI_SUBJECT_COLUMNS, $monthStart, $monthEnd)
                : [],
            'issue_insights' => $issueInsights,
        ]);
    }

    public function store(Request $request, GuestCommentOcrService $ocr, AIAnalyticsService $ai): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:8192',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $path = $request->file('image')->store('guest_comment_forms', 'public');

        $form = GuestCommentForm::create([
            'image_path' => $path,
            'status' => 'pending_verification',
            'created_by' => $request->user()->id,
        ]);

        $absolute = Storage::disk('public')->path($path);
        $result = $ocr->extract($absolute);

        $payload = [
            'raw_text' => $result['raw_text'] ?? '',
            'fields' => $result['fields'] ?? [],
        ];

        $updates = [
            'ocr_raw_text' => $payload['raw_text'],
            'ocr_payload' => $payload,
        ];

        $fieldKeys = [
            'rating_service', 'rating_food', 'rating_beverage', 'rating_cleanliness',
            'rating_staff', 'rating_value', 'comment_text', 'guest_name', 'guest_address',
            'guest_phone', 'guest_dob', 'visit_date', 'praised_staff_name',
            'praised_staff_outlet', 'marketing_source',
        ];
        foreach ($fieldKeys as $key) {
            $v = $payload['fields'][$key] ?? null;
            if ($v !== null && $v !== '') {
                $updates[$key] = $v;
            }
        }

        $issueMeta = $this->classifyIssueMeta(
            $ai,
            (string) ($updates['comment_text'] ?? ''),
            (string) ($updates['guest_name'] ?? '')
        );
        $updates = array_merge($updates, $issueMeta);

        $form->update($updates);

        $anyField = false;
        foreach ($fieldKeys as $key) {
            if (! empty($updates[$key])) {
                $anyField = true;
                break;
            }
        }
        $hasRaw = trim((string) ($updates['ocr_raw_text'] ?? '')) !== '';
        $msg = 'Foto tersimpan. Silakan verifikasi data.';
        if (! $anyField && ! $hasRaw) {
            $msg .= ' OCR tidak mengisi field otomatis — periksa konfigurasi AI di server.';
        }

        $form->refresh();
        $form->load(['creator:id,nama_lengkap,avatar', 'verifier:id,nama_lengkap,avatar', 'outlet:id_outlet,nama_outlet']);

        return response()->json([
            'success' => true,
            'message' => $msg,
            'form' => $this->serializeForm($form),
        ], 201);
    }

    private function classifyIssueMeta(AIAnalyticsService $ai, string $commentText, string $guestName): array
    {
        $commentText = trim($commentText);
        if ($commentText === '') {
            return [
                'issue_severity' => null,
                'issue_topics' => null,
                'issue_summary_id' => null,
                'issue_classified_at' => null,
            ];
        }

        try {
            $classified = $ai->classifyGoogleReviews([[
                'author' => $guestName,
                'rating' => '',
                'text' => $commentText,
                'date' => now()->toDateString(),
            ]]);
            $aiClass = $classified[0]['ai_classification'] ?? [];
            $topics = $aiClass['topics'] ?? ['other'];
            if (! is_array($topics) || $topics === []) {
                $topics = ['other'];
            }

            return [
                'issue_severity' => (string) ($aiClass['severity'] ?? 'neutral'),
                'issue_topics' => array_values(array_filter(array_map('strval', $topics))),
                'issue_summary_id' => mb_substr((string) ($aiClass['summary_id'] ?? ''), 0, 255),
                'issue_classified_at' => now(),
            ];
        } catch (\Throwable $e) {
            \Log::warning('Guest comment issue classify failed on OCR API store', [
                'error' => $e->getMessage(),
            ]);

            return [
                'issue_severity' => 'neutral',
                'issue_topics' => ['other'],
                'issue_summary_id' => 'Klasifikasi AI gagal, fallback.',
                'issue_classified_at' => now(),
            ];
        }
    }

    private function applyOutletScope($query, int $selectedOutletId, bool $canChooseOutlet, int $userOutletId)
    {
        if ($canChooseOutlet) {
            if ($selectedOutletId > 0) {
                $query->where('id_outlet', $selectedOutletId);
            }
        } elseif ($userOutletId > 0) {
            $query->where('id_outlet', $userOutletId);
        } else {
            $query->whereRaw('0=1');
        }

        return $query;
    }

    private function buildGsiSubjectStats(array $subjectColumns, Carbon $start, Carbon $end, int $selectedOutletId, bool $canChooseOutlet, int $userOutletId): array
    {
        $base = DB::table('guest_comment_forms')
            ->where('status', 'verified')
            ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()]);
        $this->applyOutletScope($base, $selectedOutletId, $canChooseOutlet, $userOutletId);

        $totalForms = (int) (clone $base)->count();
        $subjects = [];
        $overallPositive = 0;
        $overallResponses = 0;

        foreach ($subjectColumns as $column => $label) {
            $r = (clone $base)
                ->selectRaw("
                    SUM(CASE WHEN {$column} = 'excellent' THEN 1 ELSE 0 END) AS excellent,
                    SUM(CASE WHEN {$column} = 'good' THEN 1 ELSE 0 END) AS good,
                    SUM(CASE WHEN {$column} = 'average' THEN 1 ELSE 0 END) AS average,
                    SUM(CASE WHEN {$column} = 'poor' THEN 1 ELSE 0 END) AS poor
                ")
                ->first();

            $excellent = (int) ($r->excellent ?? 0);
            $good = (int) ($r->good ?? 0);
            $average = (int) ($r->average ?? 0);
            $poor = (int) ($r->poor ?? 0);
            $responses = $excellent + $good + $average + $poor;
            $abstain = max(0, $totalForms - $responses);
            $positive = $excellent + $good;
            $mtdPct = $responses > 0 ? round(($positive / $responses) * 100, 2) : null;

            $subjects[$column] = [
                'label' => $label,
                'excellent' => $excellent,
                'good' => $good,
                'average' => $average,
                'poor' => $poor,
                'abstain' => $abstain,
                'responses' => $responses,
                'mtd_pct' => $mtdPct,
            ];

            $overallPositive += $positive;
            $overallResponses += $responses;
        }

        return [
            'total_forms' => $totalForms,
            'subjects' => $subjects,
            'overall_pct' => $overallResponses > 0 ? round(($overallPositive / $overallResponses) * 100, 2) : null,
        ];
    }

    private function buildGsiMonthlyTrend(array $subjectColumns, Carbon $selectedMonthStart, int $selectedOutletId, bool $canChooseOutlet, int $userOutletId, int $monthsBack = 6): array
    {
        $rows = [];
        for ($i = $monthsBack - 1; $i >= 0; $i--) {
            $start = $selectedMonthStart->copy()->subMonths($i)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $stats = $this->buildGsiSubjectStats($subjectColumns, $start, $end, $selectedOutletId, $canChooseOutlet, $userOutletId);
            $rows[] = [
                'month' => $start->format('Y-m'),
                'label' => strtoupper($start->locale('id')->translatedFormat('M Y')),
                'gsi_pct' => $stats['overall_pct'],
                'total_forms' => (int) ($stats['total_forms'] ?? 0),
            ];
        }
        return $rows;
    }

    private function buildGsiOutletRanking(array $subjectColumns, Carbon $start, Carbon $end): array
    {
        $positiveExprParts = [];
        $responseExprParts = [];
        foreach (array_keys($subjectColumns) as $column) {
            $positiveExprParts[] = "SUM(CASE WHEN g.{$column} IN ('excellent','good') THEN 1 ELSE 0 END)";
            $responseExprParts[] = "SUM(CASE WHEN g.{$column} IN ('excellent','good','average','poor') THEN 1 ELSE 0 END)";
        }
        $positiveExpr = implode(' + ', $positiveExprParts);
        $responseExpr = implode(' + ', $responseExprParts);

        $rows = DB::table('guest_comment_forms as g')
            ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 'g.id_outlet')
            ->where('g.status', 'verified')
            ->whereNotNull('g.id_outlet')
            ->whereBetween('g.created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->groupBy('g.id_outlet', 'o.nama_outlet')
            ->selectRaw("g.id_outlet as outlet_id, o.nama_outlet as outlet_name, COUNT(*) as responses, {$responseExpr} as rating_responses, {$positiveExpr} as positives")
            ->get();

        return collect($rows)
            ->map(function ($r) {
                $responses = (int) ($r->responses ?? 0);
                $ratingResponses = (int) ($r->rating_responses ?? 0);
                $positives = (int) ($r->positives ?? 0);
                return [
                    'outlet_id' => (int) $r->outlet_id,
                    'outlet_name' => (string) $r->outlet_name,
                    'responses' => $responses,
                    'gsi_pct' => $ratingResponses > 0 ? round(($positives / $ratingResponses) * 100, 2) : null,
                ];
            })
            ->filter(fn ($r) => $r['responses'] > 0)
            ->sortByDesc(fn ($r) => $r['gsi_pct'] ?? -1)
            ->take(10)
            ->values()
            ->all();
    }

    private function buildGsiIssueInsights(
        Carbon $start,
        Carbon $end,
        int $selectedOutletId,
        bool $canChooseOutlet,
        int $userOutletId
    ): array {
        $base = DB::table('guest_comment_forms')
            ->where('status', 'verified')
            ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->whereNotNull('comment_text')
            ->where('comment_text', '!=', '');
        $this->applyOutletScope($base, $selectedOutletId, $canChooseOutlet, $userOutletId);

        $totalComments = (int) (clone $base)->count();
        if ($totalComments <= 0) {
            return [
                'status' => 'empty',
                'message' => 'Belum ada komentar terverifikasi untuk dianalisis AI.',
                'total_comments' => 0,
                'top_topics' => [],
                'severity' => [],
                'topic_examples' => [],
            ];
        }

        $rows = (clone $base)
            ->select('guest_name', 'comment_text', 'issue_severity', 'issue_topics', 'issue_summary_id')
            ->orderByDesc('id')
            ->get();

        $topicCounts = [];
        $severityCounts = [];
        $topicExamples = [];
        foreach ($rows as $row) {
            $severity = trim((string) ($row->issue_severity ?? '')) !== ''
                ? (string) $row->issue_severity
                : 'neutral';
            $severityCounts[$severity] = (int) ($severityCounts[$severity] ?? 0) + 1;

            $rawTopics = $row->issue_topics;
            if (is_string($rawTopics) && trim($rawTopics) !== '') {
                $decoded = json_decode($rawTopics, true);
                $topics = is_array($decoded) ? $decoded : [];
            } else {
                $topics = is_array($rawTopics) ? $rawTopics : [];
            }
            if ($topics === []) {
                $topics = ['other'];
            }
            $summary = (string) ($row->issue_summary_id ?? '');

            foreach ($topics as $topic) {
                $topicKey = trim((string) $topic) !== '' ? trim((string) $topic) : 'other';
                $topicCounts[$topicKey] = (int) ($topicCounts[$topicKey] ?? 0) + 1;

                if (! isset($topicExamples[$topicKey])) {
                    $topicExamples[$topicKey] = [];
                }
                if (count($topicExamples[$topicKey]) < 3) {
                    $topicExamples[$topicKey][] = [
                        'author' => (string) ($row->guest_name ?? '-'),
                        'text' => mb_substr((string) ($row->comment_text ?? ''), 0, 200),
                        'summary_id' => $summary,
                        'severity' => $severity,
                    ];
                }
            }
        }

        arsort($topicCounts);
        $topTopics = collect($topicCounts)
            ->map(function ($count, $topic) {
                return [
                    'topic' => (string) $topic,
                    'count' => (int) $count,
                    'label' => $this->mapIssueTopicLabel((string) $topic),
                ];
            })
            ->take(8)
            ->values()
            ->all();

        $topicExampleList = [];
        foreach ($topTopics as $topicRow) {
            $key = $topicRow['topic'];
            $topicExampleList[] = [
                'topic' => $key,
                'label' => $topicRow['label'],
                'examples' => $topicExamples[$key] ?? [],
            ];
        }

        return [
            'status' => 'ready',
            'message' => null,
            'total_comments' => $totalComments,
            'top_topics' => $topTopics,
            'severity' => $severityCounts,
            'topic_examples' => $topicExampleList,
        ];
    }

    private function mapIssueTopicLabel(string $topic): string
    {
        $map = [
            'food_quality' => 'Food Quality',
            'service' => 'Service',
            'hygiene' => 'Hygiene',
            'ambiance' => 'Ambiance',
            'price' => 'Price / Value',
            'wait_time' => 'Speed / Wait Time',
            'parking' => 'Parking',
            'portion' => 'Portion',
            'noise' => 'Noise',
            'reservation' => 'Reservation',
            'other' => 'Other',
            'beverage' => 'Beverage',
            'cleanliness' => 'Cleanliness',
            'staff_attitude' => 'Staff Attitude',
            'price_value' => 'Price / Value',
            'speed_wait_time' => 'Speed / Wait Time',
        ];
        return $map[$topic] ?? ucfirst(str_replace('_', ' ', $topic));
    }

    public function show(Request $request, GuestCommentForm $guest_comment_form): JsonResponse
    {
        $this->authorizeGuestCommentFormAccess($request, $guest_comment_form);

        return response()->json([
            'success' => true,
            'form' => $this->serializeForm($guest_comment_form),
        ]);
    }

    public function update(Request $request, GuestCommentForm $guest_comment_form): JsonResponse
    {
        $this->authorizeGuestCommentFormAccess($request, $guest_comment_form);

        if ($guest_comment_form->status === 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'Data sudah terverifikasi, tidak bisa diubah.',
            ], 422);
        }

        if ($request->input('id_outlet') === '' || $request->input('id_outlet') === null) {
            $request->merge(['id_outlet' => null]);
        }

        $rules = [
            'rating_service' => ['nullable', Rule::in(self::RATINGS)],
            'rating_food' => ['nullable', Rule::in(self::RATINGS)],
            'rating_beverage' => ['nullable', Rule::in(self::RATINGS)],
            'rating_cleanliness' => ['nullable', Rule::in(self::RATINGS)],
            'rating_staff' => ['nullable', Rule::in(self::RATINGS)],
            'rating_value' => ['nullable', Rule::in(self::RATINGS)],
            'comment_text' => 'nullable|string',
            'guest_name' => 'nullable|string|max:255',
            'guest_address' => 'nullable|string|max:500',
            'guest_phone' => 'nullable|string|max:100',
            'guest_dob' => 'nullable|date',
            'visit_date' => 'nullable|string|max:100',
            'praised_staff_name' => 'nullable|string|max:255',
            'praised_staff_outlet' => 'nullable|string|max:255',
            'marketing_source' => 'nullable|string|max:255',
            'id_outlet' => 'nullable|integer|exists:tbl_data_outlet,id_outlet',
            'mark_verified' => 'nullable|boolean',
        ];

        $data = $request->validate($rules);
        $markVerified = (bool) ($data['mark_verified'] ?? false);
        unset($data['mark_verified']);

        foreach (['rating_service', 'rating_food', 'rating_beverage', 'rating_cleanliness', 'rating_staff', 'rating_value'] as $rk) {
            if (array_key_exists($rk, $data) && $data[$rk] === '') {
                $data[$rk] = null;
            }
        }

        $editorOutletId = (int) ($request->user()->id_outlet ?? 0);
        if ($editorOutletId !== 1) {
            $data['id_outlet'] = $editorOutletId > 0 ? $editorOutletId : null;
        }

        $guest_comment_form->fill($data);

        if ($markVerified) {
            $guest_comment_form->status = 'verified';
            $guest_comment_form->verified_by = $request->user()->id;
            $guest_comment_form->verified_at = now();
        }

        $guest_comment_form->save();
        $guest_comment_form->refresh();
        $guest_comment_form->load(['creator:id,nama_lengkap,avatar', 'verifier:id,nama_lengkap,avatar', 'outlet:id_outlet,nama_outlet']);

        return response()->json([
            'success' => true,
            'message' => $markVerified
                ? 'Data tersimpan dan terverifikasi.'
                : 'Perubahan disimpan (belum terverifikasi).',
            'form' => $this->serializeForm($guest_comment_form),
        ]);
    }

    public function destroy(Request $request, GuestCommentForm $guest_comment_form): JsonResponse
    {
        $this->authorizeGuestCommentFormAccess($request, $guest_comment_form);

        $relativePath = $guest_comment_form->image_path;
        $guest_comment_form->delete();

        if ($relativePath && Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data guest comment berhasil dihapus.',
        ]);
    }
}
