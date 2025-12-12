<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketAttachment;
use App\Models\Departemen;
use App\Models\Divisi;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class TicketController extends Controller
{
    /**
     * Display a listing of tickets
     */
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $status = $request->get('status', 'all');
        $priority = $request->get('priority', 'all');
        $category = $request->get('category', 'all');
        $division = $request->get('division', 'all');
        $outlet = $request->get('outlet', 'all');
        $perPage = $request->get('per_page', 15);

        $query = Ticket::with([
            'category',
            'priority', 
            'status',
            'divisi',
            'outlet',
            'creator'
        ])->withCount('comments');

        // Apply filters
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status !== 'all') {
            $query->whereHas('status', function($q) use ($status) {
                $q->where('slug', $status);
            });
        }

        if ($priority !== 'all') {
            $query->where('priority_id', $priority);
        }

        if ($category !== 'all') {
            $query->where('category_id', $category);
        }

        if ($division !== 'all') {
            $query->where('divisi_id', $division);
        }

        if ($outlet !== 'all') {
            $query->where('outlet_id', $outlet);
        }

        $tickets = $query->orderBy('created_at', 'desc')
                        ->paginate($perPage)
                        ->withQueryString();

        // Get filter options
        $categories = TicketCategory::active()->orderBy('name')->get();
        $priorities = TicketPriority::active()->orderBy('level', 'desc')->get();
        $statuses = TicketStatus::active()->ordered()->get();
        
        // Get only divisions that have tickets
        $divisis = Divisi::whereHas('tickets')->active()->orderBy('nama_divisi')->get();
        
        $outlets = Outlet::active()->orderBy('nama_outlet')->get();

        // Statistics
        $statistics = [
            'total' => Ticket::count(),
            'open' => Ticket::open()->count(),
            'in_progress' => Ticket::inProgress()->count(),
            'resolved' => Ticket::resolved()->count(),
            'closed' => Ticket::closed()->count(),
        ];

        return Inertia::render('Tickets/Index', [
            'data' => $tickets,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'priority' => $priority,
                'category' => $category,
                'division' => $division,
                'outlet' => $outlet,
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'categories' => $categories,
                'priorities' => $priorities,
                'statuses' => $statuses,
                'divisions' => $divisis,
                'outlets' => $outlets,
            ],
            'statistics' => $statistics,
        ]);
    }

    /**
     * Show the form for creating a new ticket
     */
    public function create()
    {
        $categories = TicketCategory::active()->orderBy('name')->get();
        $priorities = TicketPriority::active()->orderBy('level', 'desc')->get();
        $divisis = Divisi::active()->orderBy('nama_divisi')->get();
        $outlets = Outlet::active()->orderBy('nama_outlet')->get();

        return Inertia::render('Tickets/Create', [
            'categories' => $categories,
            'priorities' => $priorities,
            'divisis' => $divisis,
            'outlets' => $outlets,
        ]);
    }

    /**
     * Store a newly created ticket
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:ticket_categories,id',
            'priority_id' => 'required|exists:ticket_priorities,id',
            'divisi_id' => 'required|exists:tbl_data_divisi,id',
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
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

            $ticket = Ticket::create([
                'ticket_number' => Ticket::generateTicketNumber(),
                'title' => $request->title,
                'description' => $request->description,
                'category_id' => $request->category_id,
                'priority_id' => $request->priority_id,
                'status_id' => $defaultStatus->id,
                'divisi_id' => $request->divisi_id,
                'outlet_id' => $request->outlet_id,
                'created_by' => auth()->id(),
                'due_date' => $dueDate,
                'source' => 'manual',
            ]);

            // Create ticket history
            $this->createTicketHistory($ticket, 'created', null, null, 'Ticket created');

            // Handle file uploads
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('ticket-attachments', 'public');
                    
                    TicketAttachment::create([
                        'ticket_id' => $ticket->id,
                        'uploaded_by' => auth()->id(),
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }

            // Send notifications to users in the selected division
            $this->sendTicketCreatedNotifications($ticket);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket berhasil dibuat',
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
     * Display the specified ticket
     */
    public function show($id)
    {
        $ticket = Ticket::with([
            'category',
            'priority',
            'status',
            'divisi',
            'outlet',
            'creator',
            'comments.user',
            'attachments',
            'history.user'
        ])->findOrFail($id);

        // If ticket source is daily_report, get attachments from daily report area
        if ($ticket->source === 'daily_report' && $ticket->source_id) {
            $dailyReport = \App\Models\DailyReport::find($ticket->source_id);
            if ($dailyReport) {
                // Get the area name from ticket title (format: "Area Name - Description...")
                $titleParts = explode(' - ', $ticket->title);
                $areaName = $titleParts[0] ?? '';
                
                // Find the area by name
                $area = \App\Models\Area::where('nama_area', $areaName)->first();
                
                if ($area) {
                    // Get daily report area with documentation
                    $reportArea = \App\Models\DailyReportArea::where('daily_report_id', $dailyReport->id)
                        ->where('area_id', $area->id)
                        ->first();
                    
                    if ($reportArea && $reportArea->documentation && !empty($reportArea->documentation)) {
                        // Create virtual attachments from daily report documentation
                        $dailyReportAttachments = [];
                        foreach ($reportArea->documentation as $index => $documentPath) {
                            $fileInfo = pathinfo($documentPath);
                            $fileName = $fileInfo['basename'];
                            
                            // Get file info
                            $cleanPath = ltrim($documentPath, '/storage/');
                            $fullPath = storage_path('app/public/' . $cleanPath);
                            
                            if (file_exists($fullPath)) {
                                $fileSize = filesize($fullPath);
                                $mimeType = mime_content_type($fullPath);
                                
                                $dailyReportAttachments[] = (object) [
                                    'id' => 'daily_report_' . $index,
                                    'ticket_id' => $ticket->id,
                                    'comment_id' => null,
                                    'file_name' => $fileName,
                                    'file_path' => $documentPath, // Use original path from daily report
                                    'file_size' => $fileSize,
                                    'mime_type' => $mimeType,
                                    'uploaded_by' => $ticket->created_by,
                                    'created_at' => $reportArea->created_at,
                                    'updated_at' => $reportArea->updated_at,
                                    'is_daily_report' => true, // Flag to identify daily report attachments
                                ];
                            }
                        }
                        
                        // Merge daily report attachments with regular attachments
                        $ticket->attachments = $ticket->attachments->concat(collect($dailyReportAttachments));
                    }
                }
            }
        }


        // Convert attachments collection to array for proper frontend serialization
        $ticketData = $ticket->toArray();
        $ticketData['attachments'] = $ticket->attachments->toArray();

        return Inertia::render('Tickets/Show', [
            'ticket' => $ticketData,
        ]);
    }

    /**
     * Show the form for editing the specified ticket
     */
    public function edit($id)
    {
        $ticket = Ticket::with([
            'category',
            'priority',
            'status',
            'divisi',
            'outlet',
            'creator',
            'attachments'
        ])->findOrFail($id);

        $categories = TicketCategory::active()->orderBy('name')->get();
        $priorities = TicketPriority::active()->orderBy('level', 'desc')->get();
        $statuses = TicketStatus::active()->ordered()->get();
        $divisis = Divisi::active()->orderBy('nama_divisi')->get();
        $outlets = Outlet::active()->orderBy('nama_outlet')->get();

        return Inertia::render('Tickets/Edit', [
            'ticket' => $ticket,
            'categories' => $categories,
            'priorities' => $priorities,
            'statuses' => $statuses,
            'divisis' => $divisis,
            'outlets' => $outlets,
        ]);
    }

    /**
     * Update the specified ticket
     */
    public function update(Request $request, $id)
    {
        $ticket = Ticket::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:ticket_categories,id',
            'priority_id' => 'required|exists:ticket_priorities,id',
            'status_id' => 'required|exists:ticket_statuses,id',
            'divisi_id' => 'required|exists:tbl_data_divisi,id',
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
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
            $oldData = $ticket->toArray();
            
            // Recalculate due date if priority changed
            $dueDate = $ticket->due_date;
            if ($oldData['priority_id'] != $request->priority_id) {
                $priority = TicketPriority::findOrFail($request->priority_id);
                $dueDate = now()->addDays($priority->max_days ?? 7);
            }
            
            $ticket->update([
                'title' => $request->title,
                'description' => $request->description,
                'category_id' => $request->category_id,
                'priority_id' => $request->priority_id,
                'status_id' => $request->status_id,
                'divisi_id' => $request->divisi_id,
                'outlet_id' => $request->outlet_id,
                'due_date' => $dueDate,
            ]);

            // Create ticket history for changes
            $this->createTicketHistory($ticket, 'updated', null, null, 'Ticket updated');


            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket berhasil diperbarui',
                'data' => $ticket->load(['category', 'priority', 'status', 'divisi', 'outlet', 'creator'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui ticket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified ticket
     */
    public function destroy($id)
    {
        $ticket = Ticket::findOrFail($id);

        try {
            $ticket->delete();

            return response()->json([
                'success' => true,
                'message' => 'Ticket berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus ticket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create ticket from daily report concern
     */
    public function createFromDailyReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'daily_report_id' => 'required|exists:daily_reports,id',
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
            // Get daily report data
            $dailyReport = \App\Models\DailyReport::with(['outlet', 'department'])->findOrFail($request->daily_report_id);
            $area = \App\Models\Area::findOrFail($request->area_id);
            $divisiConcern = \App\Models\Divisi::findOrFail($request->divisi_concern_id);

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
            $this->createTicketHistory($ticket, 'created', null, null, 
                "Ticket created from Daily Report #{$dailyReport->id}");

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
     * Add comment to ticket
     */
    public function addComment(Request $request, $id)
    {
        $ticket = Ticket::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $comment = \App\Models\TicketComment::create([
                'ticket_id' => $ticket->id,
                'user_id' => auth()->id(),
                'comment' => $request->comment,
                'is_internal' => false,
            ]);

            // Create ticket history
            $this->createTicketHistory($ticket, 'comment_added', null, null, 'Comment added: ' . substr($request->comment, 0, 50) . '...');

            // Send notifications for new comment
            $this->sendCommentNotifications($ticket, $comment);

            return response()->json([
                'success' => true,
                'message' => 'Komentar berhasil ditambahkan',
                'data' => $comment->load('user')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan komentar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update comment
     */
    public function updateComment(Request $request, $id)
    {
        $comment = \App\Models\TicketComment::findOrFail($id);

        // Check if user can edit this comment (only the author or admin)
        if ($comment->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki izin untuk mengedit komentar ini'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $comment->update([
                'comment' => $request->comment,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Komentar berhasil diperbarui',
                'data' => $comment->load('user')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui komentar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete comment
     */
    public function deleteComment($id)
    {
        $comment = \App\Models\TicketComment::findOrFail($id);

        // Check if user can delete this comment (only the author or admin)
        if ($comment->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki izin untuk menghapus komentar ini'
            ], 403);
        }

        try {
            $comment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Komentar berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus komentar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send notifications to users in the selected division when a new ticket is created
     */
    private function sendTicketCreatedNotifications($ticket)
    {
        try {
            // Get users in the selected division with status 'A'
            $users = \App\Models\User::where('division_id', $ticket->divisi_id)
                ->where('status', 'A')
                ->get();

            // Get creator name
            $creator = auth()->user();
            
            // Get outlet name
            $outlet = \App\Models\Outlet::find($ticket->outlet_id);
            
            // Get divisi name
            $divisi = \App\Models\Divisi::find($ticket->divisi_id);

            foreach ($users as $user) {
                \DB::table('notifications')->insert([
                    'user_id' => $user->id,
                    'task_id' => $ticket->id,
                    'type' => 'ticket_created',
                    'message' => "Ticket baru telah dibuat:\n\nNo: {$ticket->ticket_number}\nJudul: {$ticket->title}\nDivisi: {$divisi->nama_divisi}\nOutlet: {$outlet->nama_outlet}\nDibuat oleh: {$creator->nama_lengkap}",
                    'url' => config('app.url') . '/tickets/' . $ticket->id,
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            \Log::info('Ticket created notifications sent', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'divisi_id' => $ticket->divisi_id,
                'notified_users_count' => $users->count()
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to send ticket created notifications', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Send notifications for new comment to all commenters and division users
     */
    private function sendCommentNotifications($ticket, $comment)
    {
        try {
            // Get all users who have commented on this ticket
            $commenters = \App\Models\TicketComment::where('ticket_id', $ticket->id)
                ->where('user_id', '!=', auth()->id()) // Exclude the current commenter
                ->pluck('user_id')
                ->unique();

            // Get all users in the ticket's division with status 'A'
            $divisionUsers = \App\Models\User::where('division_id', $ticket->divisi_id)
                ->where('status', 'A')
                ->where('id', '!=', auth()->id()) // Exclude the current commenter
                ->pluck('id');

            // Combine and remove duplicates
            $notifyUserIds = $commenters->merge($divisionUsers)->unique();

            // Get commenter name
            $commenter = auth()->user();
            
            // Get outlet name
            $outlet = \App\Models\Outlet::find($ticket->outlet_id);
            
            // Get divisi name
            $divisi = \App\Models\Divisi::find($ticket->divisi_id);

            // Create notification message
            $message = "Komentar baru pada ticket:\n\n";
            $message .= "No: {$ticket->ticket_number}\n";
            $message .= "Judul: {$ticket->title}\n";
            $message .= "Divisi: {$divisi->nama_divisi}\n";
            $message .= "Outlet: {$outlet->nama_outlet}\n";
            $message .= "Komentar: " . substr($comment->comment, 0, 100) . (strlen($comment->comment) > 100 ? '...' : '') . "\n";
            $message .= "Dari: {$commenter->nama_lengkap}";

            foreach ($notifyUserIds as $userId) {
                \DB::table('notifications')->insert([
                    'user_id' => $userId,
                    'task_id' => $ticket->id,
                    'type' => 'ticket_comment',
                    'message' => $message,
                    'url' => config('app.url') . '/tickets/' . $ticket->id,
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            \Log::info('Ticket comment notifications sent', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'comment_id' => $comment->id,
                'commenter_id' => auth()->id(),
                'notified_users_count' => $notifyUserIds->count(),
                'commenters_count' => $commenters->count(),
                'division_users_count' => $divisionUsers->count()
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to send ticket comment notifications', [
                'ticket_id' => $ticket->id,
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Create ticket history record
     */
    private function createTicketHistory($ticket, $action, $fieldName = null, $oldValue = null, $description = null)
    {
        \App\Models\TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'field_name' => $fieldName,
            'old_value' => $oldValue,
            'new_value' => null,
            'description' => $description,
        ]);
    }

    /**
     * Get ticket categories for API
     */
    public function getCategories()
    {
        $categories = TicketCategory::active()->orderBy('name')->get();
        
        return response()->json([
            'success' => true,
            'categories' => $categories
        ]);
    }

    /**
     * Get ticket priorities for API
     */
    public function getPriorities()
    {
        $priorities = TicketPriority::active()->orderBy('level', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'priorities' => $priorities
        ]);
    }

    /**
     * Get tickets by area and outlet for API
     */
    public function getTicketsByArea($areaId, Request $request)
    {
        $area = \App\Models\Area::findOrFail($areaId);
        $outletId = $request->get('outlet_id');
        
        if (!$outletId) {
            return response()->json([
                'success' => false,
                'message' => 'Outlet ID is required'
            ], 400);
        }
        
        // Get tickets that contain the area name in title, match outlet, and are not closed/cancelled
        $tickets = Ticket::with(['status', 'category', 'priority', 'divisi', 'outlet'])
            ->where('title', 'like', "%{$area->nama_area}%")
            ->where('outlet_id', $outletId)
            ->whereHas('status', function($query) {
                $query->whereNotIn('slug', ['closed', 'cancelled']);
            })
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
        
        return response()->json([
            'success' => true,
            'tickets' => $tickets
        ]);
    }
}
