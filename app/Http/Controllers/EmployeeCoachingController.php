<?php

namespace App\Http\Controllers;

use App\Http\Traits\WritesActivityLogTrait;
use App\Models\EmployeeCoaching;
use App\Models\User;
use App\Services\EmployeeCoachingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
