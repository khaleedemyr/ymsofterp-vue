<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
        
        // Get approval request and check if current user is the approver
        $approvalRequest = DB::table('approval_requests')
            ->join('users', 'approval_requests.user_id', '=', 'users.id')
            ->where('approval_requests.id', $id)
            ->where('approval_requests.approver_id', $userId)
            ->where('approval_requests.status', 'pending')
            ->select([
                'approval_requests.*',
                'users.nama_lengkap as user_name',
                'users.id as user_id'
            ])
            ->first();
            
        if (!$approvalRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Approval request not found or already processed'
            ], 404);
        }
        
        try {
            DB::beginTransaction();
            
            // Update approval request to approved
            DB::table('approval_requests')
                ->where('id', $id)
                ->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approval_notes' => $request->notes,
                    'updated_at' => now()
                ]);
            
            // Update corresponding absent request to supervisor_approved
            DB::table('absent_requests')
                ->where('approval_request_id', $id)
                ->update([
                    'status' => 'supervisor_approved',
                    'approved_by' => $userId,
                    'approved_at' => now(),
                    'updated_at' => now()
                ]);
            
            // Kirim notifikasi ke user yang mengajukan
            $approver = auth()->user();
            DB::table('notifications')->insert([
                'user_id' => $approvalRequest->user_id,
                'type' => 'leave_approved',
                'message' => "Permohonan izin/cuti Anda untuk periode {$approvalRequest->date_from} - {$approvalRequest->date_to} telah disetujui oleh atasan ({$approver->nama_lengkap}). Menunggu persetujuan HRD.",
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
                    'message' => "Permohonan izin/cuti dari {$approvalRequest->user_name} untuk periode {$approvalRequest->date_from} - {$approvalRequest->date_to} telah disetujui oleh atasan dan membutuhkan persetujuan HRD Anda.",
                    'url' => config('app.url') . '/home',
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Approval request approved successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve request: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Reject a request
     */
    public function reject(Request $request, $id)
    {
        $request->validate([
            'notes' => 'nullable|string|max:500'
        ]);
        
        $userId = auth()->id();
        
        // Get approval request and check if current user is the approver
        $approvalRequest = DB::table('approval_requests')
            ->join('users', 'approval_requests.user_id', '=', 'users.id')
            ->where('approval_requests.id', $id)
            ->where('approval_requests.approver_id', $userId)
            ->where('approval_requests.status', 'pending')
            ->select([
                'approval_requests.*',
                'users.nama_lengkap as user_name',
                'users.id as user_id'
            ])
            ->first();
            
        if (!$approvalRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Approval request not found or already processed'
            ], 404);
        }
        
        try {
            DB::beginTransaction();
            
            // Update approval request to rejected
            DB::table('approval_requests')
                ->where('id', $id)
                ->update([
                    'status' => 'rejected',
                    'rejected_at' => now(),
                    'approval_notes' => $request->notes,
                    'updated_at' => now()
                ]);
            
            // Update corresponding absent request to rejected
            DB::table('absent_requests')
                ->where('approval_request_id', $id)
                ->update([
                    'status' => 'rejected',
                    'rejected_by' => $userId,
                    'rejected_at' => now(),
                    'rejection_reason' => $request->notes,
                    'updated_at' => now()
                ]);
            
            // Kirim notifikasi ke user yang mengajukan
            $approver = auth()->user();
            DB::table('notifications')->insert([
                'user_id' => $approvalRequest->user_id,
                'type' => 'leave_rejected',
                'message' => "Permohonan izin/cuti Anda untuk periode {$approvalRequest->date_from} - {$approvalRequest->date_to} telah ditolak oleh atasan ({$approver->nama_lengkap}).",
                'url' => config('app.url') . '/attendance',
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Approval request rejected successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject request: ' . $e->getMessage()
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
        
        // Only HRD users (division_id = 6) can access this
        if (auth()->user()->division_id != 6) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }
        
        // Get pending approval requests that need HRD approval
        $approvals = DB::table('approval_requests')
            ->join('users', 'approval_requests.user_id', '=', 'users.id')
            ->join('leave_types', 'approval_requests.leave_type_id', '=', 'leave_types.id')
            ->where('approval_requests.status', 'approved')
            ->where('approval_requests.hrd_status', 'pending')
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
     * Get notifications for leave approvals
     */
    public function getNotifications(Request $request)
    {
        $userId = auth()->id();
        $user = auth()->user();
        
        // Check if this user is an approver (supervisor or HRD)
        $isApprover = DB::table('approval_requests')
            ->where('approver_id', $userId)
            ->orWhere('hrd_approver_id', $userId)
            ->exists();
            
        if ($isApprover) {
            // This user is an approver - they should NOT see leave_approved/leave_rejected notifications
            // These notifications are only for the user who submitted the request
            $query = DB::table('notifications')
                ->where('user_id', $userId);
                
            // Filter based on user role
            if ($user->division_id === 6) {
                // HRD users should only see leave_hrd_approval_request notifications
                $query->where('type', 'leave_hrd_approval_request');
            } else {
                // Supervisor users should only see leave_approval_request notifications
                $query->where('type', 'leave_approval_request');
            }
        } else {
            // This user is NOT an approver - they should see leave_approved/leave_rejected notifications
            // These are notifications about their own requests being approved/rejected
            $query = DB::table('notifications')
                ->where('user_id', $userId)
                ->whereIn('type', ['leave_approved', 'leave_rejected']);
        }
        
        $notifications = $query->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
            
        return response()->json([
            'success' => true,
            'notifications' => $notifications
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
        
        // Get approval request that needs HRD approval
        $approvalRequest = DB::table('approval_requests')
            ->join('users', 'approval_requests.user_id', '=', 'users.id')
            ->where('approval_requests.id', $id)
            ->where('approval_requests.status', 'approved')
            ->where('approval_requests.hrd_status', 'pending')
            ->select([
                'approval_requests.*',
                'users.nama_lengkap as user_name',
                'users.id as user_id'
            ])
            ->first();
            
        if (!$approvalRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Approval request not found or already processed'
            ], 404);
        }
        
        try {
            DB::beginTransaction();
            
            // Update approval request to HRD approved
            DB::table('approval_requests')
                ->where('id', $id)
                ->update([
                    'hrd_status' => 'approved',
                    'hrd_approved_at' => now(),
                    'hrd_approval_notes' => $request->notes,
                    'updated_at' => now()
                ]);
            
            // Update corresponding absent request to approved (if exists)
            $absentRequestUpdated = DB::table('absent_requests')
                ->where('approval_request_id', $id)
                ->update([
                    'status' => 'approved',
                    'hrd_approved_by' => $userId,
                    'hrd_approved_at' => now(),
                    'updated_at' => now()
                ]);
                
            // Log if no absent request was found
            if ($absentRequestUpdated === 0) {
                \Log::warning("No absent_request found for approval_request_id: {$id}");
            }
            
            // Kirim notifikasi ke user yang mengajukan
            $hrdApprover = auth()->user();
            DB::table('notifications')->insert([
                'user_id' => $approvalRequest->user_id,
                'type' => 'leave_approved',
                'message' => "Permohonan izin/cuti Anda untuk periode {$approvalRequest->date_from} - {$approvalRequest->date_to} telah disetujui oleh HRD ({$hrdApprover->nama_lengkap}).",
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
        
        // Get approval request that needs HRD approval
        $approvalRequest = DB::table('approval_requests')
            ->join('users', 'approval_requests.user_id', '=', 'users.id')
            ->where('approval_requests.id', $id)
            ->where('approval_requests.status', 'approved')
            ->where('approval_requests.hrd_status', 'pending')
            ->select([
                'approval_requests.*',
                'users.nama_lengkap as user_name',
                'users.id as user_id'
            ])
            ->first();
            
        if (!$approvalRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Approval request not found or already processed'
            ], 404);
        }
        
        try {
            DB::beginTransaction();
            
            // Update approval request to HRD rejected
            DB::table('approval_requests')
                ->where('id', $id)
                ->update([
                    'hrd_status' => 'rejected',
                    'hrd_rejected_at' => now(),
                    'hrd_approval_notes' => $request->notes,
                    'updated_at' => now()
                ]);
            
            // Update corresponding absent request to rejected (if exists)
            $absentRequestUpdated = DB::table('absent_requests')
                ->where('approval_request_id', $id)
                ->update([
                    'status' => 'rejected',
                    'hrd_rejected_by' => $userId,
                    'hrd_rejected_at' => now(),
                    'rejection_reason' => $request->notes,
                    'updated_at' => now()
                ]);
                
            // Log if no absent request was found
            if ($absentRequestUpdated === 0) {
                \Log::warning("No absent_request found for approval_request_id: {$id}");
            }
            
            // Kirim notifikasi ke user yang mengajukan
            $hrdApprover = auth()->user();
            DB::table('notifications')->insert([
                'user_id' => $approvalRequest->user_id,
                'type' => 'leave_rejected',
                'message' => "Permohonan izin/cuti Anda untuk periode {$approvalRequest->date_from} - {$approvalRequest->date_to} telah ditolak oleh HRD ({$hrdApprover->nama_lengkap}).",
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
     * Mark notification as read
     */
    public function markNotificationAsRead(Request $request, $id)
    {
        $userId = auth()->id();
        
        try {
            $notification = DB::table('notifications')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->first();
                
            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found'
                ], 404);
            }
            
            DB::table('notifications')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->update([
                    'is_read' => true,
                    'updated_at' => now()
                ]);
                
            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read: ' . $e->getMessage()
            ], 500);
        }
    }
}
