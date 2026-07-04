<?php

namespace App\Http\Controllers;

use App\Models\OnboardingTemplate;
use App\Services\EmployeeOnboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingTemplateController extends Controller
{
    public function __construct(
        private readonly EmployeeOnboardingService $service
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->get('search', ''));

        $templates = OnboardingTemplate::query()
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            }))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('OnboardingTemplate/Index', [
            'templates' => $templates,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('OnboardingTemplate/Form', [
            'record' => null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        DB::beginTransaction();
        try {
            $template = OnboardingTemplate::create([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'total_weeks' => $validated['total_weeks'],
                'is_active' => $validated['is_active'] ?? true,
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
            $this->service->syncTemplate($template, $validated);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()->route('onboarding-templates.index')->with('success', 'Template onboarding berhasil disimpan.');
    }

    public function edit(OnboardingTemplate $onboardingTemplate): Response
    {
        return Inertia::render('OnboardingTemplate/Form', [
            'record' => $this->service->serializeTemplate($onboardingTemplate),
        ]);
    }

    public function update(Request $request, OnboardingTemplate $onboardingTemplate)
    {
        $validated = $this->validatePayload($request, $onboardingTemplate->id);

        DB::beginTransaction();
        try {
            $this->service->syncTemplate($onboardingTemplate, $validated);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()->route('onboarding-templates.index')->with('success', 'Template onboarding berhasil diperbarui.');
    }

    public function destroy(OnboardingTemplate $onboardingTemplate)
    {
        $onboardingTemplate->delete();

        return redirect()->route('onboarding-templates.index')->with('success', 'Template onboarding berhasil dihapus.');
    }

    public function searchUsers(Request $request)
    {
        return response()->json([
            'success' => true,
            'users' => $this->service->searchUsers((string) $request->get('search', '')),
        ]);
    }

    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('onboarding_templates', 'code')->ignore($ignoreId),
            ],
            'name' => 'required|string|max:255',
            'total_weeks' => 'required|integer|min:1|max:52',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string|max:2000',
            'weeks' => 'required|array|min:1',
            'weeks.*.week_number' => 'required|integer|min:1',
            'weeks.*.week_label' => 'nullable|string|max:255',
            'weeks.*.areas' => 'required|array|min:1',
            'weeks.*.areas.*.area_name' => 'required|string|max:255',
            'weeks.*.areas.*.items' => 'required|array|min:1',
            'weeks.*.areas.*.items.*.checklist_text' => 'required|string|max:2000',
            'weeks.*.areas.*.items.*.pic_role_hint' => 'nullable|string|max:255',
            'week_approvers' => 'nullable|array',
            'week_approvers.*.week_number' => 'required|integer|min:1',
            'week_approvers.*.approver_user_id' => 'required|integer|exists:users,id',
            'week_approvers.*.approval_level' => 'required|integer|min:1',
        ]);
    }
}
