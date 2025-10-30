<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExtraOffReportController extends Controller
{
    /**
     * Display the extra off report page
     */
    public function index()
    {
        return inertia('ExtraOffReport/Index');
    }

    /**
     * Get users for dropdown
     */
    public function getUsers()
    {
        try {
            $users = DB::table('users')
                ->where('status', 'active')
                ->select('id', 'nama_lengkap', 'nik')
                ->orderBy('nama_lengkap')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $users
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving users: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get extra off and public holiday data for report
     */
    public function getReportData(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'user_id' => 'nullable|string',
            'page' => 'nullable|string',
            'per_page' => 'nullable|string'
        ]);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $userId = $request->input('user_id');
        
        // Parse page parameter more safely
        $pageInput = $request->input('page', '1');
        $page = is_numeric($pageInput) ? (int) $pageInput : 1;
        
        // Parse per_page parameter more safely
        $perPageInput = $request->input('per_page', '15');
        $perPage = is_numeric($perPageInput) ? (int) $perPageInput : 15;
        
        // Manual validation for page
        if ($page < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Page must be at least 1'
            ], 422);
        }
        
        // Manual validation for per_page
        if ($perPage < 1 || $perPage > 100) {
            return response()->json([
                'success' => false,
                'message' => 'Per page must be between 1 and 100'
            ], 422);
        }
        
        // Handle empty user_id
        if ($userId === '' || $userId === null) {
            $userId = null;
        } else {
            $userId = (int) $userId;
            // Validate user exists if user_id is provided
            if ($userId > 0 && !DB::table('users')->where('id', $userId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }
        }
        
        
        // Validate date range
        if ($startDate > $endDate) {
            return response()->json([
                'success' => false,
                'message' => 'Start date cannot be greater than end date'
            ], 422);
        }
        
        // Log the parameters for debugging
        Log::info('Extra Off Report Parameters:', [
            'raw_request' => $request->all(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'user_id' => $userId,
            'page' => $page,
            'per_page' => $perPage,
            'page_type' => gettype($request->input('page')),
            'per_page_type' => gettype($request->input('per_page'))
        ]);

        try {
            // Get extra off data with pagination
            $extraOffData = $this->getExtraOffData($startDate, $endDate, $userId, $page, $perPage);
            
            // Get public holiday data with pagination
            $publicHolidayData = $this->getPublicHolidayData($startDate, $endDate, $userId, $page, $perPage);
            
            // Get summary statistics
            $summary = $this->getSummaryData($startDate, $endDate, $userId);

            return response()->json([
                'success' => true,
                'data' => [
                    'extra_off' => $extraOffData,
                    'public_holiday' => $publicHolidayData,
                    'summary' => $summary
                ],
                'pagination' => $extraOffData['pagination']
            ]);

        } catch (\Exception $e) {
            Log::error('Extra Off Report Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving report data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get extra off data
     */
    private function getExtraOffData($startDate, $endDate, $userId = null, $page = 1, $perPage = 15)
    {
        $query = DB::table('extra_off_transactions as eot')
            ->join('users as u', 'eot.user_id', '=', 'u.id')
            ->leftJoin('users as approver', 'eot.approved_by', '=', 'approver.id')
            ->leftJoin('tbl_data_outlet as o', 'u.id_outlet', '=', 'o.id_outlet')
            ->whereBetween('eot.created_at', [$startDate, $endDate . ' 23:59:59'])
            ->where('eot.status', 'approved')
            ->select([
                'eot.id',
                'eot.user_id',
                'u.nama_lengkap as employee_name',
                'u.nik as employee_nik',
                'eot.transaction_type',
                'eot.amount',
                'eot.source_type',
                'eot.source_date',
                'eot.description',
                'eot.used_date',
                'eot.created_at',
                'approver.nama_lengkap as approver_name',
                'o.nama_outlet as outlet_name'
            ]);

        if ($userId) {
            $query->where('eot.user_id', $userId);
        }

        // Use Laravel pagination
        $transactions = $query->orderBy('eot.created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        // Get current balances for all users (no pagination needed)
        $balances = DB::table('extra_off_balance as eob')
            ->join('users as u', 'eob.user_id', '=', 'u.id')
            ->select([
                'eob.user_id',
                'u.nama_lengkap as employee_name',
                'u.nik as employee_nik',
                'eob.balance'
            ]);

        if ($userId) {
            $balances->where('eob.user_id', $userId);
        }

        $currentBalances = $balances->get();

        return [
            'transactions' => $transactions->items(),
            'current_balances' => $currentBalances,
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
                'has_more_pages' => $transactions->hasMorePages()
            ]
        ];
    }

    /**
     * Get public holiday data
     */
    private function getPublicHolidayData($startDate, $endDate, $userId = null, $page = 1, $perPage = 15)
    {
        $query = DB::table('holiday_attendance_compensations as hac')
            ->join('users as u', 'hac.user_id', '=', 'u.id')
            ->leftJoin('tbl_kalender_perusahaan as tkp', 'hac.holiday_date', '=', 'tkp.tgl_libur')
            ->leftJoin('tbl_data_outlet as o', 'u.id_outlet', '=', 'o.id_outlet')
            ->whereBetween('hac.holiday_date', [$startDate, $endDate])
            ->whereIn('hac.status', ['approved', 'used'])
            ->select([
                'hac.id',
                'hac.user_id',
                'u.nama_lengkap as employee_name',
                'u.nik as employee_nik',
                'hac.holiday_date',
                'hac.compensation_type',
                'hac.compensation_amount',
                'hac.compensation_description',
                'hac.status',
                'hac.used_date',
                'hac.created_at',
                'tkp.keterangan as holiday_name',
                'o.nama_outlet as outlet_name'
            ]);

        if ($userId) {
            $query->where('hac.user_id', $userId);
        }

        // Use Laravel pagination
        $compensations = $query->orderBy('hac.holiday_date', 'desc')->paginate($perPage, ['*'], 'page', $page);

        return [
            'compensations' => $compensations->items(),
            'pagination' => [
                'current_page' => $compensations->currentPage(),
                'last_page' => $compensations->lastPage(),
                'per_page' => $compensations->perPage(),
                'total' => $compensations->total(),
                'from' => $compensations->firstItem(),
                'to' => $compensations->lastItem(),
                'has_more_pages' => $compensations->hasMorePages()
            ]
        ];
    }

    /**
     * Get summary statistics
     */
    private function getSummaryData($startDate, $endDate, $userId = null)
    {
        // Extra off earned count
        $extraOffEarnedQuery = DB::table('extra_off_transactions')
            ->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
            ->where('status', 'approved')
            ->where('transaction_type', 'earned');

        if ($userId) {
            $extraOffEarnedQuery->where('user_id', $userId);
        }

        $extraOffEarned = $extraOffEarnedQuery->count();

        // Extra off used count
        $extraOffUsedQuery = DB::table('extra_off_transactions')
            ->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
            ->where('status', 'approved')
            ->where('transaction_type', 'used');

        if ($userId) {
            $extraOffUsedQuery->where('user_id', $userId);
        }

        $extraOffUsed = $extraOffUsedQuery->count();

        // Public holiday count
        $publicHolidayQuery = DB::table('holiday_attendance_compensations')
            ->whereBetween('holiday_date', [$startDate, $endDate])
            ->whereIn('status', ['approved', 'used']);

        if ($userId) {
            $publicHolidayQuery->where('user_id', $userId);
        }

        $publicHolidayCount = $publicHolidayQuery->count();

        return [
            'extra_off_earned' => $extraOffEarned,
            'extra_off_used' => $extraOffUsed,
            'public_holiday_count' => $publicHolidayCount
        ];
    }

    /**
     * Delete single extra off transaction
     */
    public function deleteExtraOff(Request $request, $id)
    {
        try {
            $transaction = DB::table('extra_off_transactions')->where('id', $id)->first();
            
            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found'
                ], 404);
            }

            // If it's an 'earned' transaction, update the balance
            if ($transaction->transaction_type === 'earned') {
                DB::table('extra_off_balance')
                    ->where('user_id', $transaction->user_id)
                    ->decrement('balance', $transaction->amount);
            }

            // Delete the transaction
            DB::table('extra_off_transactions')->where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Extra off transaction deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Delete Extra Off Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting extra off transaction: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete single public holiday compensation
     */
    public function deletePublicHoliday(Request $request, $id)
    {
        try {
            $compensation = DB::table('holiday_attendance_compensations')->where('id', $id)->first();
            
            if (!$compensation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Compensation not found'
                ], 404);
            }

            // Delete the compensation
            DB::table('holiday_attendance_compensations')->where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Public holiday compensation deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Delete Public Holiday Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting public holiday compensation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete multiple extra off transactions
     */
    public function multipleDeleteExtraOff(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:extra_off_transactions,id'
        ]);

        try {
            $ids = $request->input('ids');
            
            // Get transactions to update balances
            $earnedTransactions = DB::table('extra_off_transactions')
                ->whereIn('id', $ids)
                ->where('transaction_type', 'earned')
                ->get();

            // Update balances for earned transactions
            foreach ($earnedTransactions as $transaction) {
                DB::table('extra_off_balance')
                    ->where('user_id', $transaction->user_id)
                    ->decrement('balance', $transaction->amount);
            }

            // Delete all transactions
            DB::table('extra_off_transactions')->whereIn('id', $ids)->delete();

            return response()->json([
                'success' => true,
                'message' => count($ids) . ' extra off transactions deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Multiple Delete Extra Off Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting extra off transactions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete multiple public holiday compensations
     */
    public function multipleDeletePublicHoliday(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:holiday_attendance_compensations,id'
        ]);

        try {
            $ids = $request->input('ids');
            
            // Delete all compensations
            DB::table('holiday_attendance_compensations')->whereIn('id', $ids)->delete();

            return response()->json([
                'success' => true,
                'message' => count($ids) . ' public holiday compensations deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Multiple Delete Public Holiday Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting public holiday compensations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export report to Excel
     */
    public function export(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'user_id' => 'nullable|string'
        ]);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $userId = $request->input('user_id');
        
        // Handle empty user_id
        if ($userId === '' || $userId === null) {
            $userId = null;
        } else {
            $userId = (int) $userId;
        }

        try {
            // Get all data without pagination for export
            $extraOffData = $this->getExtraOffData($startDate, $endDate, $userId, 1, 10000);
            $publicHolidayData = $this->getPublicHolidayData($startDate, $endDate, $userId, 1, 10000);
            $summary = $this->getSummaryData($startDate, $endDate, $userId);

            // Create Excel file
            $filename = 'extra_off_ph_report_' . date('Y-m-d_H-i-s') . '.xlsx';
            
            // For now, return JSON data that can be converted to Excel on frontend
            return response()->json([
                'success' => true,
                'data' => [
                    'extra_off' => $extraOffData,
                    'public_holiday' => $publicHolidayData,
                    'summary' => $summary
                ],
                'filename' => $filename
            ]);

        } catch (\Exception $e) {
            Log::error('Export Extra Off Report Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error exporting report: ' . $e->getMessage()
            ], 500);
        }
    }
}