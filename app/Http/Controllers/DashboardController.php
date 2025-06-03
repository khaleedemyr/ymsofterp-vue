<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DashboardMaintenanceExport;

class DashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function index()
    {
        $data = $this->dashboardService->getDashboardData();
        return response()->json($data);
    }

    public function filter(Request $request)
    {
        $filters = $request->only(['outlet', 'startDate', 'endDate', 'category', 'priority', 'member']);
        $data = $this->dashboardService->getDashboardData($filters);
        return response()->json($data);
    }

    public function exportExcel()
    {
        $filename = 'maintenance_report_' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download(new DashboardMaintenanceExport, $filename);
    }

    public function taskDetail($id)
    {
        $data = $this->dashboardService->getDashboardRepository()->getTaskDetail($id);
        if (!$data) return response()->json(['message' => 'Task not found'], 404);
        return response()->json($data);
    }

    public function allTasks(Request $request)
    {
        $filters = $request->only(['page', 'perPage', 'search']);
        $data = $this->dashboardService->getDashboardRepository()->getLatestTasks($filters);
        return response()->json($data);
    }

    public function allDoneTasks(Request $request)
    {
        $filters = $request->only(['page', 'perPage', 'search']);
        $data = $this->dashboardService->getDashboardRepository()->getDoneTasks($filters);
        return response()->json($data);
    }

    public function doneTasksLeaderboard()
    {
        $data = $this->dashboardService->getDashboardRepository()->getDoneTasksLeaderboard();
        return response()->json($data);
    }

    public function polatestWithDetail(Request $request)
    {
        $filters = $request->only(['page', 'perPage', 'search']);
        $data = $this->dashboardService->getDashboardRepository()->getPOLatestWithDetail($filters);
        return response()->json($data);
    }

    public function allPRWithDetail(Request $request)
    {
        $filters = $request->only(['page', 'perPage', 'search']);
        $data = $this->dashboardService->getDashboardRepository()->getPRLatestWithDetail($filters);
        return response()->json($data);
    }

    public function allRetailWithDetail(Request $request)
    {
        $filters = $request->only(['page', 'perPage', 'search']);
        $data = $this->dashboardService->getDashboardRepository()->getRetailLatestWithDetail($filters);
        return response()->json($data);
    }

    public function allActivityWithDetail(Request $request)
    {
        $filters = $request->only(['page', 'perPage', 'search']);
        $data = $this->dashboardService->getDashboardRepository()->getActivityLatestWithDetail($filters);
        return response()->json($data);
    }

    public function allOverdueTasks(Request $request)
    {
        $filters = $request->only(['page', 'perPage', 'search']);
        $data = $this->dashboardService->getDashboardRepository()->getAllOverdueTasks($filters);
        return response()->json($data);
    }

    public function taskCompletionStats(Request $request)
    {
        $filters = $request->all();
        $data = $this->dashboardService->getDashboardRepository()->getTaskCompletionStats($filters);
        return response()->json($data);
    }

    public function taskByDueDateStats(Request $request)
    {
        $filters = $request->all();
        $data = $this->dashboardService->getDashboardRepository()->getTaskByDueDateStats($filters);
        return response()->json($data);
    }

    public function taskCountPerMember(Request $request)
    {
        $filters = $request->only(['startDate', 'endDate']);
        $data = $this->dashboardService->getDashboardRepository()->getTaskCountPerMember($filters);
        return response()->json($data);
    }
} 