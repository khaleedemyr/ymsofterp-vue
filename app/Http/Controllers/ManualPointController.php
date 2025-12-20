<?php

namespace App\Http\Controllers;

use App\Models\MemberAppsMember;
use App\Models\MemberAppsPointTransaction;
use App\Models\MemberAppsPointEarning;
use App\Services\PointEarningService;
use App\Events\PointEarned;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Carbon\Carbon;

class ManualPointController extends Controller
{
    protected $pointEarningService;

    public function __construct(PointEarningService $pointEarningService)
    {
        $this->pointEarningService = $pointEarningService;
    }

    /**
     * Display list of manual point injections
     */
    public function index(Request $request)
    {
        $query = MemberAppsPointTransaction::with(['member'])
            ->where('transaction_type', 'adjustment')
            ->where('channel', 'adjustment')
            ->orderBy('created_at', 'desc');

        // Search
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

        // Filter by date
        if ($request->filled('date_from')) {
            $query->where('transaction_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('transaction_date', '<=', $request->date_to);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $transactions = $query->paginate($perPage)->withQueryString();

        // Stats
        $stats = [
            'total_injections' => MemberAppsPointTransaction::where('transaction_type', 'adjustment')
                ->where('channel', 'adjustment')
                ->count(),
            'total_points_injected' => MemberAppsPointTransaction::where('transaction_type', 'adjustment')
                ->where('channel', 'adjustment')
                ->where('point_amount', '>', 0)
                ->sum('point_amount'),
            'today_injections' => MemberAppsPointTransaction::where('transaction_type', 'adjustment')
                ->where('channel', 'adjustment')
                ->whereDate('created_at', today())
                ->count(),
        ];

        return Inertia::render('ManualPoint/Index', [
            'transactions' => $transactions,
            'stats' => $stats,
            'filters' => $request->only(['search', 'date_from', 'date_to', 'per_page']),
        ]);
    }

    /**
     * Show form for manual point injection
     */
    public function create()
    {
        return Inertia::render('ManualPoint/Create');
    }

    /**
     * Search members for autocomplete
     */
    public function searchMembers(Request $request)
    {
        $search = $request->get('search', '');

        if (strlen($search) < 2) {
            return response()->json([]);
        }

        $members = MemberAppsMember::where('is_active', true)
            ->where(function ($query) use ($search) {
                $query->where('member_id', 'like', "%{$search}%")
                    ->orWhere('nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile_phone', 'like', "%{$search}%");
            })
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
                    'label' => $member->nama_lengkap . ' (' . $member->member_id . ') - ' . ($member->just_points ?? 0) . ' points',
                ];
            });

        return response()->json($members);
    }

    /**
     * Store manual point injection
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer|exists:member_apps_members,id',
            'point_amount' => 'required|integer|min:1|max:100000',
            'transaction_date' => 'required|date',
            'reference_id' => 'nullable|string|max:255',
            'description' => 'required|string|max:500',
            'expires_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $member = MemberAppsMember::findOrFail($request->member_id);
            $pointAmount = $request->point_amount;
            $transactionDate = Carbon::parse($request->transaction_date);
            $referenceId = $request->reference_id ?? 'MANUAL-' . now()->format('YmdHis') . '-' . $member->id;
            $description = $request->description;
            
            // Calculate expiration date (default: 1 year from transaction date)
            $expiresAt = $request->expires_at 
                ? Carbon::parse($request->expires_at) 
                : $transactionDate->copy()->addYear();

            // Create point transaction
            $pointTransaction = MemberAppsPointTransaction::create([
                'member_id' => $member->id,
                'transaction_type' => 'adjustment',
                'transaction_date' => $transactionDate->format('Y-m-d'),
                'point_amount' => $pointAmount,
                'transaction_amount' => null, // Manual injection doesn't have transaction amount
                'earning_rate' => null, // Manual injection doesn't have earning rate
                'channel' => 'adjustment',
                'reference_id' => $referenceId,
                'description' => $description,
                'expires_at' => $expiresAt->format('Y-m-d'),
                'is_expired' => false,
            ]);

            // Create point earning record (for FIFO tracking)
            $pointEarning = MemberAppsPointEarning::create([
                'member_id' => $member->id,
                'point_transaction_id' => $pointTransaction->id,
                'point_amount' => $pointAmount,
                'remaining_points' => $pointAmount, // Initially all points are remaining
                'earned_at' => $transactionDate->format('Y-m-d'),
                'expires_at' => $expiresAt->format('Y-m-d'),
                'is_expired' => false,
                'is_fully_redeemed' => false,
            ]);

            // Update member's total points
            $member->increment('just_points', $pointAmount);
            
            // Refresh member to get updated points
            $member->refresh();

            DB::commit();

            Log::info('Manual point injection successful', [
                'member_id' => $member->id,
                'member_member_id' => $member->member_id,
                'point_amount' => $pointAmount,
                'transaction_id' => $pointTransaction->id,
                'reference_id' => $referenceId,
                'injected_by' => auth()->id(),
            ]);

            // Dispatch event for push notification (optional)
            try {
                event(new PointEarned(
                    $member,
                    $pointTransaction,
                    $pointAmount,
                    'transaction',
                    [
                        'order_id' => $referenceId,
                        'outlet_name' => 'Manual Injection',
                        'is_manual' => true,
                    ]
                ));
            } catch (\Exception $e) {
                // Log error but don't fail the injection
                Log::warning('Failed to dispatch PointEarned event for manual injection', [
                    'member_id' => $member->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return redirect()->route('manual-point.index')
                ->with('success', "Point berhasil di-inject: {$pointAmount} points untuk {$member->nama_lengkap} (Total: {$member->just_points} points)");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error injecting manual points', [
                'member_id' => $request->member_id,
                'point_amount' => $request->point_amount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Gagal inject point: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Show detail of manual point injection
     */
    public function show($id)
    {
        $transaction = MemberAppsPointTransaction::with(['member', 'earning'])
            ->where('transaction_type', 'adjustment')
            ->where('channel', 'adjustment')
            ->findOrFail($id);

        return Inertia::render('ManualPoint/Show', [
            'transaction' => $transaction,
        ]);
    }
}

