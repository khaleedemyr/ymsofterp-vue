<?php

namespace App\Http\Controllers;

use App\Models\GuestCommentForm;
use App\Models\Outlet;
use App\Services\AIAnalyticsService;
use App\Services\GuestCommentOcrService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class GuestCommentFormController extends Controller
{
    private const RATINGS = ['poor', 'average', 'good', 'excellent'];

    /**
     * id_outlet user = 1 (pusat): akses semua data, boleh filter outlet di index.
     * Selain itu: hanya data outlet sendiri; parameter id_outlet di URL diabaikan.
     */
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

    public function index(Request $request)
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

        $forms = $query->paginate(15)->withQueryString();

        $forms->getCollection()->transform(function (GuestCommentForm $form) {
            $form->setAttribute(
                'image_url',
                $form->image_path
                    ? Storage::disk('public')->url($form->image_path)
                    : null
            );

            return $form;
        });

        $outlets = $canChooseOutlet
            ? Outlet::where('status', 'A')->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet'])
            : collect();

        $lockedOutlet = null;
        if (! $canChooseOutlet && $userOutletId > 0) {
            $lockedOutlet = Outlet::where('id_outlet', $userOutletId)->where('status', 'A')->first(['id_outlet', 'nama_outlet']);
        }

        return Inertia::render('GuestComment/Index', [
            'forms' => $forms,
            'outlets' => $outlets,
            'canChooseOutlet' => $canChooseOutlet,
            'lockedOutlet' => $lockedOutlet,
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
                'id_outlet' => $canChooseOutlet ? $request->id_outlet : null,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
            ],
        ]);
    }

    public function gsiDashboard(Request $request)
    {
        $userOutletId = (int) ($request->user()->id_outlet ?? 0);
        $canChooseOutlet = ($userOutletId === 1);

        $month = (string) ($request->input('month') ?: now()->format('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        $selectedOutletId = (int) ($request->input('id_outlet') ?: 0);
        if (! $canChooseOutlet) {
            $selectedOutletId = max(0, $userOutletId);
        }

        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $lastMonthStart = $monthStart->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $lastMonthStart->copy()->endOfMonth();

        $subjectColumns = [
            'rating_service' => 'Quality of Service',
            'rating_food' => 'Quality of Food',
            'rating_beverage' => 'Quality of Beverage',
            'rating_cleanliness' => 'Quality of Cleanliness',
            'rating_staff' => 'Attentiveness of Staff',
            'rating_value' => 'Value for Money',
        ];

        $currentStats = $this->buildGsiSubjectStats(
            $subjectColumns,
            $monthStart,
            $monthEnd,
            $selectedOutletId,
            $canChooseOutlet,
            $userOutletId
        );

        $lastStats = $this->buildGsiSubjectStats(
            $subjectColumns,
            $lastMonthStart,
            $lastMonthEnd,
            $selectedOutletId,
            $canChooseOutlet,
            $userOutletId
        );

        $rows = [];
        foreach ($subjectColumns as $column => $label) {
            $cur = $currentStats['subjects'][$column] ?? [];
            $prev = $lastStats['subjects'][$column] ?? [];
            $rows[] = [
                'subject' => $label,
                'excellent' => (int) ($cur['excellent'] ?? 0),
                'good' => (int) ($cur['good'] ?? 0),
                'average' => (int) ($cur['average'] ?? 0),
                'poor' => (int) ($cur['poor'] ?? 0),
                'abstain' => (int) ($cur['abstain'] ?? 0),
                'total_responses' => (int) ($cur['responses'] ?? 0),
                'mtd_pct' => $cur['mtd_pct'] ?? null,
                'last_month_pct' => $prev['mtd_pct'] ?? null,
            ];
        }

        $overallMtd = $currentStats['overall_pct'];
        $overallLastMonth = $lastStats['overall_pct'];
        $overallDelta = null;
        if ($overallMtd !== null && $overallLastMonth !== null) {
            $overallDelta = round($overallMtd - $overallLastMonth, 2);
        }

        $outlets = $canChooseOutlet
            ? Outlet::where('status', 'A')->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet'])
            : collect();
        $lockedOutlet = null;
        if (! $canChooseOutlet && $userOutletId > 0) {
            $lockedOutlet = Outlet::where('id_outlet', $userOutletId)->where('status', 'A')->first(['id_outlet', 'nama_outlet']);
        }
        $selectedOutlet = null;
        if ($selectedOutletId > 0) {
            $selectedOutlet = Outlet::where('id_outlet', $selectedOutletId)->first(['id_outlet', 'nama_outlet']);
        }

        $issueInsights = $this->buildGsiIssueInsights(
            $monthStart,
            $monthEnd,
            $selectedOutletId,
            $canChooseOutlet,
            $userOutletId
        );

        return Inertia::render('GuestComment/GSIDashboard', [
            'filters' => [
                'month' => $month,
                'id_outlet' => $canChooseOutlet ? ($selectedOutletId > 0 ? $selectedOutletId : null) : $selectedOutletId,
            ],
            'canChooseOutlet' => $canChooseOutlet,
            'outlets' => $outlets,
            'lockedOutlet' => $lockedOutlet,
            'selectedOutlet' => $selectedOutlet,
            'summary' => [
                'total_forms' => (int) ($currentStats['total_forms'] ?? 0),
                'overall_mtd_pct' => $overallMtd,
                'overall_last_month_pct' => $overallLastMonth,
                'overall_delta_pct' => $overallDelta,
                'min_target_pct' => 85,
            ],
            'rows' => $rows,
            'trend' => $this->buildGsiMonthlyTrend($subjectColumns, $monthStart, $selectedOutletId, $canChooseOutlet, $userOutletId, 6),
            'outletRanking' => $canChooseOutlet && $selectedOutletId <= 0
                ? $this->buildGsiOutletRanking($subjectColumns, $monthStart, $monthEnd)
                : [],
            'issueInsights' => $issueInsights,
        ]);
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

    public function create()
    {
        return Inertia::render('GuestComment/Create');
    }

    public function store(Request $request, GuestCommentOcrService $ocr, AIAnalyticsService $ai)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:8192',
        ]);

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
            $msg .= ' Isi otomatis tidak jalan: cek API key di .env (Gemini/OpenAI/Claude), atau GUEST_COMMENT_AI_PROVIDER + GUEST_COMMENT_GEMINI_MODEL untuk OCR murah, atau set GUEST_COMMENT_OCR_ENABLED=false.';
        }

        return redirect()->route('guest-comment-forms.verify', $form)->with('success', $msg);
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
            \Log::warning('Guest comment issue classify failed on OCR store', [
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

    public function show(Request $request, GuestCommentForm $guest_comment_form)
    {
        $this->authorizeGuestCommentFormAccess($request, $guest_comment_form);

        $guest_comment_form->load(['creator:id,nama_lengkap,avatar', 'verifier:id,nama_lengkap,avatar', 'outlet:id_outlet,nama_outlet']);

        return Inertia::render('GuestComment/Show', [
            'form' => $guest_comment_form,
            'imageUrl' => Storage::disk('public')->url($guest_comment_form->image_path),
        ]);
    }

    public function verify(Request $request, GuestCommentForm $guest_comment_form)
    {
        $this->authorizeGuestCommentFormAccess($request, $guest_comment_form);

        $guest_comment_form->load(['creator:id,nama_lengkap,avatar', 'verifier:id,nama_lengkap,avatar', 'outlet:id_outlet,nama_outlet']);

        $userOutletId = (int) ($request->user()->id_outlet ?? 0);
        $canChooseOutlet = ($userOutletId === 1);

        $outlets = $canChooseOutlet
            ? Outlet::where('status', 'A')->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet'])
            : collect();

        $lockedOutlet = null;
        if (! $canChooseOutlet && $userOutletId > 0) {
            $lockedOutlet = Outlet::where('id_outlet', $userOutletId)
                ->where('status', 'A')
                ->first(['id_outlet', 'nama_outlet']);
        }

        return Inertia::render('GuestComment/Verify', [
            'form' => $guest_comment_form,
            'imageUrl' => Storage::disk('public')->url($guest_comment_form->image_path),
            'outlets' => $outlets,
            'canChooseOutlet' => $canChooseOutlet,
            'lockedOutlet' => $lockedOutlet,
            'ratingOptions' => self::RATINGS,
            'readOnly' => $guest_comment_form->status === 'verified',
        ]);
    }

    public function update(Request $request, GuestCommentForm $guest_comment_form)
    {
        $this->authorizeGuestCommentFormAccess($request, $guest_comment_form);

        if ($guest_comment_form->status === 'verified') {
            return redirect()->route('guest-comment-forms.show', $guest_comment_form)
                ->with('error', 'Data sudah terverifikasi, tidak bisa diubah.');
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
        $markVerified = ! empty($data['mark_verified']);
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

        if ($markVerified) {
            return redirect()->route('guest-comment-forms.show', $guest_comment_form)
                ->with('success', 'Data guest comment tersimpan dan ditandai terverifikasi.');
        }

        return redirect()->route('guest-comment-forms.verify', $guest_comment_form)
            ->with('success', 'Perubahan disimpan (belum terverifikasi).');
    }

    public function destroy(Request $request, GuestCommentForm $guest_comment_form)
    {
        $this->authorizeGuestCommentFormAccess($request, $guest_comment_form);

        $relativePath = $guest_comment_form->image_path;
        $guest_comment_form->delete();

        if ($relativePath && Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }

        return redirect()->route('guest-comment-forms.index')
            ->with('success', 'Data guest comment berhasil dihapus.');
    }
}
