<?php

namespace App\Http\Controllers;

use App\Http\Traits\WritesActivityLogTrait;
use App\Models\NpdServiceCalibration;
use App\Models\Outlet;
use App\Models\User;
use App\Services\NpdServiceCalibrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NpdServiceCalibrationController extends Controller
{
    use WritesActivityLogTrait;

    public function __construct(
        private readonly NpdServiceCalibrationService $service
    ) {}

    private const SUPERADMIN_ROLE_ID = '5af56935b011a';

    public function index(Request $request): Response
    {
        $year = (int) $request->get('year', date('Y'));
        $month = (int) $request->get('month', date('n'));
        $month = max(1, min(12, $month));

        return Inertia::render('NpdServiceCalibration/Index', [
            'calendarEvents' => $this->enrichCalendarEvents(
                $this->service->calendarEvents($year, $month)
            ),
            'year' => $year,
            'month' => $month,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('NpdServiceCalibration/Form', [
            'record' => null,
            'scheduledDate' => $request->get('date', ''),
            'outlets' => Outlet::where('status', 'A')->where('is_outlet', 1)->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateSchedulePayload($request);
        $outlet = Outlet::findOrFail($validated['outlet_id']);
        $conductor = User::where('id', $validated['conductor_id'])->where('status', 'A')->firstOrFail();

        DB::beginTransaction();
        try {
            $calibration = NpdServiceCalibration::create([
                'outlet_id' => $outlet->id_outlet,
                'outlet_name' => (string) $outlet->nama_outlet,
                'scheduled_date' => $validated['scheduled_date'],
                'conductor_id' => $conductor->id,
                'conductor_name' => (string) $conductor->nama_lengkap,
                'status' => 'scheduled',
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->service->syncProducts($calibration, $validated['products']);
            $calibration->load('products');
            $this->service->notifyConductor($calibration);

            DB::commit();

            $this->writeActivityLog(
                $request,
                'npd_service_calibration',
                'create',
                $this->activityDescription('Membuat jadwal', $calibration),
                null,
                $this->service->snapshot($calibration)
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('npd-service-calibration.index', [
                'year' => date('Y', strtotime($validated['scheduled_date'])),
                'month' => date('n', strtotime($validated['scheduled_date'])),
            ])
            ->with('success', 'Jadwal NPD Service Calibration berhasil dibuat.');
    }

    public function show(NpdServiceCalibration $npdServiceCalibration): Response
    {
        $npdServiceCalibration->load(['products', 'participants.results', 'creator', 'conductor']);

        $canConduct = $this->canConduct($npdServiceCalibration);
        $conductPayload = $npdServiceCalibration->status === 'completed'
            ? $this->service->buildConductPayload($npdServiceCalibration)
            : null;

        return Inertia::render('NpdServiceCalibration/Show', [
            'record' => $npdServiceCalibration,
            'parameterOptions' => $this->service->parameterOptions(),
            'canConduct' => $canConduct,
            'canDelete' => $this->canDeleteCalibration($npdServiceCalibration),
            'conductPayload' => $conductPayload,
        ]);
    }

    public function edit(NpdServiceCalibration $npdServiceCalibration): Response
    {
        $this->ensureEditable($npdServiceCalibration);
        $npdServiceCalibration->load('products');

        return Inertia::render('NpdServiceCalibration/Form', [
            'record' => $npdServiceCalibration,
            'scheduledDate' => $npdServiceCalibration->scheduled_date?->format('Y-m-d') ?? '',
            'outlets' => Outlet::where('status', 'A')->where('is_outlet', 1)->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
        ]);
    }

    public function update(Request $request, NpdServiceCalibration $npdServiceCalibration)
    {
        $this->ensureEditable($npdServiceCalibration);
        $validated = $this->validateSchedulePayload($request, $npdServiceCalibration);
        $outlet = Outlet::findOrFail($validated['outlet_id']);
        $conductor = User::where('id', $validated['conductor_id'])->where('status', 'A')->firstOrFail();

        $oldConductorId = (int) $npdServiceCalibration->conductor_id;
        $oldSnapshot = $this->service->snapshot($npdServiceCalibration->load('products'));

        DB::beginTransaction();
        try {
            $npdServiceCalibration->update([
                'outlet_id' => $outlet->id_outlet,
                'outlet_name' => (string) $outlet->nama_outlet,
                'scheduled_date' => $validated['scheduled_date'],
                'conductor_id' => $conductor->id,
                'conductor_name' => (string) $conductor->nama_lengkap,
                'notes' => $validated['notes'] ?? null,
                'updated_by' => auth()->id(),
            ]);

            $this->service->syncProducts($npdServiceCalibration, $validated['products']);
            $npdServiceCalibration->load('products');

            if ((int) $conductor->id !== $oldConductorId) {
                $this->service->notifyConductor($npdServiceCalibration);
            }

            DB::commit();

            $this->writeActivityLog(
                $request,
                'npd_service_calibration',
                'update',
                $this->activityDescription('Memperbarui jadwal', $npdServiceCalibration),
                $oldSnapshot,
                $this->service->snapshot($npdServiceCalibration)
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('npd-service-calibration.show', $npdServiceCalibration->id)
            ->with('success', 'Jadwal NPD Service Calibration berhasil diperbarui.');
    }

    public function destroy(Request $request, NpdServiceCalibration $npdServiceCalibration)
    {
        $this->ensureDeletable($npdServiceCalibration);
        $oldSnapshot = $this->enrichDeleteSnapshot($this->service->snapshot($npdServiceCalibration->load('products')));
        $npdServiceCalibration->delete();

        $this->writeActivityLog(
            $request,
            'npd_service_calibration',
            'delete',
            $this->activityDescription('Menghapus jadwal', $npdServiceCalibration),
            $oldSnapshot,
            null
        );

        return redirect()
            ->route('npd-service-calibration.index')
            ->with('success', 'Jadwal NPD Service Calibration berhasil dihapus.');
    }

    public function conduct(NpdServiceCalibration $npdServiceCalibration): Response
    {
        $this->ensureConductable($npdServiceCalibration);
        $npdServiceCalibration->load(['products', 'participants']);

        $existing = $npdServiceCalibration->status === 'completed'
            ? $this->service->buildConductPayload($npdServiceCalibration)
            : ['participants' => [], 'results' => []];

        return Inertia::render('NpdServiceCalibration/Conduct', [
            'record' => $npdServiceCalibration,
            'parameterOptions' => $this->service->parameterOptions(),
            'initialParticipants' => $existing['participants'],
            'initialResults' => $existing['results'],
        ]);
    }

    public function storeConduct(Request $request, NpdServiceCalibration $npdServiceCalibration)
    {
        $this->ensureConductable($npdServiceCalibration);
        $validated = $this->validateConductPayload($request, $npdServiceCalibration);

        DB::beginTransaction();
        try {
            if ($npdServiceCalibration->status === 'scheduled') {
                $npdServiceCalibration->update([
                    'status' => 'in_progress',
                    'updated_by' => auth()->id(),
                ]);
            }

            $this->service->saveConduct(
                $npdServiceCalibration,
                $validated['participants'],
                $validated['results']
            );

            DB::commit();

            $npdServiceCalibration->load(['products', 'participants']);
            $this->writeActivityLog(
                $request,
                'npd_service_calibration',
                'update',
                $this->activityDescription('Menyelesaikan conduct', $npdServiceCalibration),
                null,
                $this->service->snapshot($npdServiceCalibration)
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('npd-service-calibration.show', $npdServiceCalibration->id)
            ->with('success', 'Hasil calibration berhasil disimpan.');
    }

    public function searchConductors(Request $request)
    {
        $validated = $request->validate(['q' => 'nullable|string|max:100']);

        return response()->json([
            'users' => $this->service->searchConductors((string) ($validated['q'] ?? '')),
        ]);
    }

    public function searchParticipants(Request $request)
    {
        $validated = $request->validate(['q' => 'nullable|string|max:100']);

        return response()->json([
            'users' => $this->service->searchParticipants((string) ($validated['q'] ?? '')),
        ]);
    }

    public function searchProducts(Request $request)
    {
        $validated = $request->validate([
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'q' => 'nullable|string|max:100',
            'exclude_ids' => 'nullable|array',
            'exclude_ids.*' => 'integer',
        ]);

        $items = $this->service->searchProducts(
            (int) $validated['outlet_id'],
            (string) ($validated['q'] ?? ''),
            $validated['exclude_ids'] ?? []
        );

        return response()->json(['items' => $items]);
    }

    public function apiIndex(Request $request)
    {
        $year = (int) $request->get('year', date('Y'));
        $month = max(1, min(12, (int) $request->get('month', date('n'))));

        return response()->json([
            'success' => true,
            'calendar_events' => $this->enrichCalendarEvents(
                $this->service->calendarEvents($year, $month)
            ),
            'year' => $year,
            'month' => $month,
        ]);
    }

    public function apiCreateData(?int $id = null)
    {
        $record = null;
        if ($id !== null) {
            $record = NpdServiceCalibration::with('products')->findOrFail($id);
            try {
                $this->ensureEditable($record);
            } catch (ValidationException $e) {
                return response()->json([
                    'success' => false,
                    'message' => collect($e->errors())->flatten()->first() ?? 'Data tidak dapat diubah.',
                    'errors' => $e->errors(),
                ], 422);
            }
        }

        return response()->json([
            'success' => true,
            'record' => $record ? $this->serializeDetailRecord($record) : null,
            'outlets' => Outlet::where('status', 'A')->where('is_outlet', 1)->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
        ]);
    }

    public function apiShow(int $id)
    {
        $calibration = NpdServiceCalibration::with(['products', 'participants.results', 'creator', 'conductor'])->findOrFail($id);
        $canConduct = $this->canConduct($calibration);
        $conductPayload = $calibration->status === 'completed'
            ? $this->service->buildConductPayload($calibration)
            : null;

        return response()->json([
            'success' => true,
            'record' => $this->serializeDetailRecord($calibration, true),
            'parameter_options' => $this->service->parameterOptions(),
            'can_conduct' => $canConduct,
            'conduct_payload' => $conductPayload,
        ]);
    }

    public function apiStore(Request $request)
    {
        try {
            $calibration = $this->persistSchedule($request, null);

            return response()->json([
                'success' => true,
                'message' => 'Jadwal NPD Service Calibration berhasil dibuat.',
                'id' => $calibration->id,
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
        $record = NpdServiceCalibration::findOrFail($id);

        try {
            $this->ensureEditable($record);
            $calibration = $this->persistSchedule($request, $record);

            return response()->json([
                'success' => true,
                'message' => 'Jadwal NPD Service Calibration berhasil diperbarui.',
                'id' => $calibration->id,
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
        $record = NpdServiceCalibration::with('products')->findOrFail($id);
        $this->ensureDeletable($record);
        $oldSnapshot = $this->enrichDeleteSnapshot($this->service->snapshot($record));
        $record->delete();

        $this->writeActivityLog(
            $request,
            'npd_service_calibration',
            'delete',
            $this->activityDescription('Menghapus jadwal', $record),
            $oldSnapshot,
            null
        );

        return response()->json([
            'success' => true,
            'message' => 'Jadwal NPD Service Calibration berhasil dihapus.',
        ]);
    }

    public function apiConductData(int $id)
    {
        $calibration = NpdServiceCalibration::with(['products', 'participants'])->findOrFail($id);

        if ($calibration->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Calibration yang dibatalkan tidak dapat dilakukan.',
            ], 422);
        }

        if (! $this->canConduct($calibration)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk conduct calibration ini.',
            ], 403);
        }

        $existing = $calibration->status === 'completed'
            ? $this->service->buildConductPayload($calibration)
            : ['participants' => [], 'results' => []];

        return response()->json([
            'success' => true,
            'record' => $this->serializeDetailRecord($calibration),
            'parameter_options' => $this->service->parameterOptions(),
            'initial_participants' => $existing['participants'],
            'initial_results' => $existing['results'],
        ]);
    }

    public function apiStoreConduct(Request $request, int $id)
    {
        $calibration = NpdServiceCalibration::findOrFail($id);

        if ($calibration->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Calibration yang dibatalkan tidak dapat dilakukan.',
            ], 422);
        }

        if (! $this->canConduct($calibration)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk conduct calibration ini.',
            ], 403);
        }

        try {
            $validated = $this->validateConductPayload($request, $calibration);

            DB::beginTransaction();
            try {
                if ($calibration->status === 'scheduled') {
                    $calibration->update([
                        'status' => 'in_progress',
                        'updated_by' => auth()->id(),
                    ]);
                }

                $this->service->saveConduct(
                    $calibration,
                    $validated['participants'],
                    $validated['results']
                );

                DB::commit();

                $calibration->load(['products', 'participants']);
                $this->writeActivityLog(
                    $request,
                    'npd_service_calibration',
                    'update',
                    $this->activityDescription('Menyelesaikan conduct', $calibration),
                    null,
                    $this->service->snapshot($calibration)
                );
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            return response()->json([
                'success' => true,
                'message' => 'Hasil calibration berhasil disimpan.',
                'id' => $calibration->id,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Validasi gagal.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function apiSearchConductors(Request $request)
    {
        return $this->searchConductors($request);
    }

    public function apiSearchParticipants(Request $request)
    {
        return $this->searchParticipants($request);
    }

    public function apiSearchProducts(Request $request)
    {
        return $this->searchProducts($request);
    }

    private function persistSchedule(Request $request, ?NpdServiceCalibration $existing): NpdServiceCalibration
    {
        $validated = $this->validateSchedulePayload($request, $existing);
        $outlet = Outlet::findOrFail($validated['outlet_id']);
        $conductor = User::where('id', $validated['conductor_id'])->where('status', 'A')->firstOrFail();

        DB::beginTransaction();
        try {
            if ($existing) {
                $oldConductorId = (int) $existing->conductor_id;
                $oldSnapshot = $this->service->snapshot($existing->load('products'));

                $existing->update([
                    'outlet_id' => $outlet->id_outlet,
                    'outlet_name' => (string) $outlet->nama_outlet,
                    'scheduled_date' => $validated['scheduled_date'],
                    'conductor_id' => $conductor->id,
                    'conductor_name' => (string) $conductor->nama_lengkap,
                    'notes' => $validated['notes'] ?? null,
                    'updated_by' => auth()->id(),
                ]);

                $this->service->syncProducts($existing, $validated['products']);
                $existing->load('products');

                if ((int) $conductor->id !== $oldConductorId) {
                    $this->service->notifyConductor($existing);
                }

                DB::commit();

                $this->writeActivityLog(
                    $request,
                    'npd_service_calibration',
                    'update',
                    $this->activityDescription('Memperbarui jadwal', $existing),
                    $oldSnapshot,
                    $this->service->snapshot($existing)
                );

                return $existing;
            }

            $calibration = NpdServiceCalibration::create([
                'outlet_id' => $outlet->id_outlet,
                'outlet_name' => (string) $outlet->nama_outlet,
                'scheduled_date' => $validated['scheduled_date'],
                'conductor_id' => $conductor->id,
                'conductor_name' => (string) $conductor->nama_lengkap,
                'status' => 'scheduled',
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->service->syncProducts($calibration, $validated['products']);
            $calibration->load('products');
            $this->service->notifyConductor($calibration);

            DB::commit();

            $this->writeActivityLog(
                $request,
                'npd_service_calibration',
                'create',
                $this->activityDescription('Membuat jadwal', $calibration),
                null,
                $this->service->snapshot($calibration)
            );

            return $calibration;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDetailRecord(NpdServiceCalibration $record, bool $withMeta = false): array
    {
        $data = [
            'id' => $record->id,
            'outlet_id' => $record->outlet_id,
            'outlet_name' => $record->outlet_name,
            'scheduled_date' => $record->scheduled_date?->format('Y-m-d'),
            'conductor_id' => $record->conductor_id,
            'conductor_name' => $record->conductor_name,
            'status' => $record->status,
            'notes' => $record->notes,
            'products' => $record->products->map(fn ($p) => [
                'id' => $p->id,
                'item_id' => $p->item_id,
                'item_name' => $p->item_name,
                'category_name' => $p->category_name,
                'sub_category_name' => $p->sub_category_name,
            ])->values()->all(),
        ];

        if ($withMeta) {
            $data['created_at'] = $record->created_at?->toIso8601String();
            $data['created_by'] = $record->created_by;
            $data['created_by_name'] = $record->creator?->nama_lengkap ?? $record->creator?->name;
            $data['can_delete'] = $this->canDeleteCalibration($record);
        }

        return $data;
    }

    private function validateSchedulePayload(Request $request, ?NpdServiceCalibration $existing = null): array
    {
        $validated = $request->validate([
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'scheduled_date' => 'required|date',
            'conductor_id' => 'required|integer|exists:users,id',
            'notes' => 'nullable|string|max:2000',
            'products' => 'required|array|min:1',
            'products.*.item_id' => 'required|integer|exists:items,id',
            'products.*.item_name' => 'required|string|max:255',
            'products.*.category_name' => 'nullable|string|max:255',
            'products.*.sub_category_name' => 'nullable|string|max:255',
        ], [
            'products.required' => 'Pilih minimal satu product.',
            'products.min' => 'Pilih minimal satu product.',
        ]);

        $today = now()->toDateString();
        $scheduledDate = $validated['scheduled_date'];
        $existingDate = $existing?->scheduled_date?->format('Y-m-d');

        if ($scheduledDate < $today && $scheduledDate !== $existingDate) {
            throw ValidationException::withMessages([
                'scheduled_date' => 'Tanggal calibration tidak boleh sebelum hari ini.',
            ]);
        }

        return $validated;
    }

    private function validateConductPayload(Request $request, NpdServiceCalibration $calibration): array
    {
        $calibration->load('products');
        $productIds = $calibration->products->pluck('id')->all();
        $parameterRules = [];
        foreach ($this->service->parameterCodes() as $code) {
            $parameterRules["results.*.{$code}"] = ['required', Rule::in(['C', 'NC'])];
        }

        $validated = $request->validate(array_merge([
            'participants' => 'required|array|min:1',
            'participants.*.user_id' => 'required|integer|exists:users,id',
            'results' => 'required|array|min:1',
            'results.*.user_id' => 'required|integer',
            'results.*.calibration_product_id' => ['required', 'integer', Rule::in($productIds)],
        ], $parameterRules), [
            'participants.required' => 'Tambahkan minimal satu user yang di-calibration.',
            'results.required' => 'Lengkapi minimal satu product untuk setiap user yang di-calibration.',
        ]);

        $participantIds = collect($validated['participants'])->pluck('user_id')->map(fn ($id) => (int) $id)->unique()->values();
        $seenPairs = [];

        foreach ($validated['results'] as $index => $row) {
            $userId = (int) $row['user_id'];
            $productId = (int) $row['calibration_product_id'];
            $pairKey = "{$userId}_{$productId}";

            if (! $participantIds->contains($userId)) {
                throw ValidationException::withMessages([
                    'results' => 'Data result mengandung user yang tidak terdaftar sebagai participant.',
                ]);
            }

            if (isset($seenPairs[$pairKey])) {
                throw ValidationException::withMessages([
                    'results' => 'Terdapat data product ganda untuk user yang sama.',
                ]);
            }
            $seenPairs[$pairKey] = true;
        }

        foreach ($participantIds as $participantId) {
            $productCount = collect($validated['results'])
                ->where('user_id', $participantId)
                ->count();

            if ($productCount < 1) {
                throw ValidationException::withMessages([
                    'results' => 'Setiap user wajib memiliki minimal satu product yang di-calibration.',
                ]);
            }
        }

        return $validated;
    }

    private function isSuperAdmin(): bool
    {
        return (string) (auth()->user()?->id_role ?? '') === self::SUPERADMIN_ROLE_ID;
    }

    private function canDeleteCalibration(NpdServiceCalibration|int|null $calibrationOrCreatedBy): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $createdBy = $calibrationOrCreatedBy instanceof NpdServiceCalibration
            ? (int) $calibrationOrCreatedBy->created_by
            : (int) $calibrationOrCreatedBy;

        return $createdBy > 0 && (int) auth()->id() === $createdBy;
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    private function enrichCalendarEvents(array $events): array
    {
        return array_map(function (array $event) {
            $createdBy = (int) ($event['extendedProps']['created_by'] ?? 0);
            $event['extendedProps']['can_delete'] = $this->canDeleteCalibration($createdBy);

            return $event;
        }, $events);
    }

    private function ensureDeletable(NpdServiceCalibration $calibration): void
    {
        if (! $this->canDeleteCalibration($calibration)) {
            abort(403, 'Anda tidak memiliki akses untuk menghapus jadwal calibration ini.');
        }
    }

    private function ensureEditable(NpdServiceCalibration $calibration): void
    {
        if (in_array($calibration->status, ['completed'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Calibration yang sudah selesai tidak dapat diubah.',
            ]);
        }
    }

    private function canConduct(NpdServiceCalibration $calibration): bool
    {
        if ($calibration->status === 'cancelled') {
            return false;
        }

        return (int) auth()->id() === (int) $calibration->conductor_id
            || (int) auth()->id() === (int) $calibration->created_by;
    }

    private function ensureConductable(NpdServiceCalibration $calibration): void
    {
        if ($calibration->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => 'Calibration yang dibatalkan tidak dapat dilakukan.',
            ]);
        }

        if (! $this->canConduct($calibration)) {
            abort(403, 'Anda tidak memiliki akses untuk conduct calibration ini.');
        }
    }

    private function activityDescription(string $action, NpdServiceCalibration $calibration): string
    {
        $date = $calibration->scheduled_date?->format('d M Y') ?? '-';

        return "{$action} NPD Service Calibration: {$calibration->outlet_name} ({$date})";
    }
}
