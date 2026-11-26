<?php

namespace App\Http\Controllers;

use App\Models\SalesLead;
use App\Models\SalesLeadReo;
use App\Models\SalesLeadReoApproval;
use App\Models\User;
use App\Support\PendingApprovalCache;
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
            'canDelete' => $this->canDelete($reo, $user),
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

        $salesName = $lead->sales?->nama_lengkap ?? ($validated['sales_person_name'] ?? null);

        $reo = SalesLeadReo::create(array_merge($validated, $totals, [
            'sales_lead_id' => $lead->id,
            'number' => $this->generateNumber(),
            'company_name' => $lead->company_name,
            'sales_person_user_id' => $lead->sales_user_id,
            'sales_person_name' => $salesName,
            'prepared_by_name' => $salesName,
            'prepared_by_title' => 'Corporate Sales Executive',
            'status' => 'DRAFT',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]));

        if ($request->boolean('submit_now')) {
            $this->applyApproversAndSubmit($reo, $request->input('approvers', []), $user);

            return redirect()->route('sales-leads.reo.show', $reo->id)
                ->with('success', 'REO berhasil di-submit untuk approval.');
        }

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

        $salesName = $reo->salesLead?->sales?->nama_lengkap
            ?? ($validated['sales_person_name'] ?? $reo->sales_person_name);

        $reo->update(array_merge($validated, $totals, [
            'company_name' => $reo->salesLead?->company_name ?? $reo->company_name,
            'sales_person_user_id' => $reo->salesLead?->sales_user_id ?? $reo->sales_person_user_id,
            'sales_person_name' => $salesName,
            'prepared_by_name' => $salesName,
            'prepared_by_title' => $reo->prepared_by_title ?: 'Corporate Sales Executive',
            'updated_by' => $user->id,
            'status' => in_array($reo->status, ['REJECTED'], true) ? 'DRAFT' : $reo->status,
        ]));

        if ($request->boolean('submit_now')) {
            $this->applyApproversAndSubmit($reo->fresh(), $request->input('approvers', []), $user);

            return redirect()->route('sales-leads.reo.show', $reo->id)
                ->with('success', 'REO berhasil di-submit untuk approval.');
        }

        return redirect()->route('sales-leads.reo.show', $reo->id)
            ->with('success', 'REO berhasil diupdate.');
    }

    public function destroy($id)
    {
        $reo = $this->findVisibleReo($id);
        $user = auth()->user();
        if (! $this->canDelete($reo, $user)) {
            abort(403, 'REO tidak dapat dihapus.');
        }

        $approverIds = $reo->approvals()->pluck('approver_id')->all();

        DB::transaction(function () use ($reo) {
            $reo->approvals()->delete();
            $reo->delete();
        });

        PendingApprovalCache::forgetForUsers(array_merge($approverIds, [$user->id]));

        return redirect()->route('sales-leads.index')
            ->with('success', 'REO berhasil dihapus.');
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

        $this->applyApproversAndSubmit($reo, $request->input('approvers', []), $user);

        return redirect()->route('sales-leads.reo.show', $reo->id)
            ->with('success', 'REO berhasil di-submit untuk approval.');
    }

    public function approve(Request $request, $id)
    {
        $reo = $this->findVisibleReo($id);
        $user = auth()->user();

        $wantsJson = $request->expectsJson() || $request->ajax() || $request->wantsJson();

        if ($reo->status !== 'SUBMITTED') {
            return $wantsJson
                ? response()->json(['success' => false, 'message' => 'REO tidak dalam status Submitted.'], 422)
                : back()->with('error', 'REO tidak dalam status Submitted.');
        }

        if (! $user->signature_path || ! Storage::disk('public')->exists($user->signature_path)) {
            $msg = 'Anda belum punya e-signature. Simpan tanda tangan di menu E-Signature terlebih dahulu.';

            return $wantsJson
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : back()->with('error', $msg);
        }

        $flow = $this->resolveCurrentApprovalFlow($reo, $user);
        if (! $flow) {
            return $wantsJson
                ? response()->json(['success' => false, 'message' => 'Anda bukan approver berikutnya untuk REO ini.'], 403)
                : back()->with('error', 'Anda bukan approver berikutnya untuk REO ini.');
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
                $fresh = $reo->fresh(['approvals.approver.jabatan']);
                $this->syncSignatureLabelsFromApprovals($fresh);
                $reo->update([
                    'status' => 'APPROVED',
                    'approved_at' => now(),
                    'updated_by' => $user->id,
                ]);
            }
        });

        PendingApprovalCache::forgetForUsers([$user->id]);

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'REO berhasil di-approve (tanda tangan tersimpan).',
            ]);
        }

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

        PendingApprovalCache::forgetForUsers([$user->id]);

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'REO ditolak.',
            ]);
        }

        return redirect()->route('sales-leads.reo.show', $reo->id)
            ->with('success', 'REO ditolak.');
    }

    public function getPendingApprovals()
    {
        $currentUser = auth()->user();
        if (! $currentUser) {
            return response()->json(['success' => false, 'reos' => []], 401);
        }

        $isSuperadmin = $this->isSuperadmin($currentUser);

        $query = SalesLeadReo::query()
            ->where('status', 'SUBMITTED')
            ->whereHas('approvals', function ($q) use ($currentUser, $isSuperadmin) {
                $q->where('status', 'PENDING');
                if (! $isSuperadmin) {
                    $q->where('approver_id', $currentUser->id);
                }
            })
            ->with([
                'salesLead:id,number,company_name,type_of_event,outlet_id',
                'salesLead.outlet:id_outlet,nama_outlet',
                'salesPerson:id,nama_lengkap',
                'creator:id,nama_lengkap',
                'approvals.approver:id,nama_lengkap',
            ])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id');

        $pending = $query->get();

        if (! $isSuperadmin) {
            $pending = $pending->filter(function (SalesLeadReo $item) use ($currentUser) {
                $next = $item->approvals->where('status', 'PENDING')->sortBy('approval_level')->first();

                return $next && (int) $next->approver_id === (int) $currentUser->id;
            })->values();
        }

        $pending = $pending->map(function (SalesLeadReo $item) {
            $next = $item->approvals->where('status', 'PENDING')->sortBy('approval_level')->first();
            $item->setAttribute('approver_name', $next?->approver?->nama_lengkap);
            $item->setAttribute('approval_level', $next?->approval_level);
            $item->setAttribute('status_label', $item->statusLabel());
            $item->setAttribute('can_approve', true);

            return $item;
        });

        return response()->json([
            'success' => true,
            'reos' => $pending->values(),
        ]);
    }

    public function getApprovalDetails($id)
    {
        $reo = $this->findVisibleReo($id);
        $user = auth()->user();
        $reo->setAttribute('can_approve', $this->canApprove($reo, $user));
        $reo->setAttribute('status_label', $reo->statusLabel());

        return response()->json([
            'success' => true,
            'reo' => $reo,
        ]);
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
            'revenue_items.*.date' => 'nullable|date',
            'revenue_items.*.arrangement' => 'nullable|string|max:500',
            'revenue_items.*.date_arrangement' => 'nullable|string|max:500', // legacy
            'revenue_items.*.qty' => 'nullable|numeric|min:0',
            'revenue_items.*.days' => 'nullable|numeric|min:0',
            'revenue_items.*.rate' => 'nullable|numeric|min:0',
            'revenue_items.*.rate_nett' => 'nullable|numeric|min:0',
            'revenue_items.*.total_amount' => 'nullable|numeric|min:0',
            'revenue_items.*.total_amount_nett' => 'nullable|numeric|min:0',
            'revenue_items.*.breakdown' => 'nullable|numeric|min:0', // legacy
            'revenue_items.*.breakdown_nett' => 'nullable|numeric|min:0', // legacy
            'catering_fee_percent' => 'nullable|numeric|min:0|max:100',
            'pax' => 'nullable|integer|min:0',
            'date_of_event' => 'nullable|date',
            'time_of_event' => 'nullable|array',
            'time_of_event.*.time' => 'nullable|string|max:100',
            'time_of_event.*.meals' => 'nullable|string|max:255',
            'time_of_event.*.venue' => 'nullable|string|max:255',
            'time_of_event.*.pax' => 'nullable|numeric|min:0',
            'time_of_event.*.set_up' => 'nullable|string|max:255',
            'time_of_event.*.venue_setup' => 'nullable|string|max:255', // legacy
            'kitchen_maintenance' => 'nullable|string|max:5000',
            'maintenance' => 'nullable|string|max:5000',
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
            'approvers' => 'nullable|array',
            'approvers.*' => 'integer|exists:users,id',
            'submit_now' => 'nullable|boolean',
        ]);

        // Fields di atas tidak ikut ke fillable create/update mass-assign via validated merge
        unset($validated['approvers'], $validated['submit_now']);

        $validated['date_of_issue'] = $this->normalizeDate($validated['date_of_issue'] ?? null);
        $validated['date_of_event'] = $this->normalizeDate($validated['date_of_event'] ?? null);
        $validated['revenue_items'] = array_values(array_filter(
            array_map(function ($row) {
                $date = $row['date'] ?? null;
                $arrangement = $row['arrangement'] ?? null;
                if (! $date && ! $arrangement && ! empty($row['date_arrangement'])) {
                    $arrangement = $row['date_arrangement'];
                }

                return [
                    'date' => $date ? $this->normalizeDate($date) : null,
                    'arrangement' => $arrangement,
                    'qty' => $row['qty'] ?? null,
                    'days' => $row['days'] ?? null,
                    'rate' => $row['rate'] ?? null,
                    'rate_nett' => $row['rate_nett'] ?? null,
                    'total_amount' => $row['total_amount'] ?? ($row['breakdown'] ?? null),
                    'total_amount_nett' => $row['total_amount_nett'] ?? ($row['breakdown_nett'] ?? null),
                ];
            }, $validated['revenue_items'] ?? []),
            fn ($row) => filled($row['date'] ?? null)
                || filled($row['arrangement'] ?? null)
                || (float) ($row['qty'] ?? 0) > 0
                || (float) ($row['rate'] ?? 0) > 0
                || (float) ($row['total_amount'] ?? 0) > 0
        ));
        $validated['time_of_event'] = array_values(array_filter(
            array_map(function ($row) {
                $venue = $row['venue'] ?? $row['venue_setup'] ?? null;

                return [
                    'time' => $row['time'] ?? null,
                    'meals' => $row['meals'] ?? null,
                    'venue' => $venue,
                    'pax' => isset($row['pax']) && $row['pax'] !== '' ? (float) $row['pax'] : null,
                    'set_up' => $row['set_up'] ?? null,
                ];
            }, $validated['time_of_event'] ?? []),
            fn ($row) => filled($row['time'] ?? null)
                || filled($row['meals'] ?? null)
                || filled($row['venue'] ?? null)
                || filled($row['set_up'] ?? null)
                || (($row['pax'] ?? null) !== null && (float) $row['pax'] > 0)
        ));
        $validated['catering_fee_percent'] = $validated['catering_fee_percent'] ?? 30;

        return $validated;
    }

    private function computeTotals(array $validated): array
    {
        $subtotalRate = 0.0;
        $subtotalNett = 0.0;
        foreach ($validated['revenue_items'] ?? [] as $row) {
            $subtotalRate += (float) ($row['total_amount'] ?? ($row['breakdown'] ?? 0));
            $subtotalNett += (float) ($row['total_amount_nett'] ?? ($row['breakdown_nett'] ?? 0));
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

        return $this->ownsReo($reo, $user);
    }

    private function canDelete(SalesLeadReo $reo, $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($this->isSuperadmin($user)) {
            return true;
        }
        // Draft/rejected/submitted boleh dihapus pemilik; approved tidak (kecuali superadmin).
        if (! in_array($reo->status, ['DRAFT', 'REJECTED', 'SUBMITTED'], true)) {
            return false;
        }

        return $this->ownsReo($reo, $user);
    }

    private function ownsReo(SalesLeadReo $reo, $user): bool
    {
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

    private function applyApproversAndSubmit(SalesLeadReo $reo, array $approvers, $user): void
    {
        $approverIds = array_values(array_filter(array_map('intval', $approvers)));
        if (count($approverIds) < 1) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'approvers' => 'Minimal 1 approver diperlukan untuk submit REO.',
            ]);
        }

        DB::transaction(function () use ($reo, $approverIds, $user) {
            $reo->approvals()->delete();
            foreach ($approverIds as $index => $approverId) {
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

        PendingApprovalCache::forgetForUsers(array_merge($approverIds, [$user->id]));
    }

    /**
     * Isi label signature PDF dari sales person + approver flow (bukan input manual).
     */
    private function syncSignatureLabelsFromApprovals(SalesLeadReo $reo): void
    {
        $approved = $reo->approvals
            ->where('status', 'APPROVED')
            ->sortBy('approval_level')
            ->values();

        $data = [
            'prepared_by_name' => $reo->sales_person_name ?: $reo->prepared_by_name,
            'prepared_by_title' => $reo->prepared_by_title ?: 'Corporate Sales Executive',
        ];

        if ($approved->count() === 1) {
            $data['approved_by_name'] = $approved[0]->approver?->nama_lengkap;
            $data['approved_by_title'] = $approved[0]->approver?->jabatan?->nama_jabatan
                ?? $reo->approved_by_title
                ?? 'Approved by';
        } elseif ($approved->count() === 2) {
            $data['acknowledge_by_1_name'] = $approved[0]->approver?->nama_lengkap;
            $data['acknowledge_by_1_title'] = $approved[0]->approver?->jabatan?->nama_jabatan ?? 'Acknowledge by';
            $data['approved_by_name'] = $approved[1]->approver?->nama_lengkap;
            $data['approved_by_title'] = $approved[1]->approver?->jabatan?->nama_jabatan ?? 'Approved by';
        } elseif ($approved->count() >= 3) {
            $data['acknowledge_by_1_name'] = $approved[0]->approver?->nama_lengkap;
            $data['acknowledge_by_1_title'] = $approved[0]->approver?->jabatan?->nama_jabatan ?? 'Acknowledge by';
            $data['acknowledge_by_2_name'] = $approved[1]->approver?->nama_lengkap;
            $data['acknowledge_by_2_title'] = $approved[1]->approver?->jabatan?->nama_jabatan ?? 'Acknowledge by';
            $last = $approved->last();
            $data['approved_by_name'] = $last->approver?->nama_lengkap;
            $data['approved_by_title'] = $last->approver?->jabatan?->nama_jabatan ?? 'Approved by';
        }

        $reo->update($data);
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
