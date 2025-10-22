<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ExtraOffService;
use App\Models\ExtraOffBalance;
use App\Models\ExtraOffTransaction;
use Carbon\Carbon;

class ExtraOffController extends Controller
{
    protected $extraOffService;

    public function __construct(ExtraOffService $extraOffService)
    {
        $this->extraOffService = $extraOffService;
    }

    /**
     * Get user's extra off balance
     */
    public function getBalance(Request $request)
    {
        $userId = auth()->id();
        $balance = $this->extraOffService->getUserBalance($userId);

        return response()->json([
            'success' => true,
            'balance' => $balance ? $balance->balance : 0,
            'balance_text' => $balance ? $balance->balance_text : '0 hari'
        ]);
    }

    /**
     * Get user's extra off transactions
     */
    public function getTransactions(Request $request)
    {
        $userId = auth()->id();
        $limit = $request->get('limit', 10);
        $transactions = $this->extraOffService->getUserTransactions($userId, $limit);

        return response()->json([
            'success' => true,
            'transactions' => $transactions
        ]);
    }

    /**
     * Use extra off day
     */
    public function useExtraOff(Request $request)
    {
        $request->validate([
            'use_date' => 'required|date',
            'reason' => 'nullable|string|max:255'
        ]);

        $userId = auth()->id();
        $useDate = $request->input('use_date');
        $reason = $request->input('reason');

        try {
            $this->extraOffService->useExtraOffDay($userId, $useDate, $reason);

            return response()->json([
                'success' => true,
                'message' => 'Extra off day used successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update existing transaction descriptions to include work time details
     */
    public function updateTransactionDescriptions(Request $request)
    {
        try {
            $results = $this->extraOffService->updateExistingTransactionDescriptions();

            return response()->json([
                'success' => true,
                'message' => 'Transaction descriptions updated successfully',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Manual adjustment of balance (Admin only)
     */
    public function adjustBalance(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|integer|not_in:0',
            'reason' => 'required|string|max:255'
        ]);

        $userId = $request->input('user_id');
        $amount = $request->input('amount');
        $reason = $request->input('reason');
        $approvedBy = auth()->id();

        try {
            $this->extraOffService->adjustBalance($userId, $amount, $reason, $approvedBy);

            return response()->json([
                'success' => true,
                'message' => 'Balance adjusted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Detect unscheduled work (Admin only)
     */
    public function detectUnscheduledWork(Request $request)
    {
        $date = $request->get('date', Carbon::yesterday()->format('Y-m-d'));

        try {
            $results = $this->extraOffService->detectUnscheduledWork($date);

            return response()->json([
                'success' => true,
                'results' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detect extra off for specific date (Admin only) - POST endpoint
     */
    public function detect(Request $request)
    {
        $request->validate([
            'date' => 'required|date'
        ]);

        $date = $request->input('date');

        try {
            $results = $this->extraOffService->detectUnscheduledWork($date);

            return response()->json([
                'success' => true,
                'results' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get extra off statistics (Admin only)
     */
    public function getStatistics(Request $request)
    {
        try {
            $statistics = $this->extraOffService->getStatistics();

            return response()->json([
                'success' => true,
                'statistics' => $statistics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Initialize balances for all users (Admin only)
     */
    public function initializeBalances(Request $request)
    {
        try {
            $initialized = $this->extraOffService->initializeBalances();

            return response()->json([
                'success' => true,
                'message' => "Initialized balances for {$initialized} users"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all balances (Admin only)
     */
    public function getAllBalances(Request $request)
    {
        $query = ExtraOffBalance::with('user');

        // Filter by user name if provided
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        // Filter by balance range
        if ($request->has('min_balance')) {
            $query->where('balance', '>=', $request->get('min_balance'));
        }

        if ($request->has('max_balance')) {
            $query->where('balance', '<=', $request->get('max_balance'));
        }

        $balances = $query->orderBy('balance', 'desc')
                         ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'balances' => $balances
        ]);
    }

    /**
     * Get all transactions (Admin only)
     */
    public function getAllTransactions(Request $request)
    {
        $query = ExtraOffTransaction::with(['user', 'approver']);

        // Filter by user if provided
        if ($request->has('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        // Filter by transaction type
        if ($request->has('transaction_type')) {
            $query->where('transaction_type', $request->get('transaction_type'));
        }

        // Filter by source type
        if ($request->has('source_type')) {
            $query->where('source_type', $request->get('source_type'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->get('date_to'));
        }

        $transactions = $query->orderBy('created_at', 'desc')
                             ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'transactions' => $transactions
        ]);
    }

    /**
     * Get all transactions without pagination (Admin only)
     */
    public function getAllTransactionsSimple(Request $request)
    {
        $query = ExtraOffTransaction::with(['user:id,nama_lengkap,nik']);

        // Filter by user if provided
        if ($request->has('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        // Filter by transaction type
        if ($request->has('transaction_type')) {
            $query->where('transaction_type', $request->get('transaction_type'));
        }

        // Filter by source type
        if ($request->has('source_type')) {
            $query->where('source_type', $request->get('source_type'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->get('date_to'));
        }

        $transactions = $query->orderBy('created_at', 'desc')
                             ->limit(100) // Limit to 100 most recent
                             ->get();

        // Ensure user data is properly loaded
        $transactions->load('user');

        return response()->json([
            'success' => true,
            'transactions' => $transactions
        ]);
    }
}
