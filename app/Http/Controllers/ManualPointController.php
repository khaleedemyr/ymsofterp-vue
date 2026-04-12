<?php

namespace App\Http\Controllers;

use App\Models\MemberAppsMember;
use App\Models\MemberAppsPointTransaction;
use App\Services\MemberTierService;
use App\Services\PointEarningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class ManualPointController extends Controller
{
    /** Marker appended by PointEarningService for ERP manual backfill (same rules as POS earn). */
    public const ERP_MANUAL_DESCRIPTION_LIKE = '%[ERP manual inject]%';

    protected $pointEarningService;

    public function __construct(PointEarningService $pointEarningService)
    {
        $this->pointEarningService = $pointEarningService;
    }

    /**
     * Manual injections: legacy adjustment rows, or earn rows created from ERP manual POS backfill.
     */
    protected function scopeManualInjections($query)
    {
        return $query->where(function ($q) {
            $q->where(function ($q2) {
                $q2->where('transaction_type', 'adjustment')
                    ->where('channel', 'adjustment');
            })->orWhere(function ($q2) {
                $q2->where('transaction_type', 'earn')
                    ->where('description', 'like', self::ERP_MANUAL_DESCRIPTION_LIKE);
            });
        });
    }

    /**
     * Display list of manual point injections
     */
    public function index(Request $request)
    {
        $query = MemberAppsPointTransaction::with([
            'member',
            'earning:id,point_transaction_id,remaining_points',
        ])
            ->tap(fn ($q) => $this->scopeManualInjections($q))
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_id', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('member', function ($memberQuery) use ($search) {
                        $memberQuery->where('member_id', 'like', "%{$search}%")
                            ->orWhere('nama_lengkap', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('date_from')) {
            $query->where('transaction_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('transaction_date', '<=', $request->date_to);
        }

        $perPage = $request->get('per_page', 15);
        $transactions = $query->paginate($perPage)->withQueryString();
        $transactions->setCollection(
            $transactions->getCollection()->map(function ($transaction) {
                $remainingPoints = $transaction->earning?->remaining_points ?? 0;
                $usedPoints = max(0, $transaction->point_amount - $remainingPoints);
                $transaction->can_delete = $usedPoints === 0;
                return $transaction;
            })
        );

        $statsBase = function () {
            $q = MemberAppsPointTransaction::query();
            $this->scopeManualInjections($q);

            return $q;
        };

        $stats = [
            'total_injections' => $statsBase()->count(),
            'total_points_injected' => $statsBase()->where('point_amount', '>', 0)->sum('point_amount'),
            'today_injections' => $statsBase()->whereDate('created_at', today())->count(),
        ];

        return Inertia::render('ManualPoint/Index', [
            'transactions' => $transactions,
            'stats' => $stats,
            'filters' => $request->only(['search', 'date_from', 'date_to', 'per_page']),
        ]);
    }

    public function create()
    {
        return Inertia::render('ManualPoint/Create');
    }

    public function searchMembers(Request $request)
    {
        $search = trim((string) $request->get('search', ''));

        if ($search === '') {
            return response()->json([]);
        }

        // Hanya angka: cocokkan id primary key DB atau kolom member_id persis (termasuk "1").
        // Tanpa filter is_active — staff ERP tetap perlu backfill meski member nonaktif.
        if (ctype_digit($search)) {
            $members = MemberAppsMember::query()
                ->where(function ($query) use ($search) {
                    $query->where('id', (int) $search)
                        ->orWhere('member_id', $search);
                });
        } else {
            if (strlen($search) < 2) {
                return response()->json([]);
            }
            $members = MemberAppsMember::where('is_active', true)
                ->where(function ($query) use ($search) {
                    $query->where('member_id', 'like', "%{$search}%")
                        ->orWhere('nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile_phone', 'like', "%{$search}%");
                });
        }

        $members = $members
            ->select('id', 'member_id', 'nama_lengkap', 'email', 'mobile_phone', 'member_level', 'just_points')
            ->limit(20)
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->id,
                    'member_id' => $member->member_id,
                    'nama_lengkap' => $member->nama_lengkap,
                    'email' => $member->email,
                    'mobile_phone' => $member->mobile_phone,
                    'member_level' => $member->member_level,
                    'just_points' => $member->just_points ?? 0,
                    'label' => $member->nama_lengkap.' ('.$member->member_id.') - '.($member->just_points ?? 0).' points',
                ];
            });

        return response()->json($members);
    }

    /**
     * Backfill POS earn: same rules as POST /mobile/member/points/earn (amount + tier rate, tier spending).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer|exists:member_apps_members,id',
            'transaction_amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'paid_number' => 'required|string|max:255',
            'channel' => 'nullable|string|in:dine-in,take-away,delivery-restaurant,gift-voucher,e-commerce,pos',
            'is_gift_voucher_payment' => 'nullable|boolean',
            'is_ecommerce_order' => 'nullable|boolean',
            'description' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $paidNumber = $request->paid_number;
        $existingTransaction = MemberAppsPointTransaction::where('reference_id', $paidNumber)
            ->where('transaction_type', 'earn')
            ->first();

        if ($existingTransaction) {
            return back()->withErrors([
                'paid_number' => 'Poin untuk nomor bill / paid_number ini sudah pernah tercatat (earn). Tidak bisa dobel.',
            ])->withInput();
        }

        try {
            $member = MemberAppsMember::findOrFail($request->member_id);

            $result = $this->pointEarningService->earnPointsFromOrder(
                $member->id,
                $paidNumber,
                (float) $request->transaction_amount,
                $request->transaction_date,
                $request->input('channel', 'pos'),
                $request->boolean('is_gift_voucher_payment'),
                $request->boolean('is_ecommerce_order'),
                [
                    'manual_note' => $request->description,
                    'manual_injected_by_user_id' => auth()->id(),
                ]
            );

            if (! $result) {
                return back()->withErrors([
                    'error' => 'Tidak ada poin yang dicatat. Periksa: nominal terlalu kecil untuk menghasilkan poin, channel tidak mendapat poin, pembayaran voucher, atau order e-commerce. Atau hanya sisa desimal point (point_remainder) yang tersimpan.',
                ])->withInput();
            }

            $points = $result['points_earned'];
            $member->refresh();

            return redirect()->route('manual-point.index')
                ->with('success', "Backfill POS berhasil: {$points} poin untuk {$member->nama_lengkap} (total {$member->just_points} poin), bill {$paidNumber}.");
        } catch (\Exception $e) {
            Log::error('Error manual POS point backfill', [
                'member_id' => $request->member_id,
                'paid_number' => $paidNumber ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Gagal: '.$e->getMessage()])->withInput();
        }
    }

    public function show($id)
    {
        $transaction = MemberAppsPointTransaction::with(['member', 'earning'])
            ->tap(fn ($q) => $this->scopeManualInjections($q))
            ->findOrFail($id);

        $remainingPoints = $transaction->earning?->remaining_points ?? 0;
        $usedPoints = max(0, $transaction->point_amount - $remainingPoints);
        $canDelete = $usedPoints === 0;

        return Inertia::render('ManualPoint/Show', [
            'transaction' => $transaction,
            'can_delete' => $canDelete,
            'delete_block_reason' => $canDelete
                ? null
                : "Point injection ini tidak bisa dihapus karena sudah terpakai {$usedPoints} points.",
        ]);
    }

    /**
     * Delete manual injection: legacy adjustment, or ERP earn backfill (reverses tier spending for earn).
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $transaction = MemberAppsPointTransaction::with(['member', 'earning'])
                ->tap(fn ($q) => $this->scopeManualInjections($q))
                ->lockForUpdate()
                ->findOrFail($id);

            $member = $transaction->member;
            $earning = $transaction->earning;

            if (! $member || ! $earning) {
                DB::rollBack();

                return back()->withErrors(['error' => 'Data point injection tidak lengkap dan tidak bisa dihapus.']);
            }

            $remainingPoints = (int) $earning->remaining_points;
            $usedPoints = max(0, (int) $transaction->point_amount - $remainingPoints);
            if ($usedPoints > 0) {
                DB::rollBack();

                return back()->withErrors(['error' => "Point injection tidak bisa dihapus karena sudah terpakai {$usedPoints} points."]);
            }

            $rollbackPoint = (int) $transaction->point_amount;
            if ((int) $member->just_points < $rollbackPoint) {
                DB::rollBack();

                return back()->withErrors(['error' => 'Point member saat ini lebih kecil dari point injection. Penghapusan dibatalkan untuk menjaga konsistensi data.']);
            }

            $member->decrement('just_points', $rollbackPoint);
            $member->refresh();

            if ($transaction->transaction_type === 'earn' && str_contains((string) $transaction->description, '[ERP manual inject')) {
                $txAmount = (float) ($transaction->transaction_amount ?? 0);
                if ($txAmount > 0) {
                    MemberTierService::reverseRecordedTransaction(
                        $member->id,
                        $txAmount,
                        $transaction->transaction_date
                    );
                }
            }

            $earning->delete();
            $transaction->delete();

            DB::commit();

            Log::info('Manual point injection deleted', [
                'transaction_id' => $id,
                'member_id' => $member->id,
                'point_amount' => $rollbackPoint,
                'deleted_by' => auth()->id(),
                'member_points_after' => $member->just_points,
            ]);

            return redirect()->route('manual-point.index')
                ->with('success', "Point injection berhasil dihapus. {$rollbackPoint} points dikurangi dari member {$member->nama_lengkap}.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting manual point injection', [
                'transaction_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Gagal menghapus point injection: '.$e->getMessage()]);
        }
    }
}
