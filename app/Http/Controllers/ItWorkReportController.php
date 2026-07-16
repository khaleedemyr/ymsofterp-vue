<?php

namespace App\Http\Controllers;

use App\Models\ItWorkReport;
use App\Models\ItWorkReportEvidence;
use App\Models\ItWorkReportItem;
use App\Models\Outlet;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ItWorkReportController extends Controller
{
    private const SUPERADMIN_ROLE_ID = '5af56935b011a';

    private const ALLOWED_MIMES = 'jpg,jpeg,png,webp,gif,pdf';

    public function index(Request $request): Response
    {
        $query = ItWorkReport::query()
            ->with([
                'executor:id,nama_lengkap',
                'ticket:id,ticket_number,title',
            ])
            ->withCount(['items', 'evidences'])
            ->orderByDesc('work_date')
            ->orderByDesc('id');

        if ($request->filled('outlet_id')) {
            $query->where('outlet_id', (int) $request->outlet_id);
        }

        if ($request->filled('source_type') && $request->source_type !== 'all') {
            $query->where('source_type', $request->string('source_type')->toString());
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('executor_id')) {
            $query->where('executor_id', (int) $request->executor_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('work_date', '>=', $request->string('date_from')->toString());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('work_date', '<=', $request->string('date_to')->toString());
        }

        if ($request->filled('scope')) {
            $scope = $request->string('scope')->toString();
            $query->whereHas('items', function ($q) use ($scope) {
                $q->whereJsonContains('scopes', $scope);
            });
        }

        if ($search = trim((string) $request->get('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('outlet_name', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('wa_contact_name', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->get('per_page', 15);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 15;
        }

        return Inertia::render('ItWorkReport/Index', [
            'reports' => $query->paginate($perPage)->withQueryString(),
            'filters' => [
                'search' => $request->get('search', ''),
                'outlet_id' => $request->get('outlet_id', ''),
                'source_type' => $request->get('source_type', 'all'),
                'status' => $request->get('status', 'all'),
                'executor_id' => $request->get('executor_id', ''),
                'date_from' => $request->get('date_from', ''),
                'date_to' => $request->get('date_to', ''),
                'scope' => $request->get('scope', ''),
                'per_page' => $perPage,
            ],
            'outlets' => $this->activeOutlets(),
            'executors' => $this->executorOptions(),
            'scopeOptions' => ItWorkReport::SCOPES,
            'sourceOptions' => $this->sourceOptions(),
        ]);
    }

    public function create(Request $request): Response
    {
        $prefillTicketId = $request->integer('ticket_id') ?: null;
        $prefillTicket = null;
        if ($prefillTicketId) {
            $prefillTicket = Ticket::with('outlet:id_outlet,nama_outlet')
                ->select('id', 'ticket_number', 'title', 'outlet_id')
                ->find($prefillTicketId);
        }

        return Inertia::render('ItWorkReport/Form', array_merge(
            $this->formOptions(),
            [
                'record' => null,
                'prefillTicket' => $prefillTicket,
            ]
        ));
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request, submitting: $request->boolean('submit'));

        $report = null;
        DB::beginTransaction();
        try {
            $outlet = Outlet::findOrFail($validated['outlet_id']);
            $status = $request->boolean('submit')
                ? ItWorkReport::STATUS_SUBMITTED
                : ItWorkReport::STATUS_DRAFT;

            $report = ItWorkReport::create([
                'number' => $this->generateNumber(),
                'work_date' => $validated['work_date'],
                'start_time' => $validated['start_time'] ?? null,
                'end_time' => $validated['end_time'] ?? null,
                'outlet_id' => $outlet->id_outlet,
                'outlet_name' => (string) $outlet->nama_outlet,
                'executor_id' => $validated['executor_id'] ?? Auth::id(),
                'source_type' => $validated['source_type'],
                'ticket_id' => $validated['ticket_id'] ?? null,
                'wa_contact_name' => $validated['wa_contact_name'] ?? null,
                'wa_phone' => $validated['wa_phone'] ?? null,
                'wa_reported_at' => $validated['wa_reported_at'] ?? null,
                'wa_summary' => $validated['wa_summary'] ?? null,
                'title' => $validated['title'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => $status,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
                'submitted_at' => $status === ItWorkReport::STATUS_SUBMITTED ? now() : null,
            ]);

            $this->syncItems($report, $validated['items'] ?? []);
            $this->storeEvidences($report, $request);
            $this->assertSubmitRequirements($report, $status);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($report?->id) {
                Storage::disk('public')->deleteDirectory('it_work_reports/'.$report->id);
            }
            throw $e;
        }

        return redirect()
            ->route('it-work-reports.show', $report->id)
            ->with('success', $report->status === ItWorkReport::STATUS_SUBMITTED
                ? 'IT Work Report berhasil disubmit.'
                : 'IT Work Report disimpan sebagai draft.');
    }

    public function show(ItWorkReport $itWorkReport): Response
    {
        $itWorkReport->load([
            'items',
            'evidences',
            'executor:id,nama_lengkap',
            'creator:id,nama_lengkap',
            'ticket:id,ticket_number,title',
        ]);

        return Inertia::render('ItWorkReport/Show', [
            'record' => $itWorkReport,
            'deviceTypes' => ItWorkReport::DEVICE_TYPES,
            'scopeOptions' => ItWorkReport::SCOPES,
            'resultOptions' => ItWorkReport::RESULTS,
            'sourceOptions' => $this->sourceOptions(),
            'canEdit' => $itWorkReport->isDraft() && $this->canManage($itWorkReport),
            'canDelete' => $itWorkReport->isDraft() && $this->canManage($itWorkReport),
        ]);
    }

    public function edit(ItWorkReport $itWorkReport): Response
    {
        abort_unless($itWorkReport->isDraft() && $this->canManage($itWorkReport), 403);

        $itWorkReport->load([
            'items',
            'evidences',
            'ticket:id,ticket_number,title,outlet_id',
        ]);

        return Inertia::render('ItWorkReport/Form', array_merge(
            $this->formOptions(),
            [
                'record' => $itWorkReport,
                'prefillTicket' => null,
            ]
        ));
    }

    public function update(Request $request, ItWorkReport $itWorkReport)
    {
        abort_unless($itWorkReport->isDraft() && $this->canManage($itWorkReport), 403);

        $validated = $this->validatePayload($request, submitting: $request->boolean('submit'));

        DB::beginTransaction();
        try {
            $outlet = Outlet::findOrFail($validated['outlet_id']);
            $status = $request->boolean('submit')
                ? ItWorkReport::STATUS_SUBMITTED
                : ItWorkReport::STATUS_DRAFT;

            $itWorkReport->update([
                'work_date' => $validated['work_date'],
                'start_time' => $validated['start_time'] ?? null,
                'end_time' => $validated['end_time'] ?? null,
                'outlet_id' => $outlet->id_outlet,
                'outlet_name' => (string) $outlet->nama_outlet,
                'executor_id' => $validated['executor_id'] ?? $itWorkReport->executor_id,
                'source_type' => $validated['source_type'],
                'ticket_id' => $validated['ticket_id'] ?? null,
                'wa_contact_name' => $validated['wa_contact_name'] ?? null,
                'wa_phone' => $validated['wa_phone'] ?? null,
                'wa_reported_at' => $validated['wa_reported_at'] ?? null,
                'wa_summary' => $validated['wa_summary'] ?? null,
                'title' => $validated['title'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => $status,
                'updated_by' => Auth::id(),
                'submitted_at' => $status === ItWorkReport::STATUS_SUBMITTED
                    ? ($itWorkReport->submitted_at ?? now())
                    : null,
            ]);

            $itWorkReport->items()->delete();
            $this->syncItems($itWorkReport, $validated['items'] ?? []);

            $this->deleteEvidencesByIds($itWorkReport, $request->input('remove_evidence_ids', []));
            $this->storeEvidences($itWorkReport, $request);
            $this->assertSubmitRequirements($itWorkReport->fresh(['items', 'evidences']), $status);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('it-work-reports.show', $itWorkReport->id)
            ->with('success', $status === ItWorkReport::STATUS_SUBMITTED
                ? 'IT Work Report berhasil disubmit.'
                : 'IT Work Report berhasil diperbarui.');
    }

    public function destroy(ItWorkReport $itWorkReport)
    {
        abort_unless($itWorkReport->isDraft() && $this->canManage($itWorkReport), 403);

        Storage::disk('public')->deleteDirectory('it_work_reports/'.$itWorkReport->id);
        $itWorkReport->delete();

        return redirect()
            ->route('it-work-reports.index')
            ->with('success', 'IT Work Report dihapus.');
    }

    public function searchTickets(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $outletId = $request->integer('outlet_id') ?: null;

        $query = Ticket::query()
            ->with('outlet:id_outlet,nama_outlet')
            ->select('id', 'ticket_number', 'title', 'outlet_id', 'created_at')
            ->orderByDesc('id')
            ->limit(20);

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('ticket_number', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%");
            });
        }

        return response()->json([
            'data' => $query->get()->map(fn (Ticket $t) => [
                'id' => $t->id,
                'ticket_number' => $t->ticket_number,
                'title' => $t->title,
                'outlet_id' => $t->outlet_id,
                'outlet_name' => $t->outlet?->nama_outlet,
                'label' => $t->ticket_number.' — '.$t->title,
            ]),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $query = ItWorkReport::query()
            ->with(['items', 'executor:id,nama_lengkap', 'ticket:id,ticket_number'])
            ->orderByDesc('work_date')
            ->orderByDesc('id');

        if ($request->filled('outlet_id')) {
            $query->where('outlet_id', (int) $request->outlet_id);
        }
        if ($request->filled('source_type') && $request->source_type !== 'all') {
            $query->where('source_type', $request->string('source_type')->toString());
        }
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('date_from')) {
            $query->whereDate('work_date', '>=', $request->string('date_from')->toString());
        }
        if ($request->filled('date_to')) {
            $query->whereDate('work_date', '<=', $request->string('date_to')->toString());
        }

        $rows = collect();
        foreach ($query->limit(5000)->get() as $report) {
            if ($report->items->isEmpty()) {
                $rows->push($this->exportRow($report, null));
                continue;
            }
            foreach ($report->items as $item) {
                $rows->push($this->exportRow($report, $item));
            }
        }

        $fileName = 'it_work_reports_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new class($rows) implements FromCollection, WithHeadings {
            public function __construct(private \Illuminate\Support\Collection $rows) {}

            public function collection()
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return [
                    'Number', 'Work Date', 'Outlet', 'Executor', 'Source', 'Ticket',
                    'Status', 'Title', 'Device Type', 'Device Label', 'Identifier',
                    'Scopes', 'Result', 'Item Notes', 'WA Contact', 'WA Phone',
                ];
            }
        }, $fileName);
    }

    private function exportRow(ItWorkReport $report, ?ItWorkReportItem $item): array
    {
        $scopes = $item
            ? collect($item->scopes ?? [])->map(fn ($c) => ItWorkReport::SCOPES[$c] ?? $c)->implode(', ')
            : '';

        return [
            $report->number,
            optional($report->work_date)->format('Y-m-d'),
            $report->outlet_name,
            $report->executor?->nama_lengkap,
            $this->sourceOptions()[$report->source_type] ?? $report->source_type,
            $report->ticket?->ticket_number,
            $report->status,
            $report->title,
            $item ? (ItWorkReport::DEVICE_TYPES[$item->device_type] ?? $item->device_type) : '',
            $item?->device_label,
            $item?->identifier,
            $scopes,
            $item ? (ItWorkReport::RESULTS[$item->result] ?? $item->result) : '',
            $item?->notes,
            $report->wa_contact_name,
            $report->wa_phone,
        ];
    }

    private function validatePayload(Request $request, bool $submitting): array
    {
        $sourceType = (string) $request->input('source_type');

        $rules = [
            'work_date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'executor_id' => 'nullable|integer|exists:users,id',
            'source_type' => ['required', Rule::in(array_keys($this->sourceOptions()))],
            'ticket_id' => [
                Rule::requiredIf($sourceType === ItWorkReport::SOURCE_TICKET),
                'nullable',
                'integer',
                'exists:tickets,id',
            ],
            'wa_contact_name' => [
                Rule::requiredIf($sourceType === ItWorkReport::SOURCE_WHATSAPP && $submitting),
                'nullable',
                'string',
                'max:255',
            ],
            'wa_phone' => 'nullable|string|max:40',
            'wa_reported_at' => 'nullable|date',
            'wa_summary' => [
                Rule::requiredIf($sourceType === ItWorkReport::SOURCE_WHATSAPP && $submitting),
                'nullable',
                'string',
                'max:2000',
            ],
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
            'items' => array_values(array_filter([
                $submitting ? 'required' : 'nullable',
                'array',
                $submitting ? 'min:1' : null,
            ])),
            'items.*.device_type' => ['required_with:items', Rule::in(array_keys(ItWorkReport::DEVICE_TYPES))],
            'items.*.device_label' => 'required_with:items|string|max:255',
            'items.*.identifier' => 'nullable|string|max:255',
            'items.*.scopes' => array_values(array_filter([
                $submitting ? 'required' : 'nullable',
                'array',
                $submitting ? 'min:1' : null,
            ])),
            'items.*.scopes.*' => [Rule::in(array_keys(ItWorkReport::SCOPES))],
            'items.*.notes' => 'nullable|string|max:2000',
            'items.*.result' => ['nullable', Rule::in(array_keys(ItWorkReport::RESULTS))],
            'wa_screenshots' => 'nullable|array',
            'wa_screenshots.*' => 'file|mimes:'.self::ALLOWED_MIMES.'|max:10240',
            'work_evidences' => 'nullable|array',
            'work_evidences.*' => 'file|mimes:'.self::ALLOWED_MIMES.'|max:10240',
            'other_evidences' => 'nullable|array',
            'other_evidences.*' => 'file|mimes:'.self::ALLOWED_MIMES.'|max:10240',
            'remove_evidence_ids' => 'nullable|array',
            'remove_evidence_ids.*' => 'integer',
        ];

        return $request->validate($rules);
    }

    private function assertSubmitRequirements(ItWorkReport $report, string $status): void
    {
        if ($status !== ItWorkReport::STATUS_SUBMITTED) {
            return;
        }

        $errors = [];

        if ($report->items->isEmpty()) {
            $errors['items'] = 'Minimal 1 perangkat wajib diisi saat submit.';
        } else {
            foreach ($report->items as $idx => $item) {
                if (empty($item->scopes)) {
                    $errors["items.{$idx}.scopes"] = 'Pilih minimal 1 scope pekerjaan.';
                }
            }
        }

        $workCount = $report->evidences->where('kind', ItWorkReportEvidence::KIND_WORK)->count();
        if ($workCount < 1) {
            $errors['work_evidences'] = 'Minimal 1 evidence pekerjaan wajib diupload saat submit.';
        }

        if ($report->source_type === ItWorkReport::SOURCE_WHATSAPP) {
            $waCount = $report->evidences->where('kind', ItWorkReportEvidence::KIND_WA_SCREENSHOT)->count();
            if ($waCount < 1) {
                $errors['wa_screenshots'] = 'Screenshot WhatsApp wajib diupload jika sumber = WhatsApp.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function syncItems(ItWorkReport $report, array $items): void
    {
        foreach (array_values($items) as $index => $item) {
            ItWorkReportItem::create([
                'it_work_report_id' => $report->id,
                'device_type' => $item['device_type'],
                'device_label' => $item['device_label'],
                'identifier' => $item['identifier'] ?? null,
                'scopes' => array_values($item['scopes'] ?? []),
                'notes' => $item['notes'] ?? null,
                'result' => $item['result'] ?? null,
                'sort_order' => $index,
            ]);
        }
    }

    private function storeEvidences(ItWorkReport $report, Request $request): void
    {
        $map = [
            'wa_screenshots' => ItWorkReportEvidence::KIND_WA_SCREENSHOT,
            'work_evidences' => ItWorkReportEvidence::KIND_WORK,
            'other_evidences' => ItWorkReportEvidence::KIND_OTHER,
        ];

        foreach ($map as $input => $kind) {
            if (! $request->hasFile($input)) {
                continue;
            }
            foreach ($request->file($input) as $file) {
                if (! $file || ! $file->isValid()) {
                    continue;
                }
                $path = $file->store('it_work_reports/'.$report->id, 'public');
                ItWorkReportEvidence::create([
                    'it_work_report_id' => $report->id,
                    'kind' => $kind,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                    'uploaded_by' => Auth::id(),
                ]);
            }
        }
    }

    private function deleteEvidencesByIds(ItWorkReport $report, mixed $ids): void
    {
        $ids = collect(is_array($ids) ? $ids : [])->filter()->map(fn ($id) => (int) $id)->all();
        if (! $ids) {
            return;
        }

        $evidences = $report->evidences()->whereIn('id', $ids)->get();
        foreach ($evidences as $evidence) {
            if ($evidence->file_path) {
                Storage::disk('public')->delete($evidence->file_path);
            }
            $evidence->delete();
        }
    }

    private function generateNumber(): string
    {
        $prefix = 'IWR-'.now()->format('Ym').'-';
        $last = ItWorkReport::where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function formOptions(): array
    {
        return [
            'outlets' => $this->activeOutlets(),
            'executors' => $this->executorOptions(),
            'deviceTypes' => ItWorkReport::DEVICE_TYPES,
            'scopeOptions' => ItWorkReport::SCOPES,
            'resultOptions' => ItWorkReport::RESULTS,
            'sourceOptions' => $this->sourceOptions(),
            'currentUserId' => Auth::id(),
        ];
    }

    private function activeOutlets()
    {
        return Outlet::where('status', 'A')
            ->where('is_outlet', 1)
            ->orderBy('nama_outlet')
            ->get(['id_outlet', 'nama_outlet']);
    }

    private function executorOptions()
    {
        return User::query()
            ->where('status', 'A')
            ->orderBy('nama_lengkap')
            ->limit(500)
            ->get(['id', 'nama_lengkap']);
    }

    private function sourceOptions(): array
    {
        return [
            ItWorkReport::SOURCE_PROACTIVE => 'Proaktif',
            ItWorkReport::SOURCE_TICKET => 'Ticket',
            ItWorkReport::SOURCE_WHATSAPP => 'WhatsApp',
        ];
    }

    private function canManage(ItWorkReport $report): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return (int) $report->executor_id === (int) $user->id
            || (int) $report->created_by === (int) $user->id;
    }

    private function isSuperAdmin($user): bool
    {
        if (! $user) {
            return false;
        }
        if ((string) ($user->id_role ?? '') === self::SUPERADMIN_ROLE_ID) {
            return true;
        }
        if (method_exists($user, 'hasRole') && $user->hasRole('Superadmin')) {
            return true;
        }

        return false;
    }
}
