<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\NotificationService;

class ApprovalController extends Controller
{
    /**
     * Get pending approvals for the current user (as approver)
     */
    public function getPendingApprovals(Request $request)
    {
        $userId = auth()->id();
        $limit = $request->get('limit', 10);
        
        // Get pending approval requests where current user is the approver
        $approvals = DB::table('approval_requests')
            ->join('users', 'approval_requests.user_id', '=', 'users.id')
            ->join('leave_types', 'approval_requests.leave_type_id', '=', 'leave_types.id')
            ->where('approval_requests.approver_id', $userId)
            ->where('approval_requests.status', 'pending')
            ->select([
                'approval_requests.id',
                'approval_requests.user_id',
                'approval_requests.date_from',
                'approval_requests.date_to',
                'approval_requests.reason',
                'approval_requests.created_at',
                'users.nama_lengkap as user_name',
                'leave_types.name as leave_type_name',
                'leave_types.id as leave_type_id'
            ])
            ->orderBy('approval_requests.created_at', 'desc')
            ->limit($limit)
            ->get();
            
        // Format the data to match the expected structure
        $formattedApprovals = $approvals->map(function($approval) {
            return (object)[
                'id' => $approval->id,
                'user_id' => $approval->user_id,
                'date_from' => $approval->date_from,
                'date_to' => $approval->date_to,
                'reason' => $approval->reason,
                'created_at' => $approval->created_at,
                'user' => (object)[
                    'id' => $approval->user_id,
                    'nama_lengkap' => $approval->user_name
                ],
                'leave_type' => (object)[
                    'id' => $approval->leave_type_id,
                    'name' => $approval->leave_type_name
                ],
                'duration_text' => $this->calculateDuration($approval->date_from, $approval->date_to)
            ];
        });
            
        return response()->json([
            'success' => true,
            'approvals' => $formattedApprovals
        ]);
    }
    
    /**
     * Get approval request details
     */
    public function getApprovalDetails(Request $request, $id)
    {
        $userId = auth()->id();
        
        // Get approval request details
        $approval = DB::table('approval_requests')
            ->join('users', 'approval_requests.user_id', '=', 'users.id')
            ->join('leave_types', 'approval_requests.leave_type_id', '=', 'leave_types.id')
            ->leftJoin('users as approvers', 'approval_requests.approver_id', '=', 'approvers.id')
            ->leftJoin('users as hrd_approvers', 'approval_requests.hrd_approver_id', '=', 'hrd_approvers.id')
            ->where('approval_requests.id', $id)
            ->select([
                'approval_requests.*',
                'users.nama_lengkap as user_name',
                'users.id_jabatan as user_jabatan_id',
                'users.id_outlet as user_outlet_id',
                'leave_types.name as leave_type_name',
                'leave_types.id as leave_type_id',
                'approvers.nama_lengkap as approver_name',
                'hrd_approvers.nama_lengkap as hrd_approver_name'
            ])
            ->first();
            
        if (!$approval) {
            return response()->json([
                'success' => false,
                'message' => 'Approval request not found'
            ], 404);
        }
        
        // Parse document paths
        $documentPaths = [];
        if ($approval->document_paths) {
            $documentPaths = json_decode($approval->document_paths, true) ?: [];
        } elseif ($approval->document_path) {
            $documentPaths = [$approval->document_path];
        }

        // Format the data to match the expected structure
        $formattedApproval = (object)[
            'id' => $approval->id,
            'user_id' => $approval->user_id,
            'date_from' => $approval->date_from,
            'date_to' => $approval->date_to,
            'reason' => $approval->reason,
            'status' => $approval->status,
            'hrd_status' => $approval->hrd_status,
            'approved_at' => $approval->approved_at,
            'rejected_at' => $approval->rejected_at,
            'hrd_approved_at' => $approval->hrd_approved_at,
            'hrd_rejected_at' => $approval->hrd_rejected_at,
            'approval_notes' => $approval->approval_notes,
            'hrd_approval_notes' => $approval->hrd_approval_notes,
            'created_at' => $approval->created_at,
            'document_path' => $approval->document_path,
            'document_paths' => $documentPaths,
            'user' => (object)[
                'id' => $approval->user_id,
                'nama_lengkap' => $approval->user_name
            ],
            'leave_type' => (object)[
                'id' => $approval->leave_type_id,
                'name' => $approval->leave_type_name
            ],
            'approver' => $approval->approver_name ? (object)[
                'nama_lengkap' => $approval->approver_name
            ] : null,
            'hrd_approver' => $approval->hrd_approver_name ? (object)[
                'nama_lengkap' => $approval->hrd_approver_name
            ] : null,
            'duration_text' => $this->calculateDuration($approval->date_from, $approval->date_to)
        ];
        
        return response()->json([
            'success' => true,
            'approval' => $formattedApproval
        ]);
    }
    
