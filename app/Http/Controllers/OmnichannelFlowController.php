<?php

namespace App\Http\Controllers;

use App\Models\OmniFlow;
use App\Models\OmniFlowRun;
use App\Models\OmniTeam;
use App\Models\User;
use App\Support\OmniLeadStages;
use App\Support\OmnichannelAuthorization;
use App\Support\OmnichannelUserOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OmnichannelFlowController extends Controller
{
    public function index(Request $request): Response
    {
        $this->assertFlowAccess($request);

        $flows = OmniFlow::query()
            ->orderBy('priority')
            ->orderBy('name')
            ->get()
            ->map(fn (OmniFlow $f) => $this->formatFlowListItem($f));

        return Inertia::render('Crm/OmnichannelFlows/Index', [
            'flows' => $flows,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->assertFlowAccess($request);

        return Inertia::render('Crm/OmnichannelFlows/Edit', [
            'flow' => null,
            ...$this->editorProps(),
        ]);
    }

    public function edit(Request $request, OmniFlow $flow): Response
    {
        $this->assertFlowAccess($request);

        return Inertia::render('Crm/OmnichannelFlows/Edit', [
            'flow' => $this->formatFlowDetail($flow),
            ...$this->editorProps(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->assertFlowAccess($request);

        $validated = $this->validateFlow($request);

        OmniFlow::query()->create([
            ...$validated,
            'created_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('crm.omnichannel-flows.index')
            ->with('success', 'Flow otomasi dibuat.');
    }

    public function update(Request $request, OmniFlow $flow): RedirectResponse
    {
        $this->assertFlowAccess($request);

        $flow->update($this->validateFlow($request));

        return redirect()->route('crm.omnichannel-flows.index')
            ->with('success', 'Flow otomasi diperbarui.');
    }

    public function destroy(Request $request, OmniFlow $flow): RedirectResponse
    {
        $this->assertFlowAccess($request);

        $flow->delete();

        return redirect()->route('crm.omnichannel-flows.index')
            ->with('success', 'Flow dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateFlow(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'trigger_type' => ['required', Rule::in(['inbound_message'])],
            'channel' => ['nullable', Rule::in(['whatsapp'])],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.type' => ['required', 'string', Rule::in([
                'condition', 'send_message', 'assign_team', 'assign_users', 'set_lead_stage', 'append_memo', 'notify_assignees',
            ])],
            'steps.*.config' => ['nullable', 'array'],
        ]);

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'trigger_type' => $validated['trigger_type'],
            'channel' => $validated['channel'] ?? null,
            'priority' => (int) $validated['priority'],
            'definition' => [
                'steps' => array_values($validated['steps']),
            ],
        ];
    }

    /**
     * @return array{teams: array, users: array, leadStages: array, stepTypes: array, conditionFields: array}
     */
    private function editorProps(): array
    {
        return [
            'teams' => OmniTeam::query()->orderBy('name')->get(['id', 'name'])->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
            ])->values()->all(),
            'users' => OmnichannelUserOption::toOptions(
                User::query()->with(['jabatan', 'outlet'])->orderBy('nama_lengkap')->get()
            ),
            'leadStages' => OmniLeadStages::all(),
            'stepTypes' => [
                ['value' => 'condition', 'label' => 'Kondisi (hentikan jika tidak cocok)'],
                ['value' => 'send_message', 'label' => 'Kirim pesan WhatsApp'],
                ['value' => 'assign_team', 'label' => 'Tugaskan ke tim'],
                ['value' => 'assign_users', 'label' => 'Tugaskan ke user'],
                ['value' => 'set_lead_stage', 'label' => 'Ubah tahap lead'],
                ['value' => 'append_memo', 'label' => 'Tambah memo CRM'],
                ['value' => 'notify_assignees', 'label' => 'Notifikasi ke yang ditugaskan'],
            ],
            'conditionFields' => [
                ['value' => 'message_contains', 'label' => 'Pesan mengandung teks'],
                ['value' => 'message_not_contains', 'label' => 'Pesan tidak mengandung teks'],
                ['value' => 'hour_between', 'label' => 'Jam (rentang WIB)'],
                ['value' => 'no_assignee', 'label' => 'Belum ada penugasan'],
                ['value' => 'lead_stage', 'label' => 'Tahap lead'],
                ['value' => 'has_member', 'label' => 'Punya member app'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatFlowListItem(OmniFlow $flow): array
    {
        $lastRun = OmniFlowRun::query()
            ->where('flow_id', $flow->id)
            ->orderByDesc('id')
            ->first();

        return [
            'id' => $flow->id,
            'name' => $flow->name,
            'description' => $flow->description,
            'is_active' => (bool) $flow->is_active,
            'channel' => $flow->channel,
            'priority' => (int) $flow->priority,
            'step_count' => count($flow->steps()),
            'last_run' => $lastRun ? [
                'status' => $lastRun->status,
                'finished_at' => $lastRun->finished_at?->toIso8601String(),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatFlowDetail(OmniFlow $flow): array
    {
        return [
            'id' => $flow->id,
            'name' => $flow->name,
            'description' => $flow->description,
            'is_active' => (bool) $flow->is_active,
            'trigger_type' => $flow->trigger_type,
            'channel' => $flow->channel,
            'priority' => (int) $flow->priority,
            'steps' => $flow->steps(),
        ];
    }

    private function assertFlowAccess(Request $request): void
    {
        $uid = (int) $request->user()->id;
        abort_unless(
            OmnichannelAuthorization::userHasPermission($uid, 'omnichannel_flows_view')
                || OmnichannelAuthorization::userHasPermission($uid, 'omnichannel_teams_view'),
            403
        );
    }
}
