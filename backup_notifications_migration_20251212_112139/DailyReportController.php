<?php

namespace App\Http\Controllers;

use App\Models\DailyReport;
use App\Models\DailyReportArea;
use App\Models\DailyReportProgress;
use App\Models\DailyReportBriefing;
use App\Models\DailyReportProductivity;
use App\Models\DailyReportVisitTable;
use App\Models\DailyReportSummary;
use App\Models\Outlet;
use App\Models\Departemen;
use App\Models\Area;
use App\Models\Divisi;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DailyReportController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $creator = $request->get('creator', '');
        $status = $request->get('status', 'all');
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');
        $perPage = $request->get('per_page', 15);

            $query = DailyReport::with(['outlet', 'department', 'user.jabatan', 'reportAreas', 'comments.user.jabatan']);

        if ($search) {
            $query->whereHas('outlet', function($q) use ($search) {
                $q->where('nama_outlet', 'like', "%{$search}%");
            })->orWhereHas('department', function($q) use ($search) {
                $q->where('nama_departemen', 'like', "%{$search}%");
            });
        }

        if ($creator) {
            $query->whereHas('user', function($q) use ($creator) {
                $q->where('nama_lengkap', 'like', "%{$creator}%");
            });
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $reports = $query->orderBy('created_at', 'desc')->paginate($perPage)->withQueryString();

        // Get statistics
        $statistics = [
            'total' => DailyReport::count(),
            'draft' => DailyReport::draft()->count(),
            'completed' => DailyReport::completed()->count(),
        ];

        // Get current user permissions
        $currentUser = auth()->user();
        $canEdit = $currentUser && (
            $currentUser->id_role === '5af56935b011a' && $currentUser->status === 'A'
        );

        return Inertia::render('DailyReport/Index', [
            'data' => $reports,
            'filters' => [
                'search' => $search,
                'creator' => $creator,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'per_page' => $perPage,
            ],
            'statistics' => $statistics,
            'permissions' => [
                'can_edit' => $canEdit,
                'current_user_id' => $currentUser->id ?? null,
            ],
        ]);
    }

    public function create()
    {
        $outlets = Outlet::active()->orderBy('nama_outlet')->get();
        $departments = Departemen::active()->orderBy('nama_departemen')->get();

        return Inertia::render('DailyReport/Create', [
            'outlets' => $outlets,
            'departments' => $departments,
        ]);
    }

    public function getAreas(Request $request)
    {
        $departmentId = $request->get('department_id');
        
        if (!$departmentId) {
            return response()->json([
                'success' => false,
                'message' => 'Department ID diperlukan'
            ], 400);
        }

        $areas = Area::where('departemen_id', $departmentId)
            ->where('status', 'A')
            ->orderBy('nama_area')
            ->get();

        return response()->json([
            'success' => true,
            'areas' => $areas
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'inspection_time' => 'required|in:lunch,dinner',
            'department_id' => 'required|exists:departemens,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Create daily report
            $dailyReport = DailyReport::create([
                'outlet_id' => $request->outlet_id,
                'inspection_time' => $request->inspection_time,
                'department_id' => $request->department_id,
                'user_id' => auth()->id(),
                'status' => 'draft'
            ]);

            // Get all areas for the selected department and create progress entries
            $areas = Area::where('departemen_id', $request->department_id)
                ->where('status', 'A')
                ->orderBy('nama_area')
                ->get();

            foreach ($areas as $area) {
                DailyReportProgress::create([
                    'daily_report_id' => $dailyReport->id,
                    'area_id' => $area->id,
                    'status' => 'pending',
                    'form_data' => [],
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Daily report berhasil dibuat. Silakan mulai inspeksi.',
                'report_id' => $dailyReport->id
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat daily report: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $dailyReport = DailyReport::with([
            'outlet', 
            'department', 
            'user.jabatan',
            'progress.area',
            'reportAreas.area',
            'reportAreas.deptConcern',
            'briefing',
            'productivity',
            'visitTables',
            'summaries'
        ])->findOrFail($id);

        // Get inspection statistics
        $inspectionStats = $dailyReport->getInspectionStats();

        // Get current user permissions
        $currentUser = auth()->user();
        $canEdit = $currentUser && (
            $currentUser->id_role === '5af56935b011a' && $currentUser->status === 'A'
        );

        return Inertia::render('DailyReport/Show', [
            'report' => $dailyReport,
            'inspectionStats' => $inspectionStats,
            'permissions' => [
                'can_edit' => $canEdit,
                'current_user_id' => $currentUser->id ?? null,
            ],
        ]);
    }

    public function inspect($id)
    {
        $dailyReport = DailyReport::with([
            'outlet', 
            'department', 
            'progress.area',
            'reportAreas.area',
            'reportAreas.deptConcern'
        ])->findOrFail($id);

        // Check permissions
        $currentUser = auth()->user();
        $canEdit = $currentUser && (
            $currentUser->id_role === '5af56935b011a' && $currentUser->status === 'A'
        );

        if (!$canEdit && $dailyReport->user_id !== $currentUser->id) {
            abort(403, 'Unauthorized access to this report.');
        }

        $areas = Area::where('departemen_id', $dailyReport->department_id)
            ->where('status', 'A')
            ->orderBy('nama_area')
            ->get();

        $divisions = Divisi::active()->orderBy('nama_divisi')->get();
        
        // Add ticket categories and priorities for convert to ticket feature
        $categories = \App\Models\TicketCategory::active()->orderBy('name')->get();
        $priorities = \App\Models\TicketPriority::active()->orderBy('level', 'desc')->get();

        return Inertia::render('DailyReport/Inspect', [
            'dailyReport' => $dailyReport,
            'areas' => $areas,
            'divisions' => $divisions,
            'categories' => $categories,
            'priorities' => $priorities,
        ]);
    }

    public function autoSave(Request $request, $id)
    {
        $dailyReport = DailyReport::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'area_id' => 'required|exists:areas,id',
            'status' => 'nullable|in:G,NG,NA',
            'finding_problem' => 'nullable|string',
            'dept_concern_id' => 'nullable|exists:tbl_data_divisi,id',
            'documentation' => 'nullable|array',
            'documentation.*' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Update or create progress
            $progress = DailyReportProgress::updateOrCreate(
                [
                    'daily_report_id' => $dailyReport->id,
                    'area_id' => $request->area_id
                ],
                [
                    'progress_status' => 'in_progress',
                    'form_data' => $request->only(['status', 'finding_problem', 'dept_concern_id', 'documentation'])
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil disimpan',
                'data' => $progress
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function saveArea(Request $request, $id)
    {
        $dailyReport = DailyReport::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'area_id' => 'required|exists:areas,id',
            'status' => 'required|in:G,NG,NA',
            'finding_problem' => 'nullable|string',
            'dept_concern_id' => 'nullable|exists:tbl_data_divisi,id',
            'documentation' => 'nullable|array',
            'documentation.*' => 'string',
            'create_ticket' => 'nullable|boolean',
            'ticket_data' => 'nullable|array',
            'ticket_data.category_id' => 'required_if:create_ticket,true|exists:ticket_categories,id',
            'ticket_data.priority_id' => 'required_if:create_ticket,true|exists:ticket_priorities,id',
            'ticket_data.due_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Update progress
            $progress = DailyReportProgress::where('daily_report_id', $dailyReport->id)
                ->where('area_id', $request->area_id)
                ->first();

            if ($progress) {
                $progress->markAsCompleted($request->only(['status', 'finding_problem', 'dept_concern_id', 'documentation']));
            }

            // Update or create report area
            DailyReportArea::updateOrCreate(
                [
                    'daily_report_id' => $dailyReport->id,
                    'area_id' => $request->area_id
                ],
                [
                    'status' => $request->status,
                    'finding_problem' => $request->finding_problem,
                    'dept_concern_id' => $request->dept_concern_id,
                    'documentation' => $request->documentation
                ]
            );

            $ticketCreated = false;
            
            // Create ticket if requested
            if ($request->create_ticket && $request->ticket_data) {
                $ticketCreated = $this->createTicketFromArea($dailyReport, $request->area_id, $request->ticket_data);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Area berhasil disimpan',
                'ticket_created' => $ticketCreated
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan area: ' . $e->getMessage()
            ], 500);
        }
    }

    public function skipArea(Request $request, $id)
    {
        $dailyReport = DailyReport::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'area_id' => 'required|exists:areas,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $progress = DailyReportProgress::where('daily_report_id', $dailyReport->id)
                ->where('area_id', $request->area_id)
                ->first();

            if ($progress) {
                $progress->markAsSkipped();
            }

            return response()->json([
                'success' => true,
                'message' => 'Area berhasil di-skip'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal skip area: ' . $e->getMessage()
            ], 500);
        }
    }

    public function completeReport($id)
    {
        $dailyReport = DailyReport::findOrFail($id);

        try {
            // Don't mark as completed yet, just redirect to post-inspection
            return response()->json([
                'success' => true,
                'message' => 'Inspection selesai. Silakan isi form post-inspection.',
                'redirect_to' => route('daily-report.post-inspection', $id)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyelesaikan report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manually mark report as completed (bypass post-inspection requirements)
     */
    public function forceCompleteReport($id)
    {
        $dailyReport = DailyReport::findOrFail($id);

        // Check permissions
        $currentUser = auth()->user();
        $canEdit = $currentUser && (
            $currentUser->id_role === '5af56935b011a' && $currentUser->status === 'A'
        );

        if (!$canEdit && $dailyReport->user_id !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to complete this report.'
            ], 403);
        }

        try {
            $dailyReport->update(['status' => 'completed']);

            \Log::info('Daily report manually marked as completed', [
                'report_id' => $dailyReport->id,
                'user_id' => $currentUser->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Report berhasil diselesaikan'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyelesaikan report: ' . $e->getMessage()
            ], 500);
        }
    }

    public function uploadDocumentation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|image|mimes:png,jpg,jpeg|max:5120' // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi file gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('daily_reports/documentation', $filename, 'public');

            return response()->json([
                'success' => true,
                'message' => 'File berhasil diupload',
                'data' => [
                    'filename' => $filename,
                    'path' => $path,
                    'url' => Storage::url($path)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal upload file: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $dailyReport = DailyReport::findOrFail($id);

        // Check permissions
        $currentUser = auth()->user();
        $canEdit = $currentUser && (
            $currentUser->id_role === '5af56935b011a' && $currentUser->status === 'A'
        );

        if (!$canEdit && $dailyReport->user_id !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to delete this report.'
            ], 403);
        }

        try {
            $dailyReport->delete();

            return response()->json([
                'success' => true,
                'message' => 'Daily report berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus report: ' . $e->getMessage()
            ], 500);
        }
    }

    public function postInspection($id)
    {
        $dailyReport = DailyReport::with([
            'outlet', 
            'department', 
            'briefing',
            'productivity',
            'visitTables',
            'summaries'
        ])->findOrFail($id);

        // Check permissions
        $currentUser = auth()->user();
        $canEdit = $currentUser && (
            $currentUser->id_role === '5af56935b011a' && $currentUser->status === 'A'
        );

        if (!$canEdit && $dailyReport->user_id !== $currentUser->id) {
            abort(403, 'Unauthorized access to this report.');
        }

        // Get users from the same outlet with active status
        $users = \App\Models\User::where('id_outlet', $dailyReport->outlet_id)
            ->where('status', 'A')
            ->with('jabatan')
            ->select('id', 'nama_lengkap', 'id_outlet', 'id_jabatan')
            ->orderBy('nama_lengkap')
            ->get();

        return Inertia::render('DailyReport/PostInspection', [
            'dailyReport' => $dailyReport,
            'users' => $users,
        ]);
    }

    public function saveBriefing(Request $request, $id)
    {
        $dailyReport = DailyReport::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'briefing_type' => 'required|in:morning,afternoon',
            'time_of_conduct' => 'nullable|date_format:H:i',
            'participant' => 'nullable|string',
            'outlet' => 'nullable|string',
            'service_in_charge' => 'nullable|string',
            'bar_in_charge' => 'nullable|string',
            'kitchen_in_charge' => 'nullable|string',
            'so_product' => 'nullable|string',
            'product_up_selling' => 'nullable|string',
            'commodity_issue' => 'nullable|string',
            'oe_issue' => 'nullable|string',
            'guest_reservation_pax' => 'nullable|integer',
            'daily_revenue_target' => 'nullable|numeric',
            'promotion_program_campaign' => 'nullable|string',
            'guest_comment_target' => 'nullable|string',
            'trip_advisor_target' => 'nullable|string',
            'other_preparation' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DailyReportBriefing::updateOrCreate(
                ['daily_report_id' => $dailyReport->id],
                $request->all()
            );

            return response()->json([
                'success' => true,
                'message' => 'Briefing berhasil disimpan'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan briefing: ' . $e->getMessage()
            ], 500);
        }
    }

    public function saveProductivity(Request $request, $id)
    {
        $dailyReport = DailyReport::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'product_knowledge_test' => 'nullable|string',
            'sos_hospitality_role_play' => 'nullable|string',
            'employee_daily_coaching' => 'nullable|string',
            'others_activity' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DailyReportProductivity::updateOrCreate(
                ['daily_report_id' => $dailyReport->id],
                $request->all()
            );

            return response()->json([
                'success' => true,
                'message' => 'Productivity program berhasil disimpan'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan productivity program: ' . $e->getMessage()
            ], 500);
        }
    }

    public function saveVisitTable(Request $request, $id)
    {
        $dailyReport = DailyReport::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'guest_name' => 'nullable|string',
            'table_no' => 'nullable|string',
            'no_of_pax' => 'nullable|integer',
            'guest_experience' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DailyReportVisitTable::create([
                'daily_report_id' => $dailyReport->id,
                'guest_name' => $request->guest_name,
                'table_no' => $request->table_no,
                'no_of_pax' => $request->no_of_pax,
                'guest_experience' => $request->guest_experience,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Visit table berhasil disimpan'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan visit table: ' . $e->getMessage()
            ], 500);
        }
    }

    public function saveSummary(Request $request, $id)
    {
        $dailyReport = DailyReport::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'summary_type' => 'required|in:summary_1,summary_2',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DailyReportSummary::updateOrCreate(
                [
                    'daily_report_id' => $dailyReport->id,
                    'summary_type' => $request->summary_type
                ],
                $request->all()
            );

            // Check if this is the final summary and mark report as completed
            $this->checkAndCompleteReport($dailyReport);

            return response()->json([
                'success' => true,
                'message' => 'Summary berhasil disimpan'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan summary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if report should be marked as completed based on post-inspection completion
     */
    private function checkAndCompleteReport($dailyReport)
    {
        // Check if all required post-inspection data is completed
        $hasBriefing = $dailyReport->briefing()->exists();
        $hasProductivity = $dailyReport->productivity()->exists();
        $hasVisitTables = $dailyReport->visitTables()->count() >= 5; // Minimum 5 tables
        $hasSummary = $dailyReport->summaries()->exists();

        // For Kitchen Lunch: need briefing, productivity, visit tables, and summary
        // For Kitchen Dinner: need briefing, visit tables, and summary (no productivity)
        // For Service Lunch: need briefing, productivity, visit tables, and summary
        // For Service Dinner: need briefing, visit tables, and summary (no productivity)

        $isCompleted = false;

        if ($dailyReport->department_id == 1) { // Kitchen
            if ($dailyReport->inspection_time == 'lunch') {
                $isCompleted = $hasBriefing && $hasProductivity && $hasVisitTables && $hasSummary;
            } else { // dinner
                $isCompleted = $hasBriefing && $hasVisitTables && $hasSummary;
            }
        } else if ($dailyReport->department_id == 2) { // Service
            if ($dailyReport->inspection_time == 'lunch') {
                $isCompleted = $hasBriefing && $hasProductivity && $hasVisitTables && $hasSummary;
            } else { // dinner
                $isCompleted = $hasBriefing && $hasVisitTables && $hasSummary;
            }
        }

        if ($isCompleted && $dailyReport->status !== 'completed') {
            $dailyReport->update(['status' => 'completed']);
            
            \Log::info('Daily report marked as completed', [
                'report_id' => $dailyReport->id,
                'department_id' => $dailyReport->department_id,
                'inspection_time' => $dailyReport->inspection_time
            ]);
        }
    }

    /**
     * Create ticket from daily report area concern
     */
    public function createTicketFromConcern(Request $request, $id)
    {
        $dailyReport = DailyReport::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'area_id' => 'required|exists:areas,id',
            'finding_problem' => 'required|string',
            'divisi_concern_id' => 'required|exists:tbl_data_divisi,id',
            'category_id' => 'required|exists:ticket_categories,id',
            'priority_id' => 'required|exists:ticket_priorities,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Get area and divisi concern data
            $area = Area::findOrFail($request->area_id);
            $divisiConcern = Divisi::findOrFail($request->divisi_concern_id);

            // Get default status (Open) or first available status
            $defaultStatus = TicketStatus::where('slug', 'open')->first();
            if (!$defaultStatus) {
                // Fallback: get first active status
                $defaultStatus = TicketStatus::active()->first();
                if (!$defaultStatus) {
                    // Last resort: get any status
                    $defaultStatus = TicketStatus::first();
                    if (!$defaultStatus) {
                        throw new \Exception('No ticket status found. Please run database migrations and seed data.');
                    }
                }
            }

            // Get priority to calculate due date
            $priority = TicketPriority::findOrFail($request->priority_id);
            $dueDate = now()->addDays($priority->max_days ?? 7);

            // Create ticket title
            $title = "Daily Report Issue - {$area->nama_area}";
            
            // Create ticket description
            $description = "Issue found during daily report inspection:\n\n";
            $description .= "Outlet: {$dailyReport->outlet->nama_outlet}\n";
            $description .= "Department: {$dailyReport->department->nama_departemen}\n";
            $description .= "Area: {$area->nama_area}\n";
            $description .= "Divisi Concern: {$divisiConcern->nama_divisi}\n\n";
            $description .= "Finding Problem:\n{$request->finding_problem}";

            $ticket = Ticket::create([
                'ticket_number' => Ticket::generateTicketNumber(),
                'title' => $title,
                'description' => $description,
                'category_id' => $request->category_id,
                'priority_id' => $request->priority_id,
                'status_id' => $defaultStatus->id,
                'divisi_id' => $divisiConcern->id, // Divisi concern
                'outlet_id' => $dailyReport->outlet_id,
                'created_by' => auth()->id(),
                'due_date' => $dueDate,
                'source' => 'daily_report',
                'source_id' => $dailyReport->id,
            ]);

            // Create ticket history
            \App\Models\TicketHistory::create([
                'ticket_id' => $ticket->id,
                'user_id' => auth()->id(),
                'action' => 'created',
                'field_name' => null,
                'old_value' => null,
                'new_value' => null,
                'description' => "Ticket created from Daily Report #{$dailyReport->id}",
            ]);

            // Send notifications to users in the selected division
            $this->sendTicketCreatedNotifications($ticket);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket berhasil dibuat dari Daily Report',
                'data' => $ticket->load(['category', 'priority', 'status', 'divisi', 'outlet', 'creator'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat ticket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get ticket categories and priorities for dropdown
     */
    public function getTicketOptions()
    {
        $categories = TicketCategory::active()->orderBy('name')->get();
        $priorities = TicketPriority::active()->orderBy('level', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories,
                'priorities' => $priorities,
            ]
        ]);
    }

    /**
     * Create ticket from area data
     */
    private function createTicketFromArea($dailyReport, $areaId, $ticketData)
    {
        try {
            $area = Area::findOrFail($areaId);
            $divisi = Divisi::findOrFail($ticketData['divisi_id']);

            // Get default status (Open)
            $defaultStatus = TicketStatus::where('slug', 'open')->first();
            if (!$defaultStatus) {
                $defaultStatus = TicketStatus::active()->first();
            }

            // Create ticket
            $ticket = Ticket::create([
                'ticket_number' => Ticket::generateTicketNumber(),
                'title' => $ticketData['title'],
                'description' => $ticketData['description'],
                'category_id' => $ticketData['category_id'],
                'priority_id' => $ticketData['priority_id'],
                'status_id' => $defaultStatus->id,
                'divisi_id' => $ticketData['divisi_id'],
                'outlet_id' => $dailyReport->outlet_id,
                'created_by' => auth()->id() ?? 1, // Use admin user if no auth user
                'due_date' => $ticketData['due_date'] ? \Carbon\Carbon::parse($ticketData['due_date']) : null,
                'source' => 'daily_report',
                'source_id' => $dailyReport->id,
            ]);

            // Create ticket history
            \App\Models\TicketHistory::create([
                'ticket_id' => $ticket->id,
                'user_id' => auth()->id() ?? 1, // Use admin user if no auth user
                'action' => 'created',
                'field_name' => null,
                'old_value' => null,
                'new_value' => null,
                'description' => "Ticket created from Daily Report #{$dailyReport->id} - Area: {$area->nama_area}",
            ]);

            // Note: Attachments are displayed directly from daily report area in ticket show page

            // Send notifications
            $this->sendTicketCreatedNotifications($ticket);

            \Log::info('Ticket created from daily report area', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'daily_report_id' => $dailyReport->id,
                'area_id' => $areaId,
                'area_name' => $area->nama_area
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to create ticket from daily report area', [
                'daily_report_id' => $dailyReport->id,
                'area_id' => $areaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }


    /**
     * Send ticket created notifications to division users
     */
    private function sendTicketCreatedNotifications($ticket)
    {
        try {
            $users = \App\Models\User::where('division_id', $ticket->divisi_id)
                ->where('status', 'A')
                ->get();

            $creator = auth()->user();
            $outlet = \App\Models\Outlet::find($ticket->outlet_id);
            $divisi = \App\Models\Divisi::find($ticket->divisi_id);

            foreach ($users as $user) {
                \DB::table('notifications')->insert([
                    'user_id' => $user->id,
                    'task_id' => $ticket->id,
                    'type' => 'ticket_created',
                    'message' => "Ticket baru telah dibuat dari Daily Report:\n\nNo: {$ticket->ticket_number}\nJudul: {$ticket->title}\nDivisi: {$divisi->nama_divisi}\nOutlet: {$outlet->nama_outlet}\nDibuat oleh: {$creator->nama_lengkap}",
                    'url' => config('app.url') . '/tickets/' . $ticket->id,
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            \Log::info('Ticket created notifications sent from daily report', ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'divisi_id' => $ticket->divisi_id, 'notified_users_count' => $users->count()]);
        } catch (\Exception $e) {
            \Log::error('Failed to send ticket created notifications from daily report', ['ticket_id' => $ticket->id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }
    }

    public function getSummaryRating(Request $request)
    {
        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');
        $region = $request->get('region');

        // Set default date range if not provided
        if (!$startDate || !$endDate) {
            $endDate = now()->format('Y-m-d');
            $startDate = now()->subDays(30)->format('Y-m-d');
        }

        try {
            $query = DB::table('daily_reports as dr')
                ->join('tbl_data_outlet as o', 'dr.outlet_id', '=', 'o.id_outlet')
                ->join('regions as r', 'o.region_id', '=', 'r.id')
                ->join('daily_report_areas as dra', 'dr.id', '=', 'dra.daily_report_id')
                ->whereBetween(DB::raw('DATE(dr.created_at)'), [$startDate, $endDate])
                ->where('dr.status', 'completed');

            // Apply region filter if provided
            if ($region) {
                $query->where('r.id', $region);
            }

            $summaryData = $query->select([
                'o.id_outlet as id',
                'o.nama_outlet',
                'r.name as region_name',
                'r.id as region_id',
                DB::raw('COUNT(DISTINCT dr.id) as total_reports'),
                DB::raw('COUNT(DISTINCT CASE WHEN dr.status = "completed" THEN dr.id END) as completed_reports'),
                DB::raw('COUNT(DISTINCT CASE WHEN dr.status = "draft" THEN dr.id END) as draft_reports'),
                DB::raw('AVG(CASE WHEN dra.status = "G" THEN 100 WHEN dra.status = "NG" THEN 0 END) as average_rating')
            ])
            ->groupBy('o.id_outlet', 'o.nama_outlet', 'r.name', 'r.id')
            ->orderBy('average_rating', 'desc')
            ->get();

            // Format the data
            $formattedData = $summaryData->map(function ($item) {
                return [
                    'id' => $item->id,
                    'nama_outlet' => $item->nama_outlet,
                    'region' => $item->region_name,
                    'region_id' => $item->region_id,
                    'total_reports' => (int) $item->total_reports,
                    'completed_reports' => (int) $item->completed_reports,
                    'draft_reports' => (int) $item->draft_reports,
                    'average_rating' => round($item->average_rating ?? 0, 1)
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedData,
                'filters' => [
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'region' => $region
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting summary rating data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'filters' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data summary rating',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getRegions()
    {
        try {
            $regions = DB::table('regions')
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $regions
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting regions data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data regions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getDepartmentRatings(Request $request)
    {
        $outletId = $request->get('outletId');
        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');
        $region = $request->get('region');

        if (!$outletId) {
            return response()->json([
                'success' => false,
                'message' => 'Outlet ID diperlukan'
            ], 400);
        }

        // Set default date range if not provided
        if (!$startDate || !$endDate) {
            $endDate = now()->format('Y-m-d');
            $startDate = now()->subDays(30)->format('Y-m-d');
        }

        try {
            $query = DB::table('daily_reports as dr')
                ->join('tbl_data_outlet as o', 'dr.outlet_id', '=', 'o.id_outlet')
                ->join('regions as r', 'o.region_id', '=', 'r.id')
                ->join('departemens as d', 'dr.department_id', '=', 'd.id')
                ->join('daily_report_areas as dra', 'dr.id', '=', 'dra.daily_report_id')
                ->where('dr.outlet_id', $outletId)
                ->whereBetween(DB::raw('DATE(dr.created_at)'), [$startDate, $endDate])
                ->where('dr.status', 'completed');

            // Apply region filter if provided
            if ($region) {
                $query->where('r.id', $region);
            }

            $departmentData = $query->select([
                'd.id',
                'd.nama_departemen',
                DB::raw('COUNT(DISTINCT dr.id) as total_reports'),
                DB::raw('AVG(CASE WHEN dra.status = "G" THEN 100 WHEN dra.status = "NG" THEN 0 END) as average_rating')
            ])
            ->groupBy('d.id', 'd.nama_departemen')
            ->orderBy('average_rating', 'desc')
            ->get();

            // Format the data
            $formattedData = $departmentData->map(function ($item) {
                return [
                    'id' => $item->id,
                    'nama_departemen' => $item->nama_departemen,
                    'total_reports' => (int) $item->total_reports,
                    'average_rating' => round($item->average_rating ?? 0, 1)
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedData
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting department ratings data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'outletId' => $outletId,
                'filters' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data rating departemen',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