    /**
     * Calculate duration text
     */
    private function calculateDuration($dateFrom, $dateTo)
    {
        $from = Carbon::parse($dateFrom);
        $to = Carbon::parse($dateTo);
        
        if ($from->isSameDay($to)) {
            return '1 hari';
        }
        
        $days = $from->diffInDays($to) + 1;
        return $days . ' hari';
    }
    
    /**
     * Approve a request
     */
    public function approve(Request $request, $id)
    {
        $request->validate([
            'notes' => 'nullable|string|max:500'
        ]);
        
        $userId = auth()->id();
        
        // Get absent request and check if current user is the approver
        $absentRequest = DB::table('absent_requests')
            ->join('users', 'absent_requests.user_id', '=', 'users.id')
            ->join('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->join('users as approvers', function($join) {
                $join->on('tbl_data_jabatan.id_atasan', '=', 'approvers.id_jabatan')
                     ->where('approvers.id_outlet', '=', DB::raw('users.id_outlet'))
                     ->where('approvers.status', '=', 'A');
            })
            ->where('absent_requests.id', $id)
            ->where('approvers.id', $userId)
            ->where('absent_requests.status', 'pending')
            ->select([
                'absent_requests.*',
                'users.nama_lengkap as user_name',
                'users.id as user_id'
            ])
            ->first();
            
