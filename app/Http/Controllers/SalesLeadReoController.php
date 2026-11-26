<?php

namespace App\Http\Controllers;

use App\Models\SalesLead;
use App\Models\SalesLeadReo;
use App\Models\SalesLeadReoApproval;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SalesLeadReoController extends Controller
{
    public function createOrShow($salesLeadId)
    {
        $lead = $this->findVisibleLead($salesLeadId);
        $reo = SalesLeadReo::query()
            ->where('sales_lead_id', $lead->id)
            ->with(['approvals.approver:id,nama_lengkap,signature_path'])
            ->first();

        if ($reo) {
            return redirect()->route('sales-leads.reo.show', $reo->id);
        }

        return $this->renderForm($lead, null);
    }

    public function show($id): Response
    {
        $reo = $this->findVisibleReo($id);
        $user = auth()->user();

        return Inertia::render('SalesLead/Reo/Show', [
            'reo' => $reo,
            'lead' => $reo->salesLead,
            'canEdit' => $this->canEdit($reo, $user),
            'canApprove' => $this->canApprove($reo, $user),
            'canExport' => true,
            'statuses' => SalesLeadReo::STATUSES,
        ]);
    }

    public function edit($id): Response
    {
        $reo = $this->findVisibleReo($id);
        $user = auth()->user();
        if (! $this->canEdit($reo, $user)) {
            abort(403, 'REO tidak dapat diedit pada status ini.');
        }

        return $this->renderForm($reo->salesLead, $reo);
    }

    public function store(Request $request, $salesLeadId)
    {
        $lead = $this->findVisibleLead($salesLeadId);
        if (SalesLeadReo::where('sales_lead_id', $lead->id)->exists()) {
            return redirect()->route('sales-leads.reo.open', $lead->id)
                ->with('error', 'REO untuk Sales Lead ini sudah ada.');
        }

        $validated = $this->validatePayload($request);
        $totals = $this->computeTotals($validated);
        $user = auth()->user();

        $reo = SalesLeadReo::create(array_merge($validated, $totals, [
            'sales_lead_id' => $lead->id,
            'number' => $this->generateNumber(),
            'company_name' => $lead->company_name,
            'sales_person_user_id' => $lead->sales_user_id,
            'sales_person_name' => $lead->sales?->nama_lengkap ?? $validated['sales_person_name'] ?? null,
            'status' => 'DRAFT',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]));

        return redirect()->route('sales-leads.reo.show', $reo->id)
            ->with('success', 'REO berhasil disimpan sebagai draft.');
    }

    public function update(Request $request, $id)
    {
        $reo = $this->findVisibleReo($id);
        $user = auth()->user();
        if (! $this->canEdit($reo, $user)) {
            abort(403, 'REO tidak dapat diedit pada status ini.');
        }

        $validated = $this->validatePayload($request);
        $totals = $this->computeTotals($validated);

        $reo->update(array_merge($validated, $totals, [
            'company_name' => $reo->salesLead?->company_name ?? $reo->company_name,
            'sales_person_user_id' => $reo->salesLead?->sales_user_id ?? $reo->sales_person_user_id,
            'sales_person_name' => $reo->salesLead?->sales?->nama_lengkap ?? ($validated['sales_person_name'] ?? $reo->sales_person_name),
            'updated_by' => $user->id,
            'status' => in_array($reo->status, ['REJECTED'], true) ? 'DRAFT' : $reo->status,
        ]));

        return redirect()->route('sales-leads.reo.show', $reo->id)
            ->with('success', 'REO berhasil diupdate.');
    }

    public function submit(Request $request, $id)
    {
        $reo = $this->findVisibleReo($id);
        $user = auth()->user();
        if (! $this->canEdit($reo, $user)) {
            return back()->with('error', 'REO tidak dapat di-submit.');
        }

        $request->validate([
            'approvers' => 'required|array|min:1',
            'approvers.*' => 'required|integer|exists:users,id',
        ]);

        DB::transaction(function () use ($request, $reo, $user) {
            $reo->approvals()->delete();
            foreach (array_values($request->approvers) as $index => $approverId) {
                SalesLeadReoApproval::create([
                    'reo_id' => $reo->id,
                    'approver_id' => $approverId,
                    'approval_level' => $index + 1,
                    'status' => 'PENDING',
                ]);
            }

            $reo->update([
                'status' => 'SUBMITTED',
                'submitted_at' => now(),
                'approved_at' => null,
                'updated_by' => $user->id,
            ]);
        });

        return redirect()->route('sales-leads.reo.show', $reo->id)
            ->with('success', 'REO berhasil di-submit untuk approval.');
    }

    public function approve(Request $request, $id)
    {
        $reo = $this->findVisibleReo($id);
        $user = auth()->user();

        if ($reo->status !== 'SUBMITTED') {
            return back()->with('error', 'REO tidak dalam status Submitted.');
        }

        if (! $user->signature_path || ! Storage::disk('public')->exists($user->signature_path)) {
            return back()->with('error', 'Anda belum punya e-signature. Simpan tanda tangan di menu E-Signature terlebih dahulu.');
        }

        $flow = $this->resolveCurrentApprovalFlow($reo, $user);
        if (! $flow) {
            return back()->with('error', 'Anda bukan approver berikutnya untuk REO ini.');
        }

        $notes = $request->input('notes');

        DB::transaction(function () use ($flow, $reo, $user, $notes) {
            $flow->update([
                'status' => 'APPROVED',
                'signature_path' => $user->signature_path,
                'notes' => $notes,
                'acted_at' => now(),
            ]);

            $stillPending = $reo->approvals()->where('status', 'PENDING')->exists();
            if (! $stillPending) {
                $reo->update([
                    'status' => 'APPROVED',
                    'approved_at' => now(),
                    'updated_by' => $user->id,
                ]);
            }
        });

        return redirect()->route('sales-leads.reo.show', $reo->id)
            ->with('success', 'REO berhasil di-approve (tanda tangan tersimpan).');
    }

    public function reject(Request $request, $id)
    {
        $reo = $this->findVisibleReo($id);
        $user = auth()->user();

        if ($reo->status !== 'SUBMITTED') {
            return back()->with('error', 'REO tidak dalam status Submitted.');
        }

        $flow = $this->resolveCurrentApprovalFlow($reo, $user);
        if (! $flow) {
            return back()->with('error', 'Anda bukan approver berikutnya untuk REO ini.');
        }

        $request->validate(['notes' => 'nullable|string|max:2000']);

        DB::transaction(function () use ($flow, $reo, $user, $request) {
            $flow->update([
                'status' => 'REJECTED',
                'notes' => $request->input('notes'),
                'acted_at' => now(),
            ]);
            $reo->update([
                'status' => 'REJECTED',
                'updated_by' => $user->id,
            ]);
        });

        return redirect()->route('sales-leads.reo.show', $reo->id)
            ->with('success', 'REO ditolak.');
    }

    public function exportPdf($id)
    {
        $reo = $this->findVisibleReo($id);
        $signatures = $reo->approvals
            ->where('status', 'APPROVED')
            ->values()
            ->map(function (SalesLeadReoApproval $flow) {
                $path = $flow->signature_path
                    ?: ($flow->approver?->signature_path);

                return [
                    'level' => $flow->approval_level,
                    'name' => $flow->approver?->nama_lengkap,
                    'signature_data_uri' => $this->signatureDataUri($path),
                    'acted_at' => $flow->acted_at,
                ];
            });

        $pdf = Pdf::loadView('exports.sales_lead_reo_pdf', [
            'reo' => $reo,
            'lead' => $reo->salesLead,
            'signatures' => $signatures,
            'showSignatures' => $reo->status === 'APPROVED',
        ])->setPaper('a4', 'portrait');

        $filename = str_replace(['/', '\\'], '-', $reo->number).'.pdf';

        return $pdf->download($filename);
    }

    public function getApprovers(Request $request)
    {
        $search = trim((string) $request->get('search', ''));

        $users = User::query()
            ->leftJoin('tbl_data_jabatan as j', 'users.id_jabatan', '=', 'j.id_jabatan')
            ->where('users.status', 'A')
            ->where('users.id', '!=', auth()->id())
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('users.nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%")
                        ->orWhere('j.nama_jabatan', 'like', "%{$search}%");
                });
            })
            ->orderBy('users.nama_lengkap')
            ->limit(40)
            ->get([
                'users.id',
                'users.nama_lengkap',
                'users.email',
                DB::raw('j.nama_jabatan as jabatan'),
            ]);

        return response()->json(['success' => true, 'users' => $users]);
    }

    private function renderForm(SalesLead $lead, ?SalesLeadReo $reo): Response
    {
        $lead->loadMissing(['sales:id,nama_lengkap', 'outlet:id_outlet,nama_outlet']);

        return Inertia::render('SalesLead/Reo/Form', [
            'lead' => $lead,
            'reo' => $reo,
            'defaults' => $reo ? null : [
                'company_name' => $lead->company_name,
                'date_of_issue' => $lead->event_date,
                'date_of_event' => $lead->event_date,
                'sales_person_name' => $lead->sales?->nama_lengkap,
                'prepared_by_name' => $lead->sales?->nama_lengkap,
                'prepared_by_title' => 'Corporate Sales Executive',
                'pax' => $lead->pax,
                'venue_setup' => $lead->outlet?->nama_outlet,
                'catering_fee_percent' => 30,
                'acknowledge_by_1_title' => 'Corporate Executive Chef',
                'acknowledge_by_2_title' => 'GM Sales & Marketing',
                'approved_by_title' => 'Director of Operation',
                'billing_instruction' => "Payment is Done, by Transfer to\nPT Yuditama Mandiri\n7750383321\nBCA",
            ],
        ]);
    }

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'address' => 'nullable|string|max:2000',
            'contact_person' => 'nullable|string|max:255',
            'mobile' => 'nullable|string|max:100',
            'email' => 'nullable|string|max:255',
            'date_of_issue' => 'nullable|date',
            'venue_setup' => 'nullable|string|max:255',
            'sales_person_name' => 'nullable|string|max:255',
            'sign_board' => 'nullable|string|max:5000',
            'revenue_items' => 'nullable|array',
            'revenue_items.*.date_arrangement' => 'nullable|string|max:500',
            'revenue_items.*.qty' => 'nullable|numeric|min:0',
            'revenue_items.*.days' => 'nullable|numeric|min:0',
            'revenue_items.*.rate' => 'nullable|numeric|min:0',
            'revenue_items.*.rate_nett' => 'nullable|numeric|min:0',
            'revenue_items.*.breakdown' => 'nullable|numeric|min:0',
            'revenue_items.*.breakdown_nett' => 'nullable|numeric|min:0',
            'catering_fee_percent' => 'nullable|numeric|min:0|max:100',
            'pax' => 'nullable|integer|min:0',
            'date_of_event' => 'nullable|date',
            'time_of_event' => 'nullable|array',
            'time_of_event.*.time' => 'nullable|string|max:100',
            'time_of_event.*.meals' => 'nullable|string|max:255',
            'time_of_event.*.venue_setup' => 'nullable|string|max:255',
            'menu_items' => 'nullable|string|max:10000',
            'kitchen_maintenance' => 'nullable|string|max:5000',
            'marcom' => 'nullable|string|max:5000',
            'billing_instruction' => 'nullable|string|max:5000',
            'prepared_by_name' => 'nullable|string|max:255',
            'prepared_by_title' => 'nullable|string|max:255',
            'acknowledge_by_1_name' => 'nullable|string|max:255',
            'acknowledge_by_1_title' => 'nullable|string|max:255',
            'acknowledge_by_2_name' => 'nullable|string|max:255',
            'acknowledge_by_2_title' => 'nullable|string|max:255',
            'approved_by_name' => 'nullable|string|max:255',
            'approved_by_title' => 'nullable|string|max:255',
            'total_restaurant_revenue' => 'nullable|numeric|min:0',
            'total_transaction' => 'nullable|numeric|min:0',
            'total_amount' => 'nullable|numeric|min:0',
        ]);

        $validated['date_of_issue'] = $this->normalizeDate($validated['date_of_issue'] ?? null);
        $validated['date_of_event'] = $this->normalizeDate($validated['date_of_event'] ?? null);
        $validated['revenue_items'] = array_values(array_filter(
            $validated['revenue_items'] ?? [],
            fn ($row) => filled($row['date_arrangement'] ?? null)
                || (float) ($row['qty'] ?? 0) > 0
                || (float) ($row['rate'] ?? 0) > 0
        ));
        $validated['time_of_event'] = array_values(array_filter(
            $validated['time_of_event'] ?? [],
            fn ($row) => filled($row['time'] ?? null)
                || filled($row['meals'] ?? null)
                || filled($row['venue_setup'] ?? null)
        ));
        $validated['catering_fee_percent'] = $validated['catering_fee_percent'] ?? 30;

        return $validated;
    }

    private function computeTotals(array $validated): array
    {
        $subtotalRate = 0.0;
        $subtotalNett = 0.0;
        foreach ($validated['revenue_items'] ?? [] as $row) {
            $subtotalRate += (float) ($row['breakdown'] ?? 0);
            $subtotalNett += (float) ($row['breakdown_nett'] ?? 0);
        }

        $feePercent = (float) ($validated['catering_fee_percent'] ?? 30);
        $feeAmount = round($subtotalNett * ($feePercent / 100), 2);
        $autoTotal = round($subtotalNett + $feeAmount, 2);

        $totalRestaurant = array_key_exists('total_restaurant_revenue', $validated) && $validated['total_restaurant_revenue'] !== null && $validated['total_restaurant_revenue'] !== ''
            ? (float) $validated['total_restaurant_revenue']
            : $subtotalNett;
        $totalTransaction = array_key_exists('total_transaction', $validated) && $validated['total_transaction'] !== null && $validated['total_transaction'] !== ''
            ? (float) $validated['total_transaction']
            : $feeAmount;
        $totalAmount = array_key_exists('total_amount', $validated) && $validated['total_amount'] !== null && $validated['total_amount'] !== ''
            ? (float) $validated['total_amount']
            : $autoTotal;

        return [
            'subtotal_rate' => $subtotalRate,
            'subtotal_nett' => $subtotalNett,
            'catering_fee_amount' => $feeAmount,
            'total_restaurant_revenue' => $totalRestaurant,
            'total_transaction' => $totalTransaction,
            'total_amount' => $totalAmount,
        ];
    }

    private function generateNumber(): string
    {
        $prefix = 'REO-'.now()->format('Ym').'-';
        $last = SalesLeadReo::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('number');

        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value, $m)) {
            return $m[0];
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function signatureDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }
        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';
        $binary = Storage::disk('public')->get($path);

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }

    private function findVisibleLead($id): SalesLead
    {
        $user = auth()->user();
        $canViewAll = $this->canViewAll($user);

        return SalesLead::query()
            ->with(['sales:id,nama_lengkap', 'outlet:id_outlet,nama_outlet', 'reo'])
            ->when(! $canViewAll, function ($q) use ($user) {
                $q->where(function ($inner) use ($user) {
                    $inner->where('created_by', $user->id)
                        ->orWhere('sales_user_id', $user->id);
                });
            })
            ->where('id', $id)
            ->firstOrFail();
    }

    private function findVisibleReo($id): SalesLeadReo
    {
        $user = auth()->user();
        $canViewAll = $this->canViewAll($user);

        return SalesLeadReo::query()
            ->with([
                'salesLead.outlet:id_outlet,nama_outlet',
                'salesLead.sales:id,nama_lengkap',
                'approvals.approver:id,nama_lengkap,signature_path',
                'creator:id,nama_lengkap',
            ])
            ->where('id', $id)
            ->where(function ($q) use ($user, $canViewAll) {
                if ($canViewAll) {
                    return;
                }
                $q->whereHas('salesLead', function ($leadQ) use ($user) {
                    $leadQ->where(function ($inner) use ($user) {
                        $inner->where('created_by', $user->id)
                            ->orWhere('sales_user_id', $user->id);
                    });
                })->orWhereHas('approvals', fn ($a) => $a->where('approver_id', $user->id));
            })
            ->firstOrFail();
    }

    private function resolveCurrentApprovalFlow(SalesLeadReo $reo, $user): ?SalesLeadReoApproval
    {
        $pending = $reo->approvals()->where('status', 'PENDING')->orderBy('approval_level')->get();
        if ($pending->isEmpty()) {
            return null;
        }
        $next = $pending->first();
        if ($this->isSuperadmin($user) || (int) $next->approver_id === (int) $user->id) {
            return $next;
        }

        return null;
    }

    private function canEdit(SalesLeadReo $reo, $user): bool
    {
        if (! $user) {
            return false;
        }
        if (! in_array($reo->status, ['DRAFT', 'REJECTED'], true)) {
            return false;
        }
        if ($this->isSuperadmin($user)) {
            return true;
        }

        return (int) $reo->created_by === (int) $user->id
            || (int) $reo->sales_person_user_id === (int) $user->id
            || (int) ($reo->salesLead?->created_by) === (int) $user->id
            || (int) ($reo->salesLead?->sales_user_id) === (int) $user->id;
    }

    private function canApprove(SalesLeadReo $reo, $user): bool
    {
        if (! $user || $reo->status !== 'SUBMITTED') {
            return false;
        }

        return (bool) $this->resolveCurrentApprovalFlow($reo, $user);
    }

    private function canViewAll($user): bool
    {
        return $this->isSuperadmin($user) || $this->isSalesDivision($user);
    }

    private function isSalesDivision($user): bool
    {
        return $user && (int) ($user->division_id ?? 0) === 17;
    }

    private function isSuperadmin($user): bool
    {
        return $user && (string) ($user->id_role ?? '') === '5af56935b011a';
    }
}
