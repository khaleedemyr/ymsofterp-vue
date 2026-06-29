<?php

namespace App\Http\Controllers;

use App\Http\Traits\WritesActivityLogTrait;
use App\Models\EmployeeCoaching;
use App\Models\User;
use App\Services\EmployeeCoachingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeCoachingController extends Controller
{
    use WritesActivityLogTrait;

    public function __construct(
        private readonly EmployeeCoachingService $service
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->get('search', ''));

        $query = EmployeeCoaching::query()
            ->with(['creator'])
            ->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('employee_name', 'like', "%{$search}%")
                    ->orWhere('outlet_name', 'like', "%{$search}%")
                    ->orWhere('jabatan_name', 'like', "%{$search}%");
            });
        }

        $records = $query->paginate(15)->withQueryString();

        return Inertia::render('EmployeeCoaching/Index', [
            'records' => $records,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('EmployeeCoaching/Form', [
            'record' => null,
            'concernOptions' => $this->service->concernOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $employee = $this->resolveEmployee((int) $validated['employee_id']);

        DB::beginTransaction();
        try {
            $coaching = EmployeeCoaching::create([
                ...$this->employeeSnapshot($employee),
                'performance_description' => $validated['performance_description'] ?? null,
                'action_taken' => $validated['action_taken'] ?? null,
                'action_due_date' => $validated['action_due_date'] ?? null,
                'performance_review_plan_date' => $validated['performance_review_plan_date'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->service->syncConcerns($coaching, $validated['concerns']);

            DB::commit();

            $coaching->load('concerns');
            $this->writeActivityLog(
                $request,
                'employee_coaching',
                'create',
                $this->activityDescription('Membuat', $coaching),
                null,
                $this->service->snapshot($coaching)
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('employee-coaching.index')
            ->with('success', 'Employee Coaching berhasil disimpan.');
    }

    public function show(EmployeeCoaching $employeeCoaching): Response
    {
        $employeeCoaching->load(['creator', 'concerns']);

        return Inertia::render('EmployeeCoaching/Show', [
            'record' => $employeeCoaching,
            'concernOptions' => $this->service->concernOptions(),
        ]);
    }

    public function edit(EmployeeCoaching $employeeCoaching): Response
    {
        $employeeCoaching->load(['concerns']);

        return Inertia::render('EmployeeCoaching/Form', [
            'record' => $employeeCoaching,
            'concernOptions' => $this->service->concernOptions(),
        ]);
    }

    public function update(Request $request, EmployeeCoaching $employeeCoaching)
    {
        $validated = $this->validatePayload($request);
        $employee = $this->resolveEmployee((int) $validated['employee_id']);

        DB::beginTransaction();
        try {
            $employeeCoaching->load('concerns');
            $oldSnapshot = $this->service->snapshot($employeeCoaching);

            $employeeCoaching->update([
                ...$this->employeeSnapshot($employee),
                'performance_description' => $validated['performance_description'] ?? null,
                'action_taken' => $validated['action_taken'] ?? null,
                'action_due_date' => $validated['action_due_date'] ?? null,
                'performance_review_plan_date' => $validated['performance_review_plan_date'] ?? null,
                'updated_by' => auth()->id(),
            ]);

            $this->service->syncConcerns($employeeCoaching, $validated['concerns']);

            DB::commit();

            $employeeCoaching->load('concerns');
            $this->writeActivityLog(
                $request,
                'employee_coaching',
                'update',
                $this->activityDescription('Memperbarui', $employeeCoaching),
                $oldSnapshot,
                $this->service->snapshot($employeeCoaching)
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('employee-coaching.index')
            ->with('success', 'Employee Coaching berhasil diperbarui.');
    }

    public function destroy(Request $request, EmployeeCoaching $employeeCoaching)
    {
        $employeeCoaching->load('concerns');
        $oldSnapshot = $this->enrichDeleteSnapshot($this->service->snapshot($employeeCoaching));

        $employeeCoaching->delete();

        $this->writeActivityLog(
            $request,
            'employee_coaching',
            'delete',
            $this->activityDescription('Menghapus', $employeeCoaching),
            $oldSnapshot,
            null
        );

        return redirect()
            ->route('employee-coaching.index')
            ->with('success', 'Employee Coaching berhasil dihapus.');
    }

    public function searchEmployees(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
        ]);

        $employees = $this->service->searchEmployees((string) ($validated['q'] ?? ''));

        return response()->json(['employees' => $employees]);
    }

    public function apiIndex(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $perPage = min(50, max(5, (int) $request->input('per_page', 15)));

        $query = EmployeeCoaching::query()
            ->with(['creator'])
            ->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('employee_name', 'like', "%{$search}%")
                    ->orWhere('outlet_name', 'like', "%{$search}%")
                    ->orWhere('jabatan_name', 'like', "%{$search}%");
            });
        }

        $records = $query->paginate($perPage);
        $records->getCollection()->transform(fn (EmployeeCoaching $record) => $this->serializeListRecord($record));

        return response()->json([
            'success' => true,
            'data' => $records->items(),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ]);
    }

    public function apiCreateData(?int $id = null)
    {
        $record = null;
        if ($id !== null) {
            $record = EmployeeCoaching::with(['concerns'])->findOrFail($id);
        }

        return response()->json([
            'success' => true,
            'record' => $record ? $this->serializeDetailRecord($record) : null,
            'concern_options' => $this->service->concernOptions(),
        ]);
    }

    public function apiShow(int $id)
    {
        $record = EmployeeCoaching::with(['creator', 'concerns'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'record' => $this->serializeDetailRecord($record, true),
            'concern_options' => $this->service->concernOptions(),
        ]);
    }

    public function apiStore(Request $request)
    {
        try {
            $coaching = $this->persistCoaching($request, null);

            return response()->json([
                'success' => true,
                'message' => 'Employee Coaching berhasil disimpan.',
                'id' => $coaching->id,
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
        $record = EmployeeCoaching::findOrFail($id);

        try {
            $coaching = $this->persistCoaching($request, $record);

            return response()->json([
                'success' => true,
                'message' => 'Employee Coaching berhasil diperbarui.',
                'id' => $coaching->id,
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
        $record = EmployeeCoaching::with('concerns')->findOrFail($id);
        $oldSnapshot = $this->enrichDeleteSnapshot($this->service->snapshot($record));

        $record->delete();

        $this->writeActivityLog(
            $request,
            'employee_coaching',
            'delete',
            $this->activityDescription('Menghapus', $record),
            $oldSnapshot,
            null
        );

        return response()->json([
            'success' => true,
            'message' => 'Employee Coaching berhasil dihapus.',
        ]);
    }

    public function apiSearchEmployees(Request $request)
    {
        return $this->searchEmployees($request);
    }

    private function persistCoaching(Request $request, ?EmployeeCoaching $existing): EmployeeCoaching
    {
        $validated = $this->validatePayload($request);
        $employee = $this->resolveEmployee((int) $validated['employee_id']);

        DB::beginTransaction();
        try {
            if ($existing) {
                $existing->load('concerns');
                $oldSnapshot = $this->service->snapshot($existing);

                $existing->update([
                    ...$this->employeeSnapshot($employee),
                    'performance_description' => $validated['performance_description'] ?? null,
                    'action_taken' => $validated['action_taken'] ?? null,
                    'action_due_date' => $validated['action_due_date'] ?? null,
                    'performance_review_plan_date' => $validated['performance_review_plan_date'] ?? null,
                    'updated_by' => auth()->id(),
                ]);

                $this->service->syncConcerns($existing, $validated['concerns']);

                DB::commit();

                $existing->load('concerns');
                $this->writeActivityLog(
                    $request,
                    'employee_coaching',
                    'update',
                    $this->activityDescription('Memperbarui', $existing),
                    $oldSnapshot,
                    $this->service->snapshot($existing)
                );

                return $existing;
            }

            $coaching = EmployeeCoaching::create([
                ...$this->employeeSnapshot($employee),
                'performance_description' => $validated['performance_description'] ?? null,
                'action_taken' => $validated['action_taken'] ?? null,
                'action_due_date' => $validated['action_due_date'] ?? null,
                'performance_review_plan_date' => $validated['performance_review_plan_date'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->service->syncConcerns($coaching, $validated['concerns']);

            DB::commit();

            $coaching->load('concerns');
            $this->writeActivityLog(
                $request,
                'employee_coaching',
                'create',
                $this->activityDescription('Membuat', $coaching),
                null,
                $this->service->snapshot($coaching)
            );

            return $coaching;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeListRecord(EmployeeCoaching $record): array
    {
        return [
            'id' => $record->id,
            'employee_id' => $record->employee_id,
            'employee_name' => $record->employee_name,
            'outlet_name' => $record->outlet_name,
            'jabatan_name' => $record->jabatan_name,
            'performance_review_plan_date' => optional($record->performance_review_plan_date)?->format('Y-m-d'),
            'created_at' => $record->created_at?->toIso8601String(),
            'created_by_name' => $record->creator?->nama_lengkap ?? $record->creator?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDetailRecord(EmployeeCoaching $record, bool $withCreator = false): array
    {
        $data = [
            'id' => $record->id,
            'employee_id' => $record->employee_id,
            'employee_name' => $record->employee_name,
            'jabatan_id' => $record->jabatan_id,
            'jabatan_name' => $record->jabatan_name,
            'outlet_id' => $record->outlet_id,
            'outlet_name' => $record->outlet_name,
            'division_id' => $record->division_id,
            'division_name' => $record->division_name,
            'performance_description' => $record->performance_description,
            'action_taken' => $record->action_taken,
            'action_due_date' => optional($record->action_due_date)?->format('Y-m-d'),
            'performance_review_plan_date' => optional($record->performance_review_plan_date)?->format('Y-m-d'),
            'concerns' => $record->concerns->map(fn ($item) => [
                'id' => $item->id,
                'concern_code' => $item->concern_code,
                'other_label' => $item->other_label,
                'comment' => $item->comment,
            ])->values()->all(),
            'created_at' => $record->created_at?->toIso8601String(),
        ];

        if ($withCreator) {
            $data['created_by_name'] = $record->creator?->nama_lengkap ?? $record->creator?->name;
        }

        return $data;
    }

    private function validatePayload(Request $request): array
    {
        $concernCodes = EmployeeCoachingService::CONCERN_CODES;

        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:users,id',
            'performance_description' => 'nullable|string',
            'action_taken' => 'nullable|string',
            'action_due_date' => 'nullable|date',
            'performance_review_plan_date' => 'nullable|date',
            'concerns' => 'required|array|min:1',
            'concerns.*.code' => ['required', 'string', Rule::in($concernCodes)],
            'concerns.*.comment' => 'required|string|max:5000',
            'concerns.*.other_label' => 'nullable|string|max:255',
        ], [
            'concerns.required' => 'Pilih minimal satu Point of Concern.',
            'concerns.min' => 'Pilih minimal satu Point of Concern.',
            'concerns.*.comment.required' => 'Comment wajib diisi untuk setiap concern yang dipilih.',
        ]);

        foreach ($validated['concerns'] as $index => $concern) {
            if ($concern['code'] === 'other' && trim((string) ($concern['other_label'] ?? '')) === '') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "concerns.{$index}.other_label" => 'Isian Lain-Lain wajib diisi jika opsi Other dipilih.',
                ]);
            }
        }

        return $validated;
    }

    private function resolveEmployee(int $employeeId): User
    {
        return User::query()
            ->where('id', $employeeId)
            ->where('status', 'A')
            ->with(['jabatan', 'divisi', 'outlet'])
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function employeeSnapshot(User $employee): array
    {
        return [
            'employee_id' => $employee->id,
            'employee_name' => (string) $employee->nama_lengkap,
            'jabatan_id' => $employee->id_jabatan ? (int) $employee->id_jabatan : null,
            'jabatan_name' => (string) ($employee->jabatan?->nama_jabatan ?? '-'),
            'outlet_id' => $employee->id_outlet ? (int) $employee->id_outlet : null,
            'outlet_name' => (string) ($employee->outlet?->nama_outlet ?? '-'),
            'division_id' => $employee->division_id ? (int) $employee->division_id : null,
            'division_name' => (string) ($employee->divisi?->nama_divisi ?? '-'),
        ];
    }

    private function activityDescription(string $action, EmployeeCoaching $coaching): string
    {
        return "{$action} Employee Coaching: {$coaching->employee_name} ({$coaching->outlet_name})";
    }
}
