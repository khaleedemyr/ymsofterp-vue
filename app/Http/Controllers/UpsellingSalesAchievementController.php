<?php

namespace App\Http\Controllers;

use App\Http\Traits\WritesActivityLogTrait;
use App\Models\Outlet;
use App\Models\UpsellingSalesAchievement;
use App\Models\UpsellingSalesAchievementItem;
use App\Services\UpsellingSalesAchievementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UpsellingSalesAchievementController extends Controller
{
    use WritesActivityLogTrait;

    public function __construct(
        private readonly UpsellingSalesAchievementService $service
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->get('search', ''));
        $outletId = $request->get('outlet_id', '');
        $month = $request->get('month', '');
        $year = $request->get('year', '');

        $query = UpsellingSalesAchievement::query()
            ->with(['outlet', 'creator', 'items'])
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('id');

        if ($search !== '') {
            $query->whereHas('outlet', function ($q) use ($search) {
                $q->where('nama_outlet', 'like', "%{$search}%");
            });
        }
        if ($outletId !== '') {
            $query->where('outlet_id', $outletId);
        }
        if ($month !== '') {
            $query->where('month', (int) $month);
        }
        if ($year !== '') {
            $query->where('year', (int) $year);
        }

        $records = $query->paginate(15)->withQueryString();

        $records->getCollection()->transform(function (UpsellingSalesAchievement $record) {
            $record->setAttribute(
                'achievement_percent',
                $this->service->computeAchievementPercent($record)
            );

            return $record;
        });

        return Inertia::render('UpsellingSalesAchievement/Index', [
            'records' => $records,
            'outlets' => Outlet::where('status', 'A')->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
            'filters' => [
                'search' => $search,
                'outlet_id' => $outletId,
                'month' => $month,
                'year' => $year,
            ],
            'monthOptions' => $this->monthOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('UpsellingSalesAchievement/Form', [
            'record' => null,
            'outlets' => Outlet::where('status', 'A')->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
            'monthOptions' => $this->monthOptions(),
            'yearOptions' => $this->yearOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $exists = UpsellingSalesAchievement::where('outlet_id', $validated['outlet_id'])
            ->where('month', $validated['month'])
            ->where('year', $validated['year'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'month' => 'Data upselling untuk outlet, bulan, dan tahun ini sudah ada.',
            ]);
        }

        DB::beginTransaction();
        try {
            $achievement = UpsellingSalesAchievement::create([
                'outlet_id' => $validated['outlet_id'],
                'month' => $validated['month'],
                'year' => $validated['year'],
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->syncItems($achievement, $validated['items']);

            DB::commit();

            $achievement->load(['outlet', 'items']);
            $this->writeActivityLog(
                $request,
                'upselling_sales_achievement',
                'create',
                $this->activityDescription('Membuat', $achievement),
                null,
                $this->achievementSnapshot($achievement)
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('upselling-sales-achievement.index')
            ->with('success', 'Upselling Sales Achievement berhasil disimpan.');
    }

    public function show(UpsellingSalesAchievement $upsellingSalesAchievement): Response
    {
        $upsellingSalesAchievement->load(['outlet', 'creator', 'items']);
        $detail = $this->service->buildDetailRows($upsellingSalesAchievement);

        return Inertia::render('UpsellingSalesAchievement/Show', [
            'record' => $upsellingSalesAchievement,
            'detail' => $detail,
            'monthLabel' => UpsellingSalesAchievementService::monthLabel((int) $upsellingSalesAchievement->month),
        ]);
    }

    public function edit(UpsellingSalesAchievement $upsellingSalesAchievement): Response
    {
        $upsellingSalesAchievement->load(['outlet', 'items']);

        return Inertia::render('UpsellingSalesAchievement/Form', [
            'record' => $upsellingSalesAchievement,
            'outlets' => Outlet::where('status', 'A')->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
            'monthOptions' => $this->monthOptions(),
            'yearOptions' => $this->yearOptions((int) $upsellingSalesAchievement->year),
        ]);
    }

    public function update(Request $request, UpsellingSalesAchievement $upsellingSalesAchievement)
    {
        $validated = $this->validatePayload($request, $upsellingSalesAchievement);

        $exists = UpsellingSalesAchievement::where('outlet_id', $validated['outlet_id'])
            ->where('month', $validated['month'])
            ->where('year', $validated['year'])
            ->where('id', '!=', $upsellingSalesAchievement->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'month' => 'Data upselling untuk outlet, bulan, dan tahun ini sudah ada.',
            ]);
        }

        DB::beginTransaction();
        try {
            $upsellingSalesAchievement->load(['outlet', 'items']);
            $oldSnapshot = $this->achievementSnapshot($upsellingSalesAchievement);

            $upsellingSalesAchievement->update([
                'outlet_id' => $validated['outlet_id'],
                'month' => $validated['month'],
                'year' => $validated['year'],
                'updated_by' => auth()->id(),
            ]);

            UpsellingSalesAchievementItem::where('achievement_id', $upsellingSalesAchievement->id)->delete();
            $this->syncItems($upsellingSalesAchievement, $validated['items']);

            DB::commit();

            $upsellingSalesAchievement->load(['outlet', 'items']);
            $this->writeActivityLog(
                $request,
                'upselling_sales_achievement',
                'update',
                $this->activityDescription('Memperbarui', $upsellingSalesAchievement),
                $oldSnapshot,
                $this->achievementSnapshot($upsellingSalesAchievement)
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('upselling-sales-achievement.index')
            ->with('success', 'Upselling Sales Achievement berhasil diperbarui.');
    }

    public function destroy(Request $request, UpsellingSalesAchievement $upsellingSalesAchievement)
    {
        $upsellingSalesAchievement->load(['outlet', 'items']);
        $oldSnapshot = $this->enrichDeleteSnapshot($this->achievementSnapshot($upsellingSalesAchievement));

        $upsellingSalesAchievement->delete();

        $this->writeActivityLog(
            $request,
            'upselling_sales_achievement',
            'delete',
            $this->activityDescription('Menghapus', $upsellingSalesAchievement),
            $oldSnapshot,
            null
        );

        return redirect()
            ->route('upselling-sales-achievement.index')
            ->with('success', 'Upselling Sales Achievement berhasil dihapus.');
    }

    public function searchItems(Request $request)
    {
        $validated = $request->validate([
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'q' => 'nullable|string|max:100',
        ]);

        $items = $this->service->searchPosItems(
            (int) $validated['outlet_id'],
            (string) ($validated['q'] ?? '')
        );

        return response()->json(['items' => $items]);
    }

    public function apiIndex(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $outletId = $request->get('outlet_id', '');
        $month = $request->get('month', '');
        $year = $request->get('year', '');
        $perPage = min(50, max(5, (int) $request->input('per_page', 15)));

        $query = UpsellingSalesAchievement::query()
            ->with(['outlet', 'creator', 'items'])
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('id');

        if ($search !== '') {
            $query->whereHas('outlet', function ($q) use ($search) {
                $q->where('nama_outlet', 'like', "%{$search}%");
            });
        }
        if ($outletId !== '') {
            $query->where('outlet_id', $outletId);
        }
        if ($month !== '') {
            $query->where('month', (int) $month);
        }
        if ($year !== '') {
            $query->where('year', (int) $year);
        }

        $records = $query->paginate($perPage);

        $records->getCollection()->transform(function (UpsellingSalesAchievement $record) {
            return $this->serializeListRecord($record);
        });

        return response()->json([
            'success' => true,
            'data' => $records->items(),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
            'outlets' => Outlet::where('status', 'A')->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
            'month_options' => $this->monthOptions(),
        ]);
    }

    public function apiCreateData(Request $request, ?int $id = null)
    {
        $record = null;
        $includeYear = null;

        if ($id !== null) {
            $record = UpsellingSalesAchievement::with(['outlet', 'items'])->findOrFail($id);
            $includeYear = (int) $record->year;
        }

        return response()->json([
            'success' => true,
            'record' => $record ? $this->serializeFormRecord($record) : null,
            'outlets' => Outlet::where('status', 'A')->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
            'month_options' => $this->monthOptions(),
            'year_options' => $this->yearOptions($includeYear),
        ]);
    }

    public function apiShow(int $id)
    {
        $record = UpsellingSalesAchievement::with(['outlet', 'creator', 'items'])->findOrFail($id);
        $detail = $this->service->buildDetailRows($record);

        return response()->json([
            'success' => true,
            'record' => $this->serializeFormRecord($record, true),
            'detail' => $detail,
            'month_label' => UpsellingSalesAchievementService::monthLabel((int) $record->month),
        ]);
    }

    public function apiStore(Request $request)
    {
        try {
            $achievement = $this->persistAchievement($request, null);

            return response()->json([
                'success' => true,
                'message' => 'Upselling Sales Achievement berhasil disimpan.',
                'id' => $achievement->id,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Validasi gagal.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function apiUpdate(Request $request, int $id)
    {
        $record = UpsellingSalesAchievement::findOrFail($id);

        try {
            $achievement = $this->persistAchievement($request, $record);

            return response()->json([
                'success' => true,
                'message' => 'Upselling Sales Achievement berhasil diperbarui.',
                'id' => $achievement->id,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Validasi gagal.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function apiDestroy(Request $request, int $id)
    {
        $record = UpsellingSalesAchievement::with(['outlet', 'items'])->findOrFail($id);
        $oldSnapshot = $this->enrichDeleteSnapshot($this->achievementSnapshot($record));

        $record->delete();

        $this->writeActivityLog(
            $request,
            'upselling_sales_achievement',
            'delete',
            $this->activityDescription('Menghapus', $record),
            $oldSnapshot,
            null
        );

        return response()->json([
            'success' => true,
            'message' => 'Upselling Sales Achievement berhasil dihapus.',
        ]);
    }

    public function apiSearchItems(Request $request)
    {
        return $this->searchItems($request);
    }

    private function persistAchievement(Request $request, ?UpsellingSalesAchievement $existing): UpsellingSalesAchievement
    {
        $validated = $this->validatePayload($request, $existing);

        $existsQuery = UpsellingSalesAchievement::where('outlet_id', $validated['outlet_id'])
            ->where('month', $validated['month'])
            ->where('year', $validated['year']);

        if ($existing) {
            $existsQuery->where('id', '!=', $existing->id);
        }

        if ($existsQuery->exists()) {
            throw ValidationException::withMessages([
                'month' => 'Data upselling untuk outlet, bulan, dan tahun ini sudah ada.',
            ]);
        }

        DB::beginTransaction();
        try {
            if ($existing) {
                $existing->load(['outlet', 'items']);
                $oldSnapshot = $this->achievementSnapshot($existing);

                $existing->update([
                    'outlet_id' => $validated['outlet_id'],
                    'month' => $validated['month'],
                    'year' => $validated['year'],
                    'updated_by' => auth()->id(),
                ]);

                UpsellingSalesAchievementItem::where('achievement_id', $existing->id)->delete();
                $this->syncItems($existing, $validated['items']);

                DB::commit();

                $existing->load(['outlet', 'items']);
                $this->writeActivityLog(
                    $request,
                    'upselling_sales_achievement',
                    'update',
                    $this->activityDescription('Memperbarui', $existing),
                    $oldSnapshot,
                    $this->achievementSnapshot($existing)
                );

                return $existing;
            }

            $achievement = UpsellingSalesAchievement::create([
                'outlet_id' => $validated['outlet_id'],
                'month' => $validated['month'],
                'year' => $validated['year'],
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->syncItems($achievement, $validated['items']);

            DB::commit();

            $achievement->load(['outlet', 'items']);
            $this->writeActivityLog(
                $request,
                'upselling_sales_achievement',
                'create',
                $this->activityDescription('Membuat', $achievement),
                null,
                $this->achievementSnapshot($achievement)
            );

            return $achievement;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function serializeListRecord(UpsellingSalesAchievement $record): array
    {
        $record->setAttribute(
            'achievement_percent',
            $this->service->computeAchievementPercent($record)
        );

        return [
            'id' => $record->id,
            'outlet_id' => $record->outlet_id,
            'outlet_name' => $record->outlet?->nama_outlet,
            'month' => (int) $record->month,
            'month_label' => UpsellingSalesAchievementService::monthLabel((int) $record->month),
            'year' => (int) $record->year,
            'created_at' => $record->created_at?->toIso8601String(),
            'created_by_name' => $record->creator?->nama_lengkap ?? $record->creator?->name,
            'achievement_percent' => (float) $record->achievement_percent,
            'items_count' => $record->items?->count() ?? 0,
        ];
    }

    private function serializeFormRecord(UpsellingSalesAchievement $record, bool $withCreator = false): array
    {
        $data = [
            'id' => $record->id,
            'outlet_id' => $record->outlet_id,
            'outlet_name' => $record->outlet?->nama_outlet,
            'month' => (int) $record->month,
            'year' => (int) $record->year,
            'created_at' => $record->created_at?->toIso8601String(),
            'items' => $record->items->map(fn ($item) => [
                'item_id' => (int) $item->item_id,
                'item_name' => $item->item_name,
                'category_label' => $item->category_label,
                'average_check' => (float) $item->average_check,
                'cover' => (int) $item->cover,
                'fb_revenue' => (float) $item->fb_revenue,
            ])->values()->all(),
        ];

        if ($withCreator) {
            $data['created_by_name'] = $record->creator?->nama_lengkap ?? $record->creator?->name;
        }

        return $data;
    }

    private function validatePayload(Request $request, ?UpsellingSalesAchievement $existing = null): array
    {
        $currentYear = (int) date('Y');
        $minYear = $currentYear;
        $requestedYear = (int) $request->input('year');

        if (
            $existing
            && $requestedYear === (int) $existing->year
            && (int) $existing->year < $currentYear
        ) {
            $minYear = (int) $existing->year;
        }

        return $request->validate([
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'month' => 'required|integer|min:1|max:12',
            'year' => "required|integer|min:{$minYear}|max:2100",
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer|exists:items,id',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.category_label' => 'nullable|string|max:255',
            'items.*.average_check' => 'required|numeric|min:0',
            'items.*.cover' => 'required|integer|min:1',
            'items.*.fb_revenue' => 'required|numeric|min:0',
        ]);
    }

    private function syncItems(UpsellingSalesAchievement $achievement, array $items): void
    {
        foreach ($items as $index => $item) {
            UpsellingSalesAchievementItem::create([
                'achievement_id' => $achievement->id,
                'item_id' => $item['item_id'],
                'item_name' => $item['item_name'],
                'category_label' => $item['category_label'] ?? null,
                'average_check' => $item['average_check'],
                'cover' => $item['cover'],
                'fb_revenue' => $item['fb_revenue'],
                'sort_order' => $index,
            ]);
        }
    }

    private function monthOptions(): array
    {
        return collect(range(1, 12))->map(fn ($m) => [
            'value' => $m,
            'label' => UpsellingSalesAchievementService::monthLabel($m),
        ])->all();
    }

    private function yearOptions(?int $includeYear = null): array
    {
        $current = (int) date('Y');
        $end = $current + 2;
        $years = range($current, $end);

        if ($includeYear !== null && $includeYear < $current && ! in_array($includeYear, $years, true)) {
            array_unshift($years, $includeYear);
        }

        return collect($years)->map(fn ($y) => [
            'value' => $y,
            'label' => (string) $y,
        ])->all();
    }

    private function achievementSnapshot(UpsellingSalesAchievement $achievement): array
    {
        return [
            'id' => $achievement->id,
            'outlet_id' => $achievement->outlet_id,
            'outlet_name' => $achievement->outlet?->nama_outlet,
            'month' => $achievement->month,
            'month_label' => UpsellingSalesAchievementService::monthLabel((int) $achievement->month),
            'year' => $achievement->year,
            'created_by' => $achievement->created_by,
            'updated_by' => $achievement->updated_by,
            'items' => $achievement->items->map(fn ($item) => [
                'item_id' => $item->item_id,
                'item_name' => $item->item_name,
                'category_label' => $item->category_label,
                'average_check' => (float) $item->average_check,
                'cover' => (int) $item->cover,
                'fb_revenue' => (float) $item->fb_revenue,
            ])->values()->all(),
        ];
    }

    private function activityDescription(string $action, UpsellingSalesAchievement $achievement): string
    {
        $outlet = $achievement->outlet?->nama_outlet ?? 'Outlet #'.$achievement->outlet_id;
        $period = UpsellingSalesAchievementService::monthLabel((int) $achievement->month).' '.$achievement->year;

        return "{$action} Upselling Sales Achievement: {$outlet} ({$period})";
    }
}
