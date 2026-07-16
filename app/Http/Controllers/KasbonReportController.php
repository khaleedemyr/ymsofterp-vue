<?php

namespace App\Http\Controllers;

use App\Exports\KasbonReportExport;
use App\Models\PrKasbon;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class KasbonReportController extends Controller
{
    public function index(Request $request)
    {
        $this->assertUserCanAccessReportKasbon();

        return Inertia::render('Reports/KasbonReport', $this->kasbonReportViewModel($request));
    }

    /**
     * Approval app (JSON): sama filter & struktur data dengan halaman web.
     */
    public function apiIndex(Request $request)
    {
        $this->assertUserCanAccessReportKasbon();

        return response()->json($this->kasbonReportViewModel($request));
    }

    /**
     * Export Excel (filter sama dengan index). Dipakai web (session) dan approval-app (Bearer).
     */
    public function exportExcel(Request $request)
    {
        $this->assertUserCanAccessReportKasbon();
        abort_unless(Schema::hasTable('pr_kasbons'), 404);

        $dateFrom = $request->input('date_from') ?: now()->startOfMonth()->format('Y-m-d');
        $dateTo = $request->input('date_to') ?: now()->endOfMonth()->format('Y-m-d');
        $query = $this->buildKasbonReportQuery($request, $dateFrom, $dateTo);

        $rows = (clone $query)
            ->select([
                'k.id',
                'k.purchase_requisition_id',
                'k.pr_number',
                'k.outlet_id',
                'k.division_id',
                'k.employee_user_id',
                'k.total_amount',
                'k.termin_total',
                'k.installment_amount',
                'k.paid_installments',
                'k.status',
                'k.approved_at',
                'k.last_installment_at',
                'k.created_at',
                'k.updated_at',
                'o.nama_outlet as outlet_name',
                'd.nama_divisi as division_name',
                'emp.nama_lengkap as employee_name',
                'pr.status as pr_status',
                'nfp.payment_number as nfp_payment_number',
                'nfp.status as nfp_payment_status',
                DB::raw('COALESCE(nfp.approved_at, nfp.approved_gm_finance_at, nfp.approved_finance_manager_at) as nfp_transfer_approved_at'),
                DB::raw(
                    "CASE " .
                    "WHEN k.status = 'completed' THEN 'completed' " .
                    "WHEN nfp.id IS NULL OR nfp.status IS NULL OR nfp.status <> 'paid' THEN 'waiting_transfer' " .
                    "ELSE 'active' " .
                    "END as tracker_status"
                ),
            ])
            ->orderByDesc('k.approved_at')
            ->orderByDesc('k.id')
            ->limit(15000)
            ->get();

        $this->attachPrApprovalStepsToRows($rows);

        $filename = 'report_kasbon_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new KasbonReportExport($rows), $filename);
    }

    /**
     * @return array<string, mixed>
     */
    private function kasbonReportViewModel(Request $request): array
    {
        if (! Schema::hasTable('pr_kasbons')) {
            return [
                'tableMissing' => true,
                'kasbons' => [],
                'summary' => null,
                'divisions' => $this->divisions(),
                'outlets' => $this->outlets(),
                'pagination' => [
                    'total' => 0,
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 15,
                    'from' => null,
                    'to' => null,
                ],
                'filters' => $this->defaultFilters($request),
            ];
        }

        $perPage = min(100, max(5, (int) $request->input('per_page', 15)));
        $page = max(1, (int) $request->input('page', 1));
        $dateFrom = $request->input('date_from') ?: now()->startOfMonth()->format('Y-m-d');
        $dateTo = $request->input('date_to') ?: now()->endOfMonth()->format('Y-m-d');

        $query = $this->buildKasbonReportQuery($request, $dateFrom, $dateTo);

        $summary = (clone $query)->selectRaw(
            "COUNT(*) as total_rows, " .
            "SUM(CASE WHEN k.status <> 'completed' AND nfp.id IS NOT NULL AND nfp.status = 'paid' THEN 1 ELSE 0 END) as active_count, " .
            "SUM(CASE WHEN k.status <> 'completed' AND (nfp.id IS NULL OR nfp.status IS NULL OR nfp.status <> 'paid') THEN 1 ELSE 0 END) as waiting_transfer_count, " .
            "SUM(CASE WHEN k.status = 'completed' THEN 1 ELSE 0 END) as completed_count, " .
            "COALESCE(SUM(k.total_amount), 0) as sum_total_amount"
        )->first();

        $paginator = (clone $query)
            ->select([
                'k.id',
                'k.purchase_requisition_id',
                'k.pr_number',
                'k.outlet_id',
                'k.division_id',
                'k.employee_user_id',
                'k.total_amount',
                'k.termin_total',
                'k.installment_amount',
                'k.paid_installments',
                'k.status',
                'k.approved_at',
                'k.last_installment_at',
                'k.created_at',
                'k.updated_at',
                'o.nama_outlet as outlet_name',
                'd.nama_divisi as division_name',
                'emp.nama_lengkap as employee_name',
                'pr.status as pr_status',
                'nfp.payment_number as nfp_payment_number',
                'nfp.status as nfp_payment_status',
                DB::raw('COALESCE(nfp.approved_at, nfp.approved_gm_finance_at, nfp.approved_finance_manager_at) as nfp_transfer_approved_at'),
                DB::raw(
                    "CASE " .
                    "WHEN k.status = 'completed' THEN 'completed' " .
                    "WHEN nfp.id IS NULL OR nfp.status IS NULL OR nfp.status <> 'paid' THEN 'waiting_transfer' " .
                    "ELSE 'active' " .
                    "END as tracker_status"
                ),
            ])
            ->orderByDesc('k.approved_at')
            ->orderByDesc('k.id')
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();

        $kasbonItems = $paginator->items();
        $this->attachPrApprovalStepsToRows($kasbonItems);

        return [
            'tableMissing' => false,
            'kasbons' => $kasbonItems,
            'summary' => [
                'total_rows' => (int) ($summary->total_rows ?? 0),
                'active_count' => (int) ($summary->active_count ?? 0),
                'waiting_transfer_count' => (int) ($summary->waiting_transfer_count ?? 0),
                'completed_count' => (int) ($summary->completed_count ?? 0),
                'sum_total_amount' => (float) ($summary->sum_total_amount ?? 0),
            ],
            'divisions' => $this->divisions(),
            'outlets' => $this->outlets(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => array_merge($this->defaultFilters($request), [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'per_page' => $perPage,
                'page' => $page,
            ]),
        ];
    }

    private function defaultFilters(Request $request): array
    {
        return [
            'status' => $request->input('status', 'all'),
            'division_id' => $request->input('division_id', ''),
            'outlet_id' => $request->input('outlet_id', ''),
            'search' => $request->input('search', ''),
        ];
    }

    private function buildKasbonReportQuery(Request $request, string $dateFrom, string $dateTo): Builder
    {
        $status = $request->input('status', 'all');
        $divisionId = $request->input('division_id');
        $outletId = $request->input('outlet_id');
        $search = $request->input('search');

        $query = DB::table('pr_kasbons as k')
            ->leftJoin('purchase_requisitions as pr', 'pr.id', '=', 'k.purchase_requisition_id')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'k.outlet_id')
            ->leftJoin('users as emp', 'emp.id', '=', 'k.employee_user_id')
            ->leftJoin('tbl_data_divisi as d', 'd.id', '=', 'k.division_id');

        $nfpLatestSub = DB::table('non_food_payments')
            ->select('purchase_requisition_id', DB::raw('MAX(id) as latest_nfp_id'))
            ->whereNotNull('purchase_requisition_id')
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->groupBy('purchase_requisition_id');

        $query->leftJoinSub($nfpLatestSub, 'nfp_idx', function ($join) {
            $join->on('pr.id', '=', 'nfp_idx.purchase_requisition_id');
        });
        $query->leftJoin('non_food_payments as nfp', 'nfp.id', '=', 'nfp_idx.latest_nfp_id');

        if ($status === 'active') {
            $query->where('k.status', '!=', 'completed')
                ->whereNotNull('nfp.id')
                ->where('nfp.status', 'paid');
        } elseif ($status === 'waiting_transfer') {
            $query->where('k.status', '!=', 'completed')
                ->where(function ($q) {
                    $q->whereNull('nfp.id')
                        ->orWhereNull('nfp.status')
                        ->orWhere('nfp.status', '<>', 'paid');
                });
        } elseif ($status === 'completed') {
            $query->where('k.status', 'completed');
        } elseif ($status !== 'all') {
            $query->where('k.status', $status);
        }
        if ($divisionId) {
            $query->where('k.division_id', $divisionId);
        }
        if ($outletId) {
            $query->where('k.outlet_id', $outletId);
        }
        if ($dateFrom) {
            $query->whereDate('k.approved_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('k.approved_at', '<=', $dateTo);
        }
        if ($search) {
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('k.pr_number', 'like', $term)
                    ->orWhere('emp.nama_lengkap', 'like', $term);
            });
        }

        return $query;
    }

    /**
     * Catat 1x pembayaran cicilan manual (naikkan paid_installments, set completed jika sudah penuh).
     */
    public function recordInstallment(Request $request, int $id)
    {
        $this->assertUserCanAccessReportKasbon();
        abort_unless(Schema::hasTable('pr_kasbons'), 404);

        $wantsJson = $request->expectsJson();

        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
            'paid_at' => 'nullable|date',
        ]);

        try {
            DB::transaction(function () use ($id, $validated) {
                $k = PrKasbon::query()->lockForUpdate()->findOrFail($id);
                $termin = max(1, (int) $k->termin_total);
                $paid = (int) $k->paid_installments;
                if ($k->status === 'completed' || $paid >= $termin) {
                    throw new \RuntimeException('Kasbon sudah lunas, tidak bisa menambah cicilan.');
                }

                $latestNfp = DB::table('non_food_payments')
                    ->where('purchase_requisition_id', $k->purchase_requisition_id)
                    ->whereNotIn('status', ['rejected', 'cancelled'])
                    ->orderByDesc('id')
                    ->first();
                if (! $latestNfp || ($latestNfp->status ?? '') !== 'paid') {
                    throw new \RuntimeException(
                        'Non Food Payment untuk PR ini belum berstatus paid. Catat cicilan setelah transfer selesai (NFP paid).'
                    );
                }
                $newPaid = $paid + 1;
                $paidAt = ! empty($validated['paid_at'])
                    ? Carbon::parse($validated['paid_at'])->startOfDay()
                    : now();

                $user = Auth::user();
                $uname = $user && ($user->nama_lengkap ?? null)
                    ? $user->nama_lengkap
                    : (($user && ($user->name ?? null)) ? $user->name : 'user #' . Auth::id());
                $line = '[' . now()->format('Y-m-d H:i') . '] Cicilan ' . $newPaid . '/' . $termin . ' dicatat oleh ' . $uname;
                if (! empty($validated['notes'])) {
                    $line .= ' — ' . trim($validated['notes']);
                }
                $mergedNotes = trim(($k->notes ? $k->notes . "\n" : '') . $line);
                if (strlen($mergedNotes) > 2000) {
                    $mergedNotes = substr($mergedNotes, -2000);
                }

                $k->update([
                    'paid_installments' => $newPaid,
                    'last_installment_at' => $paidAt,
                    'status' => $newPaid >= $termin ? 'completed' : 'active',
                    'notes' => $mergedNotes,
                ]);
            });
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            abort(404);
        } catch (\RuntimeException $e) {
            if ($wantsJson) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['installment' => $e->getMessage()]);
        }

        if ($wantsJson) {
            return response()->json(['success' => true, 'message' => 'Pembayaran cicilan berhasil dicatat.']);
        }

        return redirect()->back()->with('success', 'Pembayaran cicilan berhasil dicatat.');
    }

    /**
     * Batalkan 1x pencatatan cicilan terakhir (turunkan paid_installments, status aktif jika sebelumnya completed).
     */
    public function reverseInstallment(Request $request, int $id)
    {
        $this->assertUserCanAccessReportKasbon();
        abort_unless(Schema::hasTable('pr_kasbons'), 404);

        $wantsJson = $request->expectsJson();

        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            DB::transaction(function () use ($id, $validated) {
                $k = PrKasbon::query()->lockForUpdate()->findOrFail($id);
                $termin = max(1, (int) $k->termin_total);
                $paid = (int) $k->paid_installments;
                if ($paid <= 0) {
                    throw new \RuntimeException('Belum ada cicilan yang tercatat, tidak ada yang dibatalkan.');
                }

                $newPaid = $paid - 1;
                $user = Auth::user();
                $uname = $user && ($user->nama_lengkap ?? null)
                    ? $user->nama_lengkap
                    : (($user && ($user->name ?? null)) ? $user->name : 'user #' . Auth::id());
                $line = '[' . now()->format('Y-m-d H:i') . '] Pembatalan 1x cicilan (menjadi ' . $newPaid . '/' . $termin . ') oleh ' . $uname;
                if (! empty($validated['notes'])) {
                    $line .= ' — ' . trim($validated['notes']);
                }
                $mergedNotes = trim(($k->notes ? $k->notes . "\n" : '') . $line);
                if (strlen($mergedNotes) > 2000) {
                    $mergedNotes = substr($mergedNotes, -2000);
                }

                $k->update([
                    'paid_installments' => $newPaid,
                    'last_installment_at' => null,
                    'status' => $newPaid >= $termin ? 'completed' : 'active',
                    'notes' => $mergedNotes,
                ]);
            });
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            abort(404);
        } catch (\RuntimeException $e) {
            if ($wantsJson) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['installment' => $e->getMessage()]);
        }

        if ($wantsJson) {
            return response()->json(['success' => true, 'message' => 'Pencatatan cicilan terakhir berhasil dibatalkan.']);
        }

        return redirect()->back()->with('success', 'Pencatatan cicilan terakhir berhasil dibatalkan.');
    }

    /**
     * Sama seperti visibilitas menu Report Kasbon (erp_permission report_kasbon_view).
     */
    private function assertUserCanAccessReportKasbon(): void
    {
        $uid = Auth::id();
        abort_unless($uid, 403);

        $user = Auth::user();
        if ($user && (($user->id_role ?? null) === '5af56935b011a' || in_array((int) ($user->id_jabatan ?? 0), [160, 317], true))) {
            return;
        }

        $ok = DB::table('erp_user_role as ur')
            ->join('erp_role_permission as rp', 'rp.role_id', '=', 'ur.role_id')
            ->join('erp_permission as p', 'p.id', '=', 'rp.permission_id')
            ->join('erp_menu as m', 'm.id', '=', 'p.menu_id')
            ->where('ur.user_id', $uid)
            ->where('m.code', 'report_kasbon')
            ->where('p.code', 'report_kasbon_view')
            ->exists();

        abort_unless($ok, 403);
    }

    private function divisions()
    {
        return DB::table('tbl_data_divisi')
            ->select('id', 'nama_divisi as name')
            ->orderBy('nama_divisi')
            ->get();
    }

    private function outlets()
    {
        return DB::table('tbl_data_outlet')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();
    }

    /**
     * Lampirkan riwayat approve PR per baris (dari purchase_requisition_approval_flows).
     *
     * @param  iterable<int, object>  $rows
     */
    private function attachPrApprovalStepsToRows(iterable $rows): void
    {
        $prIds = collect($rows)
            ->pluck('purchase_requisition_id')
            ->filter()
            ->unique()
            ->values();

        if ($prIds->isEmpty()) {
            return;
        }

        $flowsByPr = collect();
        if (Schema::hasTable('purchase_requisition_approval_flows')) {
            $flowsByPr = DB::table('purchase_requisition_approval_flows as praf')
                ->leftJoin('users as u', 'u.id', '=', 'praf.approver_id')
                ->whereIn('praf.purchase_requisition_id', $prIds)
                ->where('praf.status', 'APPROVED')
                ->whereNotNull('praf.approved_at')
                ->orderBy('praf.purchase_requisition_id')
                ->orderBy('praf.approval_level')
                ->select([
                    'praf.purchase_requisition_id',
                    'praf.approval_level',
                    'praf.approved_at',
                    'u.nama_lengkap as approver_name',
                ])
                ->get()
                ->groupBy('purchase_requisition_id');
        }

        $legacyByPr = DB::table('purchase_requisitions as pr')
            ->leftJoin('users as u_cc', 'u_cc.id', '=', 'pr.approved_cc_by')
            ->leftJoin('users as u_ssd', 'u_ssd.id', '=', 'pr.approved_ssd_by')
            ->whereIn('pr.id', $prIds)
            ->select([
                'pr.id',
                'pr.approved_cc_at',
                'pr.approved_ssd_at',
                'u_cc.nama_lengkap as approved_cc_name',
                'u_ssd.nama_lengkap as approved_ssd_name',
            ])
            ->get()
            ->keyBy('id');

        foreach ($rows as $row) {
            $prId = $row->purchase_requisition_id ?? null;
            $steps = [];

            if ($prId && isset($flowsByPr[$prId])) {
                foreach ($flowsByPr[$prId] as $flow) {
                    $steps[] = [
                        'level' => (int) ($flow->approval_level ?? 0),
                        'approver_name' => $flow->approver_name ?: '—',
                        'approved_at' => $flow->approved_at
                            ? Carbon::parse($flow->approved_at)->toIso8601String()
                            : null,
                    ];
                }
            }

            if ($steps === [] && $prId && isset($legacyByPr[$prId])) {
                $legacy = $legacyByPr[$prId];
                if (! empty($legacy->approved_cc_at)) {
                    $steps[] = [
                        'level' => 1,
                        'approver_name' => $legacy->approved_cc_name ?: 'Cost Control',
                        'approved_at' => Carbon::parse($legacy->approved_cc_at)->toIso8601String(),
                    ];
                }
                if (! empty($legacy->approved_ssd_at)) {
                    $steps[] = [
                        'level' => 2,
                        'approver_name' => $legacy->approved_ssd_name ?: 'SSD',
                        'approved_at' => Carbon::parse($legacy->approved_ssd_at)->toIso8601String(),
                    ];
                }
            }

            $row->pr_approval_steps = $steps;
        }
    }
}
