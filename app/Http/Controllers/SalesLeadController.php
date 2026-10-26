<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\SalesLead;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesLeadController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->get('search', ''));
        $segment1 = trim((string) $request->get('segment_1', ''));
        $status = trim((string) $request->get('status', ''));
        $outletId = $request->get('outlet_id');

        $records = $this->visibleQuery()
            ->with([
                'outlet:id_outlet,nama_outlet',
                'sales:id,nama_lengkap',
                'creator:id,nama_lengkap',
            ])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('number', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('remarks', 'like', "%{$search}%")
                        ->orWhereHas('sales', fn ($u) => $u->where('nama_lengkap', 'like', "%{$search}%"));
                });
            })
            ->when($segment1 !== '', fn ($q) => $q->where('segment_1', $segment1))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($outletId, fn ($q) => $q->where('outlet_id', (int) $outletId))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('SalesLead/Index', [
            'records' => $records,
            'filters' => [
                'search' => $search,
                'segment_1' => $segment1,
                'status' => $status,
                'outlet_id' => $outletId ? (int) $outletId : '',
            ],
            'segment1Options' => SalesLead::SEGMENT_1,
            'statuses' => SalesLead::STATUSES,
            'outlets' => $this->outletOptions(),
        ]);
    }

    public function create(): Response
    {
        $user = auth()->user();

        return Inertia::render('SalesLead/Create', array_merge($this->formOptions(), [
            'salesUser' => [
                'id' => $user->id,
                'nama_lengkap' => $user->nama_lengkap,
            ],
        ]));
    }

    public function show($id): Response
    {
        $record = $this->findVisible($id);
        $user = auth()->user();

        return Inertia::render('SalesLead/Show', [
            'record' => $record,
            'canEdit' => $this->canEdit($record, $user),
            'canDelete' => $this->canDelete($record, $user),
            'statuses' => SalesLead::STATUSES,
        ]);
    }

    public function edit($id): Response
    {
        $record = $this->findVisible($id);
        $user = auth()->user();
        if (! $this->canEdit($record, $user)) {
            abort(403, 'Tidak dapat mengedit Sales Lead ini.');
        }

        return Inertia::render('SalesLead/Edit', array_merge($this->formOptions(), [
            'record' => $record,
            'salesUser' => [
                'id' => $record->sales?->id ?? $record->sales_user_id,
                'nama_lengkap' => $record->sales?->nama_lengkap ?? '-',
            ],
        ]));
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $user = auth()->user();

        $lead = SalesLead::create([
            'number' => $this->generateNumber(),
            'company_name' => $validated['company_name'],
            'segment_1' => $validated['segment_1'] ?? null,
            'segment_2' => $validated['segment_2'] ?? null,
            'type_of_event' => $validated['type_of_event'] ?? null,
            'event_date' => $validated['event_date'] ?? null,
            'pax' => $validated['pax'] ?? null,
            'outlet_id' => $validated['outlet_id'] ?? null,
            'status' => $validated['status'],
            'revenue' => $validated['revenue'] ?? null,
            'sales_user_id' => $user->id,
            'remarks' => $validated['remarks'] ?? null,
            'created_by' => $user->id,
        ]);

        return redirect()->route('sales-leads.show', $lead->id)
            ->with('success', 'Sales Lead berhasil disimpan.');
    }

    public function update(Request $request, $id)
    {
        $record = $this->findVisible($id);
        $user = auth()->user();
        if (! $this->canEdit($record, $user)) {
            abort(403, 'Tidak dapat mengedit Sales Lead ini.');
        }

        $validated = $this->validatePayload($request);

        $record->update([
            'company_name' => $validated['company_name'],
            'segment_1' => $validated['segment_1'] ?? null,
            'segment_2' => $validated['segment_2'] ?? null,
            'type_of_event' => $validated['type_of_event'] ?? null,
            'event_date' => $validated['event_date'] ?? null,
            'pax' => $validated['pax'] ?? null,
            'outlet_id' => $validated['outlet_id'] ?? null,
            'status' => $validated['status'],
            'revenue' => $validated['revenue'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('sales-leads.show', $record->id)
            ->with('success', 'Sales Lead berhasil diupdate.');
    }

    public function destroy($id)
    {
        $record = $this->findVisible($id);
        $user = auth()->user();
        if (! $this->canDelete($record, $user)) {
            abort(403);
        }

        $record->delete();

        return redirect()->route('sales-leads.index')->with('success', 'Sales Lead dihapus.');
    }

    private function formOptions(): array
    {
        return [
            'segment1Options' => SalesLead::SEGMENT_1,
            'segment2Options' => SalesLead::SEGMENT_2,
            'typeOfEvents' => SalesLead::TYPE_OF_EVENTS,
            'statuses' => SalesLead::STATUSES,
            'outlets' => $this->outletOptions(),
        ];
    }

    private function outletOptions()
    {
        return Outlet::query()
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->orderBy('nama_outlet')
            ->get(['id_outlet', 'nama_outlet']);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'company_name' => 'required|string|max:255',
            'segment_1' => 'nullable|string|in:'.implode(',', SalesLead::SEGMENT_1),
            'segment_2' => 'nullable|string|in:'.implode(',', SalesLead::SEGMENT_2),
            'type_of_event' => 'nullable|string|in:'.implode(',', SalesLead::TYPE_OF_EVENTS),
            'event_date' => 'nullable|date',
            'pax' => 'nullable|integer|min:0',
            'outlet_id' => 'nullable|integer|exists:tbl_data_outlet,id_outlet',
            'status' => 'required|string|in:'.implode(',', array_keys(SalesLead::STATUSES)),
            'revenue' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:5000',
        ]);
    }

    private function generateNumber(): string
    {
        $prefix = 'SL-'.now()->format('Ym').'-';
        $last = SalesLead::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('number');

        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function visibleQuery()
    {
        $user = auth()->user();
        $isSuperadmin = $this->isSuperadmin($user);

        return SalesLead::query()
            ->when(! $isSuperadmin, function ($q) use ($user) {
                $q->where(function ($inner) use ($user) {
                    $inner->where('created_by', $user->id)
                        ->orWhere('sales_user_id', $user->id);
                });
            });
    }

    private function findVisible($id): SalesLead
    {
        return $this->visibleQuery()
            ->with([
                'outlet:id_outlet,nama_outlet',
                'sales:id,nama_lengkap,email',
                'creator:id,nama_lengkap',
            ])
            ->where('id', $id)
            ->firstOrFail();
    }

    private function canEdit(SalesLead $record, $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($this->isSuperadmin($user)) {
            return true;
        }

        return (int) $record->created_by === (int) $user->id
            || (int) $record->sales_user_id === (int) $user->id;
    }

    private function canDelete(SalesLead $record, $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($this->isSuperadmin($user)) {
            return true;
        }

        return (int) $record->created_by === (int) $user->id;
    }

    private function isSuperadmin($user): bool
    {
        if (! $user) {
            return false;
        }

        $roleName = strtolower((string) ($user->role_name ?? $user->role ?? ''));
        if (in_array($roleName, ['superadmin', 'super admin', 'administrator'], true)) {
            return true;
        }

        try {
            if (method_exists($user, 'roles')) {
                return $user->roles()->whereIn('name', ['superadmin', 'Superadmin', 'Super Admin'])->exists();
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return (int) ($user->id ?? 0) === 1;
    }
}