        if (!$absentRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Approval request not found or already processed'
            ], 404);
        }
        
        try {
            DB::beginTransaction();
            
            // Update absent request to supervisor_approved (waiting for HRD approval)
            DB::table('absent_requests')
                ->where('id', $id)
                ->update([
                    'status' => 'supervisor_approved',
                    'approved_by' => $userId,
                    'approved_at' => now(),
                    'updated_at' => now()
                ]);
            
            // Kirim notifikasi ke user yang mengajukan
            $approver = auth()->user();
            DB::table('notifications')->insert([
                'user_id' => $absentRequest->user_id,
                'type' => 'leave_supervisor_approved',
                'message' => "Permohonan izin/cuti Anda untuk periode {$absentRequest->date_from} - {$absentRequest->date_to} telah disetujui oleh atasan ({$approver->nama_lengkap}). Menunggu persetujuan HRD.",
                'url' => config('app.url') . '/attendance',
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Kirim notifikasi ke semua HRD (division_id=6 dan status=A)
            $hrdUsers = DB::table('users')
                ->where('division_id', 6)
                ->where('status', 'A')
                ->select('id')
                ->get();
                
            foreach ($hrdUsers as $hrdUser) {
                DB::table('notifications')->insert([
                    'user_id' => $hrdUser->id,
                    'type' => 'leave_hrd_approval_request',
                    'message' => "Permohonan izin/cuti dari {$absentRequest->user_name} untuk periode {$absentRequest->date_from} - {$absentRequest->date_to} telah disetujui oleh atasan dan membutuhkan persetujuan HRD Anda.",
                    'url' => config('app.url') . '/home',
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Permohonan izin/cuti berhasil disetujui'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error approving request: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyetujui permohonan'
            ], 500);
        }
    }
    
    /**
     * Reject a request
     */
    public function reject(Request $request, $id)
    {
        $request->validate([
            'notes' => 'required|string|max:500'
        ]);
        
        $userId = auth()->id();
        
        // Get absent request and check if current user is the approver
        $absentRequest = DB::table('absent_requests')
            ->join('users', 'absent_requests.user_id', '=', 'users.id')
            ->join('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->join('users as approvers', function($join) {
                $join->on('tbl_data_jabatan.id_atasan', '=', 'approvers.id_jabatan')
                     ->where('approvers.id_outlet', '=', DB::raw('users.id_outlet'))
                     ->where('approvers.status', '=', 'A');
            })
            ->where('absent_requests.id', $id)
            ->where('approvers.id', $userId)
            ->where('absent_requests.status', 'pending')
            ->select([
                'absent_requests.*',
                'users.nama_lengkap as user_name',
                'users.id as user_id'
            ])
            ->first();
            
        if (!$absentRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Approval request not found or already processed'
            ], 404);
        }
        
        try {
            DB::beginTransaction();
            
            // Update absent request
            DB::table('absent_requests')
                ->where('id', $id)
                ->update([
                    'status' => 'rejected',
                    'rejected_by' => $userId,
                    'rejected_at' => now(),
                    'rejection_reason' => $request->input('notes'),
                    'updated_at' => now()
                ]);
            
            // Kirim notifikasi ke user yang mengajukan
            $approver = auth()->user();
            $rejectionReason = $request->input('notes') ? "\n\nAlasan penolakan: {$request->input('notes')}" : '';
            DB::table('notifications')->insert([
                'user_id' => $absentRequest->user_id,
                'type' => 'leave_rejected',
                'message' => "Permohonan izin/cuti Anda untuk periode {$absentRequest->date_from} - {$absentRequest->date_to} telah ditolak oleh {$approver->nama_lengkap}.{$rejectionReason}",
                'url' => config('app.url') . '/attendance',
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Permohonan izin/cuti berhasil ditolak'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error rejecting request: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menolak permohonan'
            ], 500);
        }
    }
    
    /**
     * Get pending HRD approvals
     */
    public function getPendingHrdApprovals(Request $request)
    {
        $userId = auth()->id();
        $limit = $request->get('limit', 10);
        
        // Only HRD users (division_id=6) can see HRD approvals
        if (auth()->user()->division_id !== 6) {
            return response()->json([
                'success' => true,
                'approvals' => []
            ]);
        }
        
        // Get supervisor_approved absent requests
        $approvals = DB::table('absent_requests')
            ->join('users', 'absent_requests.user_id', '=', 'users.id')
            ->join('leave_types', 'absent_requests.leave_type_id', '=', 'leave_types.id')
            ->where('absent_requests.status', 'supervisor_approved')
            ->select([
                'absent_requests.id',
                'absent_requests.user_id',
                'absent_requests.date_from',
                'absent_requests.date_to',
                'absent_requests.reason',
                'absent_requests.created_at',
                'users.nama_lengkap as user_name',
                'leave_types.name as leave_type_name',
                'leave_types.id as leave_type_id'
            ])
            ->orderBy('absent_requests.created_at', 'desc')
            ->limit($limit)
            ->get();
            
        // Format the data to match the expected structure
        $formattedApprovals = $approvals->map(function($approval) {
            return (object)[
                'id' => $approval->id,
                'user_id' => $approval->user_id,
                'date_from' => $approval->date_from,
                'date_to' => $approval->date_to,
                'reason' => $approval->reason,
                'created_at' => $approval->created_at,
                'user' => (object)[
                    'id' => $approval->user_id,
                    'nama_lengkap' => $approval->user_name
                ],
                'leave_type' => (object)[
                    'id' => $approval->leave_type_id,
                    'name' => $approval->leave_type_name
                ],
                'duration_text' => $this->calculateDuration($approval->date_from, $approval->date_to)
            ];
        });
            
        return response()->json([
            'success' => true,
            'approvals' => $formattedApprovals
        ]);
    }
    
    /**
     * HRD Approve a request
     */
    public function hrdApprove(Request $request, $id)
    {
        $request->validate([
            'notes' => 'nullable|string|max:500'
        ]);
        
        $userId = auth()->id();
        
        // Only HRD users can approve
        if (auth()->user()->division_id !== 6) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }
        
        // Get supervisor_approved absent request
        $absentRequest = DB::table('absent_requests')
            ->join('users', 'absent_requests.user_id', '=', 'users.id')
            ->where('absent_requests.id', $id)
            ->where('absent_requests.status', 'supervisor_approved')
            ->select([
                'absent_requests.*',
                'users.nama_lengkap as user_name',
                'users.id as user_id'
            ])
            ->first();
            
        if (!$absentRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Approval request not found or already processed'
            ], 404);
        }
        
        try {
            DB::beginTransaction();
            
            // Update absent request to approved
            DB::table('absent_requests')
                ->where('id', $id)
                ->update([
                    'status' => 'approved',
                    'hrd_approved_by' => $userId,
                    'hrd_approved_at' => now(),
                    'updated_at' => now()
                ]);
            
            // Kirim notifikasi ke user yang mengajukan
            $hrdApprover = auth()->user();
            DB::table('notifications')->insert([
                'user_id' => $absentRequest->user_id,
                'type' => 'leave_approved',
                'message' => "Permohonan izin/cuti Anda untuk periode {$absentRequest->date_from} - {$absentRequest->date_to} telah disetujui oleh HRD ({$hrdApprover->nama_lengkap}).",
                'url' => config('app.url') . '/attendance',
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Permohonan izin/cuti berhasil disetujui oleh HRD'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error HRD approving request: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyetujui permohonan'
            ], 500);
        }
    }
    
    /**
     * HRD Reject a request
     */
    public function hrdReject(Request $request, $id)
    {
        $request->validate([
            'notes' => 'required|string|max:500'
        ]);
        
        $userId = auth()->id();
        
        // Only HRD users can reject
        if (auth()->user()->division_id !== 6) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }
        
        // Get supervisor_approved absent request
        $absentRequest = DB::table('absent_requests')
            ->join('users', 'absent_requests.user_id', '=', 'users.id')
            ->where('absent_requests.id', $id)
            ->where('absent_requests.status', 'supervisor_approved')
            ->select([
                'absent_requests.*',
                'users.nama_lengkap as user_name',
                'users.id as user_id'
            ])
            ->first();
            
        if (!$absentRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Approval request not found or already processed'
            ], 404);
        }
        
        try {
            DB::beginTransaction();
            
            // Update absent request to rejected
            DB::table('absent_requests')
                ->where('id', $id)
                ->update([
                    'status' => 'rejected',
                    'hrd_rejected_by' => $userId,
                    'hrd_rejected_at' => now(),
                    'hrd_rejection_reason' => $request->input('notes'),
                    'updated_at' => now()
                ]);
            
            // Kirim notifikasi ke user yang mengajukan
            $hrdRejector = auth()->user();
            $rejectionReason = $request->input('notes') ? "\n\nAlasan penolakan: {$request->input('notes')}" : '';
            DB::table('notifications')->insert([
                'user_id' => $absentRequest->user_id,
                'type' => 'leave_rejected',
                'message' => "Permohonan izin/cuti Anda untuk periode {$absentRequest->date_from} - {$absentRequest->date_to} telah ditolak oleh HRD ({$hrdRejector->nama_lengkap}).{$rejectionReason}",
                'url' => config('app.url') . '/attendance',
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Permohonan izin/cuti berhasil ditolak oleh HRD'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error HRD rejecting request: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menolak permohonan'
            ], 500);
        }
    }
    
    /**
     * Get approval statistics for the current user
     */
    public function getApprovalStats(Request $request)
    {
        $userId = auth()->id();
        
        // Get pending count from absent_requests where current user is approver
        $pendingCount = DB::table('absent_requests')
            ->join('users', 'absent_requests.user_id', '=', 'users.id')
            ->join('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->join('users as approvers', function($join) {
                $join->on('tbl_data_jabatan.id_atasan', '=', 'approvers.id_jabatan')
                     ->where('approvers.id_outlet', '=', DB::raw('users.id_outlet'))
                     ->where('approvers.status', '=', 'A');
            })
            ->where('approvers.id', $userId)
            ->where('absent_requests.status', 'pending')
            ->count();
        
        $stats = [
            'pending_count' => $pendingCount,
            'approved_count' => 0,
            'rejected_count' => 0,
            'total_count' => $pendingCount
        ];
        
        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }
    
    /**
     * Get user's own approval requests (as requester)
     */
    public function getMyRequests(Request $request)
    {
        $userId = auth()->id();
        $limit = $request->get('limit', 10);
        
        $requests = ApprovalRequest::with(['approver', 'hrdApprover', 'leaveType'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
            
        return response()->json([
            'success' => true,
            'requests' => $requests
        ]);
    }

    /**
     * Get user's notifications related to leave requests
     */
    public function getLeaveNotifications(Request $request)
    {
        $userId = auth()->id();
        $limit = $request->get('limit', 10);
        
        $notifications = DB::table('notifications')
            ->where('user_id', $userId)
            ->whereIn('type', ['leave_approval_request', 'leave_supervisor_approved', 'leave_approved', 'leave_rejected', 'leave_hrd_approval_request'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
            
        return response()->json([
            'success' => true,
            'notifications' => $notifications
        ]);
    }



}
