<?php

namespace App\Http\Controllers;

use App\Models\OmniFlow;
use App\Models\OmniFlowRun;
use App\Models\OmniTeam;
use App\Models\User;
use App\Support\OmniFlowDefinition;
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
            ->with(['createdBy:id,nama_lengkap,avatar'])
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

        $validated = $this->validateFlow($request, null);

        OmniFlow::query()->create([
            ...$validated,
            'is_active' => true,
            'created_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('crm.omnichannel-flows.index')
            ->with('success', 'Flow otomasi dibuat.');
    }

    public function update(Request $request, OmniFlow $flow): RedirectResponse
    {
        $this->assertFlowAccess($request);

        $flow->update($this->validateFlow($request, $flow));

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

    public function toggleActive(Request $request, OmniFlow $flow): RedirectResponse
    {
        $this->assertFlowAccess($request);

        $flow->update(['is_active' => ! $flow->is_active]);

        return redirect()->route('crm.omnichannel-flows.index')
            ->with('success', $flow->is_active ? 'Flow diaktifkan.' : 'Flow dinonaktifkan.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateFlow(Request $request, ?OmniFlow $existing = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'trigger_type' => ['required', Rule::in(['inbound_message'])],
            'channel' => ['nullable', Rule::in(['whatsapp'])],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'definition' => ['required', 'array'],
            'definition.nodes' => ['required', 'array', 'min:1'],
            'definition.nodes.*.id' => ['required', 'string', 'max:64'],
            'definition.nodes.*.type' => ['nullable', 'string', 'max:32'],
            'definition.nodes.*.position' => ['nullable', 'array'],
            'definition.nodes.*.data' => ['nullable', 'array'],
            'definition.nodes.*.data.nodeType' => ['nullable', 'string', Rule::in([
                'trigger', 'condition', 'send_message', 'assign_team', 'assign_users', 'set_lead_stage', 'append_memo', 'notify_assignees',
            ])],
            'definition.nodes.*.data.config' => ['nullable', 'array'],
            'definition.edges' => ['nullable', 'array'],
            'definition.edges.*.source' => ['required', 'string'],
            'definition.edges.*.target' => ['required', 'string'],
            'definition.edges.*.id' => ['nullable', 'string', 'max:128'],
            'definition.edges.*.sourceHandle' => ['nullable', 'string', Rule::in(['default', 'true', 'false'])],
            'definition.edges.*.targetHandle' => ['nullable', 'string', Rule::in(['target', 'default'])],
        ]);

        $definition = OmniFlowDefinition::normalizeForStorage($validated['definition']);

        $hasTrigger = false;
        $hasAction = false;
        foreach ($definition['nodes'] as $node) {
            if (! is_array($node)) {
                continue;
            }
            $type = OmniFlowDefinition::nodeType($node);
            if ($type === 'trigger') {
                $hasTrigger = true;
            }
            if ($type !== '' && $type !== 'trigger') {
                $hasAction = true;
            }
        }

        abort_unless($hasTrigger && $hasAction, 422, 'Flow harus memiliki node pemicu dan minimal satu langkah.');

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'trigger_type' => $validated['trigger_type'],
            'channel' => $validated['channel'] ?? null,
            'priority' => (int) $validated['priority'],
            'definition' => $definition,
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
            'users' => OmnichannelUserOption::assignableOptions(),
            'leadStages' => OmniLeadStages::all(),
            'nodePalette' => [
                ['value' => 'condition', 'label' => 'Kondisi', 'color' => 'amber'],
                ['value' => 'send_message', 'label' => 'Kirim pesan WA', 'color' => 'emerald'],
                ['value' => 'assign_team', 'label' => 'Tugaskan tim', 'color' => 'blue'],
                ['value' => 'assign_users', 'label' => 'Tugaskan user', 'color' => 'indigo'],
                ['value' => 'set_lead_stage', 'label' => 'Ubah tahap lead', 'color' => 'violet'],
                ['value' => 'append_memo', 'label' => 'Tambah memo', 'color' => 'slate'],
                ['value' => 'notify_assignees', 'label' => 'Notifikasi', 'color' => 'rose'],
            ],
            'stepTypes' => [
                ['value' => 'condition', 'label' => 'Kondisi'],
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

        $creator = $flow->createdBy;

        return [
            'id' => $flow->id,
            'name' => $flow->name,
            'description' => $flow->description,
            'is_active' => (bool) $flow->is_active,
            'channel' => $flow->channel,
            'priority' => (int) $flow->priority,
            'step_count' => $flow->stepCount(),
            'created_by' => $creator ? [
                'id' => (int) $creator->id,
                'name' => (string) $creator->nama_lengkap,
                'avatar_url' => $this->userAvatarUrl($creator->avatar),
            ] : null,
            'last_run' => $lastRun ? [
                'status' => $lastRun->status,
                'finished_at' => $lastRun->finished_at?->toIso8601String(),
            ] : null,
        ];
    }

    private function userAvatarUrl(?string $avatar): ?string
    {
        if ($avatar === null || trim($avatar) === '') {
            return null;
        }

        $path = ltrim($avatar, '/');

        return str_starts_with($path, 'storage/') ? '/'.$path : '/storage/'.$path;
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
            'definition' => $this->formatDefinitionForEditor($flow),
        ];
    }

    /**
     * @return array{version: int, nodes: array, edges: array}
     */
    private function formatDefinitionForEditor(OmniFlow $flow): array
    {
        $def = is_array($flow->definition) ? $flow->definition : [];

        if (OmniFlowDefinition::isGraph($def)) {
            return [
                'version' => (int) ($def['version'] ?? OmniFlowDefinition::VERSION_GRAPH),
                'nodes' => array_values($def['nodes'] ?? []),
                'edges' => array_values($def['edges'] ?? []),
            ];
        }

        return OmniFlowDefinition::linearToGraph($flow->steps());
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
