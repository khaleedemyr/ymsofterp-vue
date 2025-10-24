<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\LeaveTransaction;
use App\Services\LeaveManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LeaveManagementController extends Controller
{
    protected $leaveService;

    public function __construct(LeaveManagementService $leaveService)
    {
        $this->leaveService = $leaveService;
    }

    /**
     * Index page untuk manajemen cuti
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $perPage = $request->input('per_page', 15);

        $query = User::where('users.status', 'A')
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->leftJoin('tbl_data_outlet', 'users.id_outlet', '=', 'tbl_data_outlet.id_outlet')
            ->select(
                'users.id',
                'users.nama_lengkap',
                'users.email',
                'users.cuti',
                'users.tanggal_masuk',
                'tbl_data_jabatan.nama_jabatan',
                'tbl_data_outlet.nama_outlet'
            );

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('users.nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('users.email', 'like', "%{$search}%")
                  ->orWhere('tbl_data_jabatan.nama_jabatan', 'like', "%{$search}%")
                  ->orWhere('tbl_data_outlet.nama_outlet', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate($perPage);

        return Inertia::render('LeaveManagement/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'per_page'])
        ]);
    }

    /**
     * Show leave history for specific user
     */
    public function showHistory($userId, Request $request)
    {
        try {
            $user = User::findOrFail($userId);
            $year = $request->input('year', date('Y'));

            $leaveHistory = $this->leaveService->getLeaveHistory($userId, $year);

            return response()->json([
                'success' => true,
                'data' => $leaveHistory,
                'user' => [
                    'id' => $user->id,
                    'nama_lengkap' => $user->nama_lengkap,
                    'email' => $user->email
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching leave history: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data history cuti',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manual adjustment cuti
     */
    public function manualAdjustment(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric',
            'description' => 'required|string|max:255'
        ]);

        $result = $this->leaveService->manualAdjustment(
            $request->user_id,
            $request->amount,
            $request->description,
            Auth::id()
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Saldo cuti berhasil disesuaikan',
                'new_balance' => $result['new_balance']
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => $result['error']
            ], 400);
        }
    }

    /**
     * Use leave
     */
    public function useLeave(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.5',
            'description' => 'required|string|max:255'
        ]);

        $result = $this->leaveService->useLeave(
            $request->user_id,
            $request->amount,
            $request->description,
            Auth::id()
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Cuti berhasil digunakan',
                'new_balance' => $result['new_balance']
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => $result['error']
            ], 400);
        }
    }

    /**
     * Process monthly leave credit manually
     */
    public function processMonthlyCredit(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2030',
            'month' => 'required|integer|min:1|max:12'
        ]);

        $result = $this->leaveService->giveMonthlyLeave(
            $request->year,
            $request->month
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil memberikan cuti bulanan untuk {$request->year}-{$request->month}",
                'processed_count' => $result['processed_count'],
                'errors' => $result['errors']
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => $result['error']
            ], 400);
        }
    }

    /**
     * Process burning previous year leave manually
     */
    public function processBurning(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2030'
        ]);

        $result = $this->leaveService->burnPreviousYearLeave($request->year);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil burning cuti tahun " . ($request->year - 1),
                'processed_count' => $result['processed_count'],
                'total_burned' => $result['total_burned'],
                'errors' => $result['errors']
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => $result['error']
            ], 400);
        }
    }

    /**
     * Get leave statistics
     */
    public function getStatistics()
    {
        $totalActiveUsers = User::where('status', 'A')->count();
        $totalLeaveBalance = User::where('status', 'A')->sum('cuti');
        $averageLeaveBalance = $totalActiveUsers > 0 ? $totalLeaveBalance / $totalActiveUsers : 0;

        // Get monthly credit statistics for current year
        $monthlyCredits = LeaveTransaction::where('transaction_type', 'monthly_credit')
            ->where('year', date('Y'))
            ->count();

        // Get burning statistics for current year
        $burningTransactions = LeaveTransaction::where('transaction_type', 'burning')
            ->where('year', date('Y'))
            ->sum('amount');

        return response()->json([
            'total_active_users' => $totalActiveUsers,
            'total_leave_balance' => $totalLeaveBalance,
            'average_leave_balance' => round($averageLeaveBalance, 2),
            'monthly_credits_this_year' => $monthlyCredits,
            'total_burned_this_year' => abs($burningTransactions)
        ]);
    }
}
