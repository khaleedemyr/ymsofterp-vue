<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\HolidayAttendanceService;
use App\Models\HolidayAttendanceCompensation;
use App\Models\User;
use App\Exports\HolidayAttendanceExport;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class HolidayAttendanceController extends Controller
{
    protected $holidayAttendanceService;

    public function __construct(HolidayAttendanceService $holidayAttendanceService)
    {
        $this->holidayAttendanceService = $holidayAttendanceService;
    }

    /**
     * Display holiday attendance management page
     */
    public function index(Request $request)
    {
        $filters = [
            'start_date' => $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d')),
            'end_date' => $request->get('end_date', Carbon::now()->endOfMonth()->format('Y-m-d')),
            'compensation_type' => $request->get('compensation_type'),
            'status' => $request->get('status'),
            'user_id' => $request->get('user_id')
        ];

        $compensations = $this->holidayAttendanceService->getAllHolidayCompensations($filters);

        // Get users for filter dropdown
        $users = User::active()
            ->select('id', 'nama_lengkap', 'nik')
            ->orderBy('nama_lengkap')
            ->get();

        return Inertia::render('HolidayAttendance/Index', [
            'compensations' => $compensations,
            'users' => $users,
            'filters' => $filters
        ]);
    }

    /**
     * Process holiday attendance for a specific date
     */
    public function processHoliday(Request $request)
    {
        $request->validate([
            'date' => 'required|date'
        ]);

        $date = $request->input('date');
        $results = $this->holidayAttendanceService->processHolidayAttendance($date);

        return response()->json([
            'success' => true,
            'message' => 'Holiday attendance processed successfully',
            'results' => $results
        ]);
    }

    /**
     * Get employees who worked on a specific holiday
     */
    public function getHolidayWorkers(Request $request)
    {
        $request->validate([
            'date' => 'required|date'
        ]);

        $date = $request->input('date');
        
        if (!$this->holidayAttendanceService->isHoliday($date)) {
            return response()->json([
                'success' => false,
                'message' => 'The selected date is not a holiday'
            ]);
        }

        $employees = $this->holidayAttendanceService->getEmployeesWhoWorkedOnHoliday($date);

        return response()->json([
            'success' => true,
            'employees' => $employees
        ]);
    }

    /**
     * Get employee's holiday attendance history
     */
    public function getEmployeeHistory(Request $request, $userId)
    {
        $limit = $request->get('limit', 10);
        $history = $this->holidayAttendanceService->getEmployeeHolidayHistory($userId, $limit);

        return response()->json([
            'success' => true,
            'history' => $history
        ]);
    }

    /**
     * Use extra off day
     */
    public function useExtraOffDay(Request $request)
    {
        $request->validate([
            'compensation_id' => 'required|exists:holiday_attendance_compensations,id',
            'use_date' => 'required|date'
        ]);

        $userId = auth()->id();
        $compensationId = $request->input('compensation_id');
        $useDate = $request->input('use_date');

        try {
            $this->holidayAttendanceService->useExtraOffDay($userId, $compensationId, $useDate);

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
     * Use partial Public Holiday balance
     */
    public function usePartialPublicHolidayBalance(Request $request)
    {
        $request->validate([
            'compensation_id' => 'required|exists:holiday_attendance_compensations,id',
            'use_amount' => 'required|numeric|min:0.01',
            'use_date' => 'required|date'
        ]);

        $userId = auth()->id();
        $compensationId = $request->input('compensation_id');
        $useAmount = $request->input('use_amount');
        $useDate = $request->input('use_date');

        try {
            $this->holidayAttendanceService->usePartialPublicHolidayBalance($userId, $compensationId, $useAmount, $useDate);

            return response()->json([
                'success' => true,
                'message' => 'Public Holiday balance used successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Use Public Holiday balance with automatic record selection
     */
    public function usePublicHolidayBalanceAuto(Request $request)
    {
        $request->validate([
            'use_amount' => 'required|numeric|min:0.01',
            'use_date' => 'required|date',
            'strategy' => 'nullable|in:fifo,lifo'
        ]);

        $userId = auth()->id();
        $useAmount = $request->input('use_amount');
        $useDate = $request->input('use_date');
        $strategy = $request->input('strategy', 'fifo'); // Default to FIFO

        try {
            $result = $this->holidayAttendanceService->usePublicHolidayBalanceAuto($userId, $useAmount, $useDate, $strategy);

            return response()->json([
                'success' => true,
                'message' => 'Public Holiday balance used successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get user's available extra off days
     */
    public function getMyExtraOffDays(Request $request)
    {
        $userId = auth()->id();
        
        // Get both extra_off and bonus compensation types for Public Holiday balance
        $extraOffDays = HolidayAttendanceCompensation::where('user_id', $userId)
            ->whereIn('compensation_type', ['extra_off', 'bonus'])
            ->where('status', 'approved')
            ->with('holiday')
            ->orderBy('holiday_date', 'desc')
            ->get();

        // Calculate available balance for each record
        $processedDays = $extraOffDays->map(function ($day) {
            $availableAmount = $day->compensation_amount - ($day->used_amount ?? 0);
            
            return [
                ...$day->toArray(),
                'available_amount' => max(0, $availableAmount),
                'used_amount' => $day->used_amount ?? 0
            ];
        })->filter(function ($day) {
            // Only include records that have available balance
            return $day['available_amount'] > 0;
        });

        return response()->json([
            'success' => true,
            'extra_off_days' => $processedDays
        ]);
    }

    /**
     * Get holiday attendance statistics
     */
    public function getStatistics(Request $request)
    {
        $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));

        $stats = [
            'total_compensations' => HolidayAttendanceCompensation::whereBetween('holiday_date', [$startDate, $endDate])->count(),
            'extra_off_given' => HolidayAttendanceCompensation::whereBetween('holiday_date', [$startDate, $endDate])
                ->where('compensation_type', 'extra_off')->count(),
            'bonus_paid' => HolidayAttendanceCompensation::whereBetween('holiday_date', [$startDate, $endDate])
                ->where('compensation_type', 'bonus')->count(),
            'total_bonus_amount' => HolidayAttendanceCompensation::whereBetween('holiday_date', [$startDate, $endDate])
                ->where('compensation_type', 'bonus')
                ->sum('compensation_amount'),
            'pending_extra_off' => HolidayAttendanceCompensation::whereBetween('holiday_date', [$startDate, $endDate])
                ->where('compensation_type', 'extra_off')
                ->where('status', 'pending')->count(),
            'used_extra_off' => HolidayAttendanceCompensation::whereBetween('holiday_date', [$startDate, $endDate])
                ->where('compensation_type', 'extra_off')
                ->where('status', 'used')->count()
        ];

        return response()->json([
            'success' => true,
            'statistics' => $stats
        ]);
    }

    /**
     * Export holiday attendance data
     */
    public function export(Request $request)
    {
        $filters = [
            'start_date' => $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d')),
            'end_date' => $request->get('end_date', Carbon::now()->endOfMonth()->format('Y-m-d')),
            'compensation_type' => $request->get('compensation_type'),
            'status' => $request->get('status'),
            'user_id' => $request->get('user_id')
        ];

        $compensations = $this->holidayAttendanceService->getAllHolidayCompensations($filters);

        // Create filename
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];
        $fileName = 'holiday_attendance_' . $startDate . '_to_' . $endDate . '.xlsx';

        // Generate CSV content
        $csvContent = "Date,Employee Name,NIK,Job Position,Level,Outlet,Division,Compensation Type,Amount,Status,Used Date,Notes\n";
        
        foreach ($compensations as $compensation) {
            $compensationTypeText = $compensation->compensation_type === 'extra_off' ? 'Extra Off Day' : 'Holiday Bonus';
            $statusText = ucfirst($compensation->status);
            $amount = $compensation->compensation_type === 'extra_off' ? '1 day' : 'Rp ' . number_format($compensation->compensation_amount, 0, ',', '.');
            
            $csvContent .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $compensation->holiday_date,
                '"' . $compensation->nama_lengkap . '"',
                $compensation->nik,
                '"' . $compensation->nama_jabatan . '"',
                '"' . $compensation->nama_level . '"',
                '"' . ($compensation->nama_outlet ?? '') . '"',
                '"' . ($compensation->nama_divisi ?? '') . '"',
                $compensationTypeText,
                '"' . $amount . '"',
                $statusText,
                $compensation->used_date ?? '',
                '"' . ($compensation->notes ?? '') . '"'
            );
        }
        
        // Change filename to CSV
        $fileName = str_replace('.xlsx', '.csv', $fileName);
        
        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
    }
}
