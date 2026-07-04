<?php

namespace App\Http\Controllers;

use App\Models\CompetitorBenchmarkReport;
use App\Models\CompetitorBenchmarkReportItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CompetitorBenchmarkReportController extends Controller
{
    private const SUPERADMIN_ROLE_ID = '5af56935b011a';

    public function index(Request $request): Response
    {
        $query = $this->baseListQuery($request);
        $reports = $query->paginate((int) $request->get('perPage', 15))->withQueryString();

        return Inertia::render('CompetitorBenchmarkReport/Index', [
            'reports' => $reports,
            'filters' => [
                'search' => $request->get('search', ''),
                'month' => $request->get('month', ''),
                'perPage' => $request->get('perPage', 15),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('CompetitorBenchmarkReport/Form', array_merge(
            $this->formOptions(),
            ['record' => null]
        ));
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        DB::beginTransaction();
        try {
            $report = $this->createReport($validated);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('competitor-benchmark-report.show', $report->id)
            ->with('success', 'Competitor Benchmark Report berhasil disimpan.');
    }

    public function show(CompetitorBenchmarkReport $competitorBenchmarkReport): Response
    {
        $competitorBenchmarkReport->load([
            'items',
            'creator:id,nama_lengkap',
        ]);

        return Inertia::render('CompetitorBenchmarkReport/Show', [
            'record' => $competitorBenchmarkReport,
            'canEdit' => $this->canManage($competitorBenchmarkReport),
            'canDelete' => $this->canManage($competitorBenchmarkReport),
        ]);
    }

    public function edit(CompetitorBenchmarkReport $competitorBenchmarkReport): Response
    {
        $this->ensureEditable($competitorBenchmarkReport);
        $competitorBenchmarkReport->load('items');

        return Inertia::render('CompetitorBenchmarkReport/Form', array_merge(
            $this->formOptions(),
            ['record' => $competitorBenchmarkReport]
        ));
    }

    public function update(Request $request, CompetitorBenchmarkReport $competitorBenchmarkReport)
    {
        $this->ensureEditable($competitorBenchmarkReport);
        $validated = $this->validatePayload($request);

        DB::beginTransaction();
        try {
            $this->updateReport($competitorBenchmarkReport, $validated);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('competitor-benchmark-report.show', $competitorBenchmarkReport->id)
            ->with('success', 'Competitor Benchmark Report berhasil diperbarui.');
    }

    public function destroy(CompetitorBenchmarkReport $competitorBenchmarkReport)
    {
        $this->ensureEditable($competitorBenchmarkReport);
        $competitorBenchmarkReport->delete();

        return redirect()
            ->route('competitor-benchmark-report.index')
            ->with('success', 'Competitor Benchmark Report berhasil dihapus.');
    }

    public function getPicUsers(Request $request)
    {
        return response()->json(['success' => true, 'users' => $this->searchUsers($request)]);
    }

    public function apiIndex(Request $request)
    {
        $paginator = $this->baseListQuery($request)->paginate((int) $request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'reports' => collect($paginator->items())->map(fn (CompetitorBenchmarkReport $report) => $this->serializeListRecord($report))->values()->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function apiCreateData(?int $id = null)
    {
        $record = null;
        if ($id !== null) {
            $record = CompetitorBenchmarkReport::with('items')->findOrFail($id);
            try {
                $this->ensureEditable($record);
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Data tidak dapat diubah.',
                ], 403);
            }
        }

        return response()->json([
            'success' => true,
            'record' => $record ? $this->serializeDetailRecord($record) : null,
            ...$this->formOptions(),
        ]);
    }

    public function apiShow(int $id)
    {
        $report = CompetitorBenchmarkReport::with([
            'items',
            'creator:id,nama_lengkap',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'record' => $this->serializeDetailRecord($report),
            'can_edit' => $this->canManage($report),
            'can_delete' => $this->canManage($report),
        ]);
    }

    public function apiStore(Request $request)
    {
        try {
            $validated = $this->validatePayload($request);
            $report = $this->persistReport(null, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Competitor Benchmark Report berhasil disimpan.',
                'id' => $report->id,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Validasi gagal.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function apiUpdate(Request $request, int $id)
    {
        $report = CompetitorBenchmarkReport::findOrFail($id);

        try {
            $this->ensureEditable($report);
            $validated = $this->validatePayload($request);
            $report = $this->persistReport($report, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Competitor Benchmark Report berhasil diperbarui.',
                'id' => $report->id,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Validasi gagal.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Data tidak dapat diubah.',
            ], 403);
        }
    }

    public function apiDestroy(int $id)
    {
        $report = CompetitorBenchmarkReport::findOrFail($id);

        try {
            $this->ensureEditable($report);
            $report->delete();

            return response()->json([
                'success' => true,
                'message' => 'Competitor Benchmark Report berhasil dihapus.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Gagal menghapus.',
            ], 403);
        }
    }

    public function apiSearchPicUsers(Request $request)
    {
        return $this->getPicUsers($request);
    }

    private function baseListQuery(Request $request)
    {
        $query = CompetitorBenchmarkReport::query()
            ->with(['creator:id,nama_lengkap'])
            ->withCount('items')
            ->orderByDesc('report_month')
            ->orderByDesc('id');

        if ($request->filled('month')) {
            $month = $request->string('month')->toString();
            $query->whereDate('report_month', '>=', $month.'-01')
                ->whereDate('report_month', '<=', date('Y-m-t', strtotime($month.'-01')));
        }

        if ($search = trim((string) $request->get('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhereHas('creator', fn ($creator) => $creator->where('nama_lengkap', 'like', "%{$search}%"))
                    ->orWhereHas('items', function ($items) use ($search) {
                        $items->where('brand_restaurant_visited', 'like', "%{$search}%")
                            ->orWhere('location', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'report_month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'notes' => 'nullable|string|max:2000',
            'pic_user_ids' => 'nullable|array',
            'pic_user_ids.*' => 'integer|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.brand_restaurant_visited' => 'required|string|max:255',
            'items.*.location' => 'nullable|string|max:255',
            'items.*.visit_date' => 'nullable|date',
            'items.*.product_benchmark' => 'nullable|string|max:5000',
            'items.*.service_benchmark' => 'nullable|string|max:5000',
            'items.*.pricing_benchmark' => 'nullable|string|max:5000',
            'items.*.operational_benchmark' => 'nullable|string|max:5000',
            'items.*.market_positioning_benchmark' => 'nullable|string|max:5000',
            'items.*.summary_report' => 'nullable|string|max:5000',
            'items.*.development_action_plan' => 'nullable|string|max:5000',
        ], [
            'items.required' => 'Tambahkan minimal satu baris benchmark.',
            'items.min' => 'Tambahkan minimal satu baris benchmark.',
        ]);
    }

    private function createReport(array $validated): CompetitorBenchmarkReport
    {
        $report = CompetitorBenchmarkReport::create([
            'number' => $this->generateNumber($validated['report_month']),
            'report_month' => $validated['report_month'].'-01',
            'outlet_id' => null,
            'outlet_name' => null,
            'pics' => $this->buildPicPayload($validated['pic_user_ids'] ?? []),
            'status' => 'approved',
            'notes' => $validated['notes'] ?? null,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $this->syncItems($report, $validated['items']);

        return $report;
    }

    private function updateReport(CompetitorBenchmarkReport $report, array $validated): void
    {
        $report->update([
            'report_month' => $validated['report_month'].'-01',
            'pics' => $this->buildPicPayload($validated['pic_user_ids'] ?? []),
            'notes' => $validated['notes'] ?? null,
            'status' => 'approved',
            'updated_by' => Auth::id(),
        ]);

        $this->syncItems($report, $validated['items']);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function persistReport(?CompetitorBenchmarkReport $existing, array $validated): CompetitorBenchmarkReport
    {
        DB::beginTransaction();
        try {
            if ($existing) {
                $this->updateReport($existing, $validated);
                DB::commit();

                return $existing->fresh(['items']);
            }

            $report = $this->createReport($validated);
            DB::commit();

            return $report->fresh(['items']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function syncItems(CompetitorBenchmarkReport $report, array $items): void
    {
        CompetitorBenchmarkReportItem::where('report_id', $report->id)->delete();

        foreach ($items as $index => $item) {
            CompetitorBenchmarkReportItem::create([
                'report_id' => $report->id,
                'sort_order' => $index,
                'brand_restaurant_visited' => $item['brand_restaurant_visited'],
                'location' => $item['location'] ?? null,
                'visit_date' => $item['visit_date'] ?? null,
                'product_benchmark' => $item['product_benchmark'] ?? null,
                'service_benchmark' => $item['service_benchmark'] ?? null,
                'pricing_benchmark' => $item['pricing_benchmark'] ?? null,
                'operational_benchmark' => $item['operational_benchmark'] ?? null,
                'market_positioning_benchmark' => $item['market_positioning_benchmark'] ?? null,
                'summary_report' => $item['summary_report'] ?? null,
                'development_action_plan' => $item['development_action_plan'] ?? null,
            ]);
        }
    }

    /**
     * @param  list<int|string>  $picUserIds
     * @return list<array{id:int,name:string,jabatan:string}>
     */
    private function buildPicPayload(array $picUserIds): array
    {
        $picUsers = User::query()
            ->whereIn('users.id', collect($picUserIds)->filter()->unique())
            ->leftJoin('tbl_data_jabatan as j', 'users.id_jabatan', '=', 'j.id_jabatan')
            ->get([
                'users.id',
                'users.nama_lengkap',
                DB::raw('j.nama_jabatan as jabatan'),
            ])
            ->keyBy('id');

        return collect($picUserIds)
            ->map(fn ($userId) => [
                'id' => (int) $userId,
                'name' => (string) ($picUsers[(int) $userId]->nama_lengkap ?? ''),
                'jabatan' => (string) ($picUsers[(int) $userId]->jabatan ?? ''),
            ])
            ->values()
            ->all();
    }

    private function formOptions(): array
    {
        return [];
    }

    private function generateNumber(string $reportMonth): string
    {
        $prefix = 'CBR-'.str_replace('-', '', $reportMonth).'-';
        $last = CompetitorBenchmarkReport::withTrashed()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function searchUsers(Request $request): \Illuminate\Support\Collection
    {
        $search = trim((string) $request->get('search', ''));

        return User::query()
            ->where('users.status', 'A')
            ->leftJoin('tbl_data_jabatan as j', 'users.id_jabatan', '=', 'j.id_jabatan')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('users.nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%")
                        ->orWhere('j.nama_jabatan', 'like', "%{$search}%");
                });
            })
            ->orderBy('users.nama_lengkap')
            ->limit(20)
            ->get([
                'users.id',
                'users.nama_lengkap as name',
                'users.email',
                DB::raw('j.nama_jabatan as jabatan'),
            ]);
    }

    private function isSuperAdmin(): bool
    {
        return (string) (Auth::user()?->id_role ?? '') === self::SUPERADMIN_ROLE_ID;
    }

    private function canManage(CompetitorBenchmarkReport $report): bool
    {
        return $this->isSuperAdmin() || (int) Auth::id() === (int) $report->created_by;
    }

    private function ensureEditable(CompetitorBenchmarkReport $report): void
    {
        if (! $this->canManage($report)) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah report ini.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeListRecord(CompetitorBenchmarkReport $report): array
    {
        return [
            'id' => $report->id,
            'number' => $report->number,
            'report_month' => $report->report_month?->format('Y-m'),
            'items_count' => $report->items_count ?? $report->items()->count(),
            'creator_name' => $report->creator?->nama_lengkap,
            'created_by' => $report->created_by,
            'can_edit' => $this->canManage($report),
            'updated_at' => $report->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDetailRecord(CompetitorBenchmarkReport $report): array
    {
        return [
            'id' => $report->id,
            'number' => $report->number,
            'report_month' => $report->report_month?->format('Y-m'),
            'notes' => $report->notes,
            'pics' => $report->pics ?? [],
            'created_by' => $report->created_by,
            'creator' => $report->creator ? [
                'id' => $report->creator->id,
                'nama_lengkap' => $report->creator->nama_lengkap,
            ] : null,
            'items' => $report->items->map(fn ($item) => [
                'id' => $item->id,
                'brand_restaurant_visited' => $item->brand_restaurant_visited,
                'location' => $item->location,
                'visit_date' => $item->visit_date?->format('Y-m-d'),
                'product_benchmark' => $item->product_benchmark,
                'service_benchmark' => $item->service_benchmark,
                'pricing_benchmark' => $item->pricing_benchmark,
                'operational_benchmark' => $item->operational_benchmark,
                'market_positioning_benchmark' => $item->market_positioning_benchmark,
                'summary_report' => $item->summary_report,
                'development_action_plan' => $item->development_action_plan,
            ])->values()->all(),
        ];
    }
}
