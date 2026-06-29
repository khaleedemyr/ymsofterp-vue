<?php

namespace App\Http\Controllers;

use App\Http\Traits\WritesActivityLogTrait;
use App\Models\FbProductCalibration;
use App\Models\Outlet;
use App\Models\User;
use App\Services\FbProductCalibrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FbProductCalibrationController extends Controller
{
    use WritesActivityLogTrait;

    public function __construct(
        private readonly FbProductCalibrationService $service
    ) {}

    public function index(Request $request): Response
    {
        $year = (int) $request->get('year', date('Y'));
        $month = (int) $request->get('month', date('n'));
        $month = max(1, min(12, $month));

        return Inertia::render('FbProductCalibration/Index', [
            'calendarEvents' => $this->service->calendarEvents($year, $month),
            'year' => $year,
            'month' => $month,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('FbProductCalibration/Form', [
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
            $calibration = FbProductCalibration::create([
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
                'fb_product_calibration',
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
            ->route('fb-product-calibration.index', [
                'year' => date('Y', strtotime($validated['scheduled_date'])),
                'month' => date('n', strtotime($validated['scheduled_date'])),
            ])
            ->with('success', 'Jadwal F&B Product Calibration berhasil dibuat.');
    }

    public function show(FbProductCalibration $fbProductCalibration): Response
    {
        $fbProductCalibration->load(['products', 'participants.results', 'creator', 'conductor']);

        $canConduct = $this->canConduct($fbProductCalibration);
        $conductPayload = $fbProductCalibration->status === 'completed'
            ? $this->service->buildConductPayload($fbProductCalibration)
            : null;

        return Inertia::render('FbProductCalibration/Show', [
            'record' => $fbProductCalibration,
            'parameterOptions' => $this->service->parameterOptions(),
            'canConduct' => $canConduct,
            'conductPayload' => $conductPayload,
        ]);
    }

    public function edit(FbProductCalibration $fbProductCalibration): Response
    {
        $this->ensureEditable($fbProductCalibration);
        $fbProductCalibration->load('products');

        return Inertia::render('FbProductCalibration/Form', [
            'record' => $fbProductCalibration,
            'scheduledDate' => $fbProductCalibration->scheduled_date?->format('Y-m-d') ?? '',
            'outlets' => Outlet::where('status', 'A')->where('is_outlet', 1)->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
        ]);
    }

    public function update(Request $request, FbProductCalibration $fbProductCalibration)
    {
        $this->ensureEditable($fbProductCalibration);
        $validated = $this->validateSchedulePayload($request, $fbProductCalibration);
        $outlet = Outlet::findOrFail($validated['outlet_id']);
        $conductor = User::where('id', $validated['conductor_id'])->where('status', 'A')->firstOrFail();

        $oldConductorId = (int) $fbProductCalibration->conductor_id;
        $oldSnapshot = $this->service->snapshot($fbProductCalibration->load('products'));

        DB::beginTransaction();
        try {
            $fbProductCalibration->update([
                'outlet_id' => $outlet->id_outlet,
                'outlet_name' => (string) $outlet->nama_outlet,
                'scheduled_date' => $validated['scheduled_date'],
                'conductor_id' => $conductor->id,
                'conductor_name' => (string) $conductor->nama_lengkap,
                'notes' => $validated['notes'] ?? null,
                'updated_by' => auth()->id(),
            ]);

            $this->service->syncProducts($fbProductCalibration, $validated['products']);
            $fbProductCalibration->load('products');

            if ((int) $conductor->id !== $oldConductorId) {
                $this->service->notifyConductor($fbProductCalibration);
            }

            DB::commit();

            $this->writeActivityLog(
                $request,
                'fb_product_calibration',
                'update',
                $this->activityDescription('Memperbarui jadwal', $fbProductCalibration),
                $oldSnapshot,
                $this->service->snapshot($fbProductCalibration)
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('fb-product-calibration.show', $fbProductCalibration->id)
            ->with('success', 'Jadwal F&B Product Calibration berhasil diperbarui.');
    }

    public function destroy(Request $request, FbProductCalibration $fbProductCalibration)
    {
        $oldSnapshot = $this->enrichDeleteSnapshot($this->service->snapshot($fbProductCalibration->load('products')));
        $fbProductCalibration->delete();

        $this->writeActivityLog(
            $request,
            'fb_product_calibration',
            'delete',
            $this->activityDescription('Menghapus jadwal', $fbProductCalibration),
            $oldSnapshot,
            null
        );

        return redirect()
            ->route('fb-product-calibration.index')
            ->with('success', 'Jadwal F&B Product Calibration berhasil dihapus.');
    }

    public function conduct(FbProductCalibration $fbProductCalibration): Response
    {
        $this->ensureConductable($fbProductCalibration);
        $fbProductCalibration->load(['products', 'participants']);

        $existing = $fbProductCalibration->status === 'completed'
            ? $this->service->buildConductPayload($fbProductCalibration)
            : ['participants' => [], 'results' => []];

        return Inertia::render('FbProductCalibration/Conduct', [
            'record' => $fbProductCalibration,
            'parameterOptions' => $this->service->parameterOptions(),
            'initialParticipants' => $existing['participants'],
            'initialResults' => $existing['results'],
        ]);
    }

    public function storeConduct(Request $request, FbProductCalibration $fbProductCalibration)
    {
        $this->ensureConductable($fbProductCalibration);
        $validated = $this->validateConductPayload($request, $fbProductCalibration);

        DB::beginTransaction();
        try {
            if ($fbProductCalibration->status === 'scheduled') {
                $fbProductCalibration->update([
                    'status' => 'in_progress',
                    'updated_by' => auth()->id(),
                ]);
            }

            $this->service->saveConduct(
                $fbProductCalibration,
                $validated['participants'],
                $validated['results']
            );

            DB::commit();

            $fbProductCalibration->load(['products', 'participants']);
            $this->writeActivityLog(
                $request,
                'fb_product_calibration',
                'update',
                $this->activityDescription('Menyelesaikan conduct', $fbProductCalibration),
                null,
                $this->service->snapshot($fbProductCalibration)
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('fb-product-calibration.show', $fbProductCalibration->id)
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
            'calendar_events' => $this->service->calendarEvents($year, $month),
            'year' => $year,
            'month' => $month,
        ]);
    }

    public function apiCreateData(?int $id = null)
    {
        $record = null;
        if ($id !== null) {
            $record = FbProductCalibration::with('products')->findOrFail($id);
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
        $calibration = FbProductCalibration::with(['products', 'participants.results', 'creator', 'conductor'])->findOrFail($id);
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
                'message' => 'Jadwal F&B Product Calibration berhasil dibuat.',
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
        $record = FbProductCalibration::findOrFail($id);

        try {
            $this->ensureEditable($record);
            $calibration = $this->persistSchedule($request, $record);

            return response()->json([
                'success' => true,
                'message' => 'Jadwal F&B Product Calibration berhasil diperbarui.',
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
        $record = FbProductCalibration::with('products')->findOrFail($id);
        $oldSnapshot = $this->enrichDeleteSnapshot($this->service->snapshot($record));
        $record->delete();

        $this->writeActivityLog(
            $request,
            'fb_product_calibration',
            'delete',
            $this->activityDescription('Menghapus jadwal', $record),
            $oldSnapshot,
            null
        );

        return response()->json([
            'success' => true,
            'message' => 'Jadwal F&B Product Calibration berhasil dihapus.',
        ]);
    }

    public function apiConductData(int $id)
    {
        $calibration = FbProductCalibration::with(['products', 'participants'])->findOrFail($id);

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
        $calibration = FbProductCalibration::findOrFail($id);

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
                    'fb_product_calibration',
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

    private function persistSchedule(Request $request, ?FbProductCalibration $existing): FbProductCalibration
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
                    'fb_product_calibration',
                    'update',
                    $this->activityDescription('Memperbarui jadwal', $existing),
                    $oldSnapshot,
                    $this->service->snapshot($existing)
                );

                return $existing;
            }

            $calibration = FbProductCalibration::create([
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
                'fb_product_calibration',
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
    private function serializeDetailRecord(FbProductCalibration $record, bool $withMeta = false): array
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
            $data['created_by_name'] = $record->creator?->nama_lengkap ?? $record->creator?->name;
        }

        return $data;
    }

    private function validateSchedulePayload(Request $request, ?FbProductCalibration $existing = null): array
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

    private function validateConductPayload(Request $request, FbProductCalibration $calibration): array
    {
        $calibration->load('products');
        $productIds = $calibration->products->pluck('id')->all();
        $parameterRules = [];
        foreach (FbProductCalibrationService::PARAMETER_CODES as $code) {
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
            'results.required' => 'Lengkapi parameter calibration untuk setiap product.',
        ]);

        $participantIds = collect($validated['participants'])->pluck('user_id')->map(fn ($id) => (int) $id)->unique()->values();
        $expectedRows = $participantIds->count() * count($productIds);

        if (count($validated['results']) < $expectedRows) {
            throw ValidationException::withMessages([
                'results' => 'Lengkapi semua parameter C/NC untuk setiap user dan product.',
            ]);
        }

        foreach ($validated['results'] as $row) {
            if (! $participantIds->contains((int) $row['user_id'])) {
                throw ValidationException::withMessages([
                    'results' => 'Data result mengandung user yang tidak terdaftar sebagai participant.',
                ]);
            }
        }

        return $validated;
    }

    private function ensureEditable(FbProductCalibration $calibration): void
    {
        if (in_array($calibration->status, ['completed'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Calibration yang sudah selesai tidak dapat diubah.',
            ]);
        }
    }

    private function canConduct(FbProductCalibration $calibration): bool
    {
        if ($calibration->status === 'cancelled') {
            return false;
        }

        return (int) auth()->id() === (int) $calibration->conductor_id
            || (int) auth()->id() === (int) $calibration->created_by;
    }

    private function ensureConductable(FbProductCalibration $calibration): void
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

    private function activityDescription(string $action, FbProductCalibration $calibration): string
    {
        $date = $calibration->scheduled_date?->format('d M Y') ?? '-';

        return "{$action} F&B Product Calibration: {$calibration->outlet_name} ({$date})";
    }
}
