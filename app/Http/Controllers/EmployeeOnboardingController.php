<?php

namespace App\Http\Controllers;

use App\Models\EmployeeOnboarding;
use App\Models\EmployeeOnboardingWeekSubmission;
use App\Models\OnboardingTemplate;
use App\Models\Outlet;
use App\Services\EmployeeOnboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeOnboardingController extends Controller
{
    public function __construct(
        private readonly EmployeeOnboardingService $service
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->get('search', ''));

        $records = EmployeeOnboarding::query()
            ->with(['employee:id,nama_lengkap', 'creator:id,nama_lengkap'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('number', 'like', "%{$search}%")
                        ->orWhere('template_name', 'like', "%{$search}%")
                        ->orWhere('outlet_name', 'like', "%{$search}%")
                        ->orWhereHas('employee', fn ($e) => $e->where('nama_lengkap', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('EmployeeOnboarding/Index', [
            'records' => $records,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('EmployeeOnboarding/Form', [
            'templates' => OnboardingTemplate::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'total_weeks']),
            'outlets' => Outlet::where('status', 'A')->where('is_outlet', 1)->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'template_id' => 'required|integer|exists:onboarding_templates,id',
            'employee_user_id' => 'required|integer|exists:users,id',
            'outlet_id' => 'nullable|integer|exists:tbl_data_outlet,id_outlet',
            'start_date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'item_assignments' => 'nullable|array',
            'item_assignments.*.template_item_id' => 'required|integer',
            'item_assignments.*.assigned_pic_user_id' => 'nullable|integer|exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            $onboarding = $this->service->createInstance($validated);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()->route('employee-onboarding.show', $onboarding->id)->with('success', 'Onboarding karyawan berhasil dibuat.');
    }

    public function show(EmployeeOnboarding $employeeOnboarding): Response
    {
        return Inertia::render('EmployeeOnboarding/Show', [
            'record' => $this->service->serializeOnboarding($employeeOnboarding),
            'canApproveWeek' => $this->canApproveCurrentWeek($employeeOnboarding),
        ]);
    }

    public function destroy(EmployeeOnboarding $employeeOnboarding)
    {
        if (! $this->service->isSuperAdmin()) {
            abort(403, 'Hanya superadmin yang dapat menghapus onboarding.');
        }

        $employeeOnboarding->delete();

        return redirect()->route('employee-onboarding.index')->with('success', 'Onboarding berhasil dihapus.');
    }

    public function updateItems(Request $request, EmployeeOnboarding $employeeOnboarding)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.status' => 'nullable|in:pending,ongoing,done',
            'items.*.remark' => 'nullable|string|max:2000',
            'items.*.assigned_pic_user_id' => 'nullable|integer|exists:users,id',
        ]);

        try {
            $this->service->updateItems($employeeOnboarding, $validated['items']);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Checklist berhasil diperbarui.',
            'record' => $this->service->serializeOnboarding($employeeOnboarding->fresh()),
        ]);
    }

    public function bulkAssignPic(Request $request, EmployeeOnboarding $employeeOnboarding)
    {
        if (! $this->service->canManageOnboarding($employeeOnboarding)) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $validated = $request->validate([
            'week_number' => 'required|integer|min:1',
            'area_name' => 'nullable|string|max:255',
            'assigned_pic_user_id' => 'required|integer|exists:users,id',
        ]);

        $query = $employeeOnboarding->items()->where('week_number', $validated['week_number']);
        if (! empty($validated['area_name'])) {
            $query->where('area_name', $validated['area_name']);
        }
        $query->update([
            'assigned_pic_user_id' => $validated['assigned_pic_user_id'],
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'PIC berhasil di-assign.',
            'record' => $this->service->serializeOnboarding($employeeOnboarding->fresh()),
        ]);
    }

    public function submitWeek(Request $request, EmployeeOnboarding $employeeOnboarding)
    {
        $validated = $request->validate([
            'week_number' => 'required|integer|min:1',
            'approvers' => 'nullable|array|min:1',
            'approvers.*' => 'integer|exists:users,id',
        ]);

        try {
            $this->service->submitWeek(
                $employeeOnboarding,
                (int) $validated['week_number'],
                $validated['approvers'] ?? []
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Minggu berhasil diajukan untuk approval.',
            'record' => $this->service->serializeOnboarding($employeeOnboarding->fresh()),
        ]);
    }

    public function approve(Request $request, EmployeeOnboarding $employeeOnboarding)
    {
        $validated = $request->validate([
            'week_number' => 'required|integer|min:1',
            'action' => 'required|in:approve,reject,requires_revision',
            'comments' => 'nullable|string|max:1000',
        ]);

        $submission = EmployeeOnboardingWeekSubmission::where('onboarding_id', $employeeOnboarding->id)
            ->where('week_number', $validated['week_number'])
            ->where('status', 'submitted')
            ->firstOrFail();

        try {
            $this->service->approveWeekSubmission($submission, $validated['action'], $validated['comments'] ?? null);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Approval minggu berhasil diproses.',
            'record' => $this->service->serializeOnboarding($employeeOnboarding->fresh()),
        ]);
    }

    public function getPendingApprovals()
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->getPendingApprovals(),
        ]);
    }

    public function searchEmployees(Request $request)
    {
        return response()->json([
            'success' => true,
            'users' => $this->service->searchEmployees((string) $request->get('search', '')),
        ]);
    }

    public function searchUsers(Request $request)
    {
        return response()->json([
            'success' => true,
            'users' => $this->service->searchUsers((string) $request->get('search', '')),
        ]);
    }

    public function getTemplateStructure(int $templateId)
    {
        $template = OnboardingTemplate::with(['weeks.areas.items'])->findOrFail($templateId);

        return response()->json([
            'success' => true,
            'template' => $this->service->serializeTemplate($template),
        ]);
    }

    // --- Mobile API ---

    public function apiIndex(Request $request)
    {
        $userId = Auth::id();
        $mode = $request->get('mode', 'all');

        $query = EmployeeOnboarding::query()->with(['employee:id,nama_lengkap']);

        if ($mode === 'my_tasks') {
            $query->whereHas('items', fn ($q) => $q->where('assigned_pic_user_id', $userId));
        } elseif ($mode === 'my_onboarding') {
            $query->where('employee_user_id', $userId);
        }

        $records = $query->orderByDesc('id')->paginate((int) $request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'records' => collect($records->items())->map(fn (EmployeeOnboarding $row) => [
                'id' => $row->id,
                'number' => $row->number,
                'template_name' => $row->template_name,
                'employee_name' => $row->employee?->nama_lengkap,
                'status' => $row->status,
                'current_week' => $row->current_week,
                'unlocked_week' => $row->unlocked_week,
                'total_weeks' => $row->total_weeks,
            ])->values()->all(),
            'pagination' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'total' => $records->total(),
            ],
        ]);
    }

    public function apiShow(int $id)
    {
        $onboarding = EmployeeOnboarding::findOrFail($id);

        return response()->json([
            'success' => true,
            'record' => $this->service->serializeOnboarding($onboarding),
            'can_approve_week' => $this->canApproveCurrentWeek($onboarding),
        ]);
    }

    public function apiMyTasks(Request $request)
    {
        $request->merge(['mode' => 'my_tasks']);

        return $this->apiIndex($request);
    }

    public function apiUpdateItems(Request $request, int $id)
    {
        $onboarding = EmployeeOnboarding::findOrFail($id);

        return $this->updateItems($request, $onboarding);
    }

    public function apiSubmitWeek(Request $request, int $id)
    {
        $onboarding = EmployeeOnboarding::findOrFail($id);

        return $this->submitWeek($request, $onboarding);
    }

    public function apiApprove(Request $request, int $id)
    {
        $onboarding = EmployeeOnboarding::findOrFail($id);

        return $this->approve($request, $onboarding);
    }

    public function apiPendingApprovals()
    {
        return $this->getPendingApprovals();
    }

    public function apiSearchUsers(Request $request)
    {
        return $this->searchUsers($request);
    }

    private function canApproveCurrentWeek(EmployeeOnboarding $onboarding): bool
    {
        $submission = EmployeeOnboardingWeekSubmission::where('onboarding_id', $onboarding->id)
            ->where('week_number', $onboarding->unlocked_week)
            ->where('status', 'submitted')
            ->first();

        if (! $submission) {
            return false;
        }

        return $this->service->resolveApprovalFlowForUser($submission) !== null;
    }
}
