<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\ExtraOffService;
use App\Services\LeaveManagementService;
use App\Services\HolidayAttendanceService;

class ApprovalController extends Controller
{
    /**
     * Get pending approvals for the current user (as approver)
     */
    public function getPendingApprovals(Request $request)
    {
        $user = auth()->user();
        $userId = auth()->id();
        $limit = $request->get('limit', 10);
        
        // Superadmin: user dengan id_role = '5af56935b011a' bisa melihat semua approval
        $isSuperadmin = $user && $user->id_role === '5af56935b011a';
        
        // Get pending approvals from approval flows (new flow - sequential approval)
        $approvalFlowsQuery = DB::table('absent_request_approval_flows as arf')
            ->join('absent_requests as ar', 'arf.absent_request_id', '=', 'ar.id')
            ->join('approval_requests as apr', 'ar.approval_request_id', '=', 'apr.id')
            ->join('users', 'apr.user_id', '=', 'users.id')
            ->join('leave_types', 'apr.leave_type_id', '=', 'leave_types.id')
            ->where('arf.status', 'PENDING');
        
        // Superadmin can see all, regular users only their own
        if (!$isSuperadmin) {
            $approvalFlowsQuery->where('arf.approver_id', $userId);
        }
        
        $approvalFlows = $approvalFlowsQuery
            ->select([
                'apr.id',
                'apr.user_id',
                'apr.date_from',
                'apr.date_to',
                'apr.reason',
                'apr.created_at',
                'users.nama_lengkap as user_name',
                'leave_types.name as leave_type_name',
                'leave_types.id as leave_type_id',
                'arf.approval_level',
                'ar.id as absent_request_id'
            ])
            ->orderBy('apr.created_at', 'desc')
            ->get();
        
        // Filter: Only show if current user is the next approver in line (skip for superadmin)
        $filteredApprovals = $approvalFlows->filter(function($approval) use ($isSuperadmin) {
            if ($isSuperadmin) {
                return true; // Superadmin can see all
            }
            
            // Get all pending flows for this absent request
            $pendingFlows = DB::table('absent_request_approval_flows')
                ->where('absent_request_id', $approval->absent_request_id)
                ->where('status', 'PENDING')
                ->orderBy('approval_level')
                ->get();
            
            // Check if current user is the first pending approver (next in line)
            if ($pendingFlows->isEmpty()) return false;
            $nextApprover = $pendingFlows->first();
            return $nextApprover->approver_id == auth()->id();
        });
        
        // Also get old flow approvals (backward compatibility)
        $oldApprovalsQuery = DB::table('approval_requests')
            ->join('users', 'approval_requests.user_id', '=', 'users.id')
            ->join('leave_types', 'approval_requests.leave_type_id', '=', 'leave_types.id')
            ->leftJoin('absent_requests', 'approval_requests.id', '=', 'absent_requests.approval_request_id')
            ->leftJoin('absent_request_approval_flows', function($join) use ($userId) {
                $join->on('absent_requests.id', '=', 'absent_request_approval_flows.absent_request_id')
                     ->where('absent_request_approval_flows.approver_id', '=', $userId);
            })
            ->where('approval_requests.status', 'pending')
            ->whereNull('absent_request_approval_flows.id'); // Only old flow (no approval flows)
        
        // Superadmin can see all, regular users only their own
        if (!$isSuperadmin) {
            $oldApprovalsQuery->where('approval_requests.approver_id', $userId);
        }
        
        $oldApprovals = $oldApprovalsQuery
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
            ->get();
        
        // Combine and format
        $allApprovals = $filteredApprovals->merge($oldApprovals)->take($limit);
        
        $formattedApprovals = $allApprovals->map(function($approval) {
            // Get approver name - for new flow, get from approval flows; for old flow, get from approver_id
            $approverName = null;
            if (isset($approval->absent_request_id)) {
                // New flow: get next pending approver
                $nextFlow = DB::table('absent_request_approval_flows')
                    ->join('users', 'absent_request_approval_flows.approver_id', '=', 'users.id')
                    ->where('absent_request_id', $approval->absent_request_id)
                    ->where('status', 'PENDING')
                    ->orderBy('approval_level')
                    ->select('users.nama_lengkap')
                    ->first();
                $approverName = $nextFlow ? $nextFlow->nama_lengkap : null;
            } else {
                // Old flow: get approver from approval_requests
                $approver = DB::table('approval_requests')
                    ->join('users', 'approval_requests.approver_id', '=', 'users.id')
                    ->where('approval_requests.id', $approval->id)
                    ->select('users.nama_lengkap')
                    ->first();
                $approverName = $approver ? $approver->nama_lengkap : null;
            }
            
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
                'duration_text' => $this->calculateDuration($approval->date_from, $approval->date_to),
                'approval_level' => $approval->approval_level ?? null,
                'approver_name' => $approverName
            ];
        });
            
        return response()->json([
            'success' => true,
            'approvals' => $formattedApprovals->values()
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
            'notes' => 'nullable|string|max:500',
            'comment' => 'nullable|string|max:500', // Alias for notes
        ]);
        
        // Support both 'notes' and 'comment' parameters
        $notes = $request->input('notes') ?? $request->input('comment');
        if ($notes && !$request->has('notes')) {
            $request->merge(['notes' => $notes]);
        }
        
        $userId = auth()->id();
        
        // Get approval request
        $approvalRequest = DB::table('approval_requests')
            ->join('users', 'approval_requests.user_id', '=', 'users.id')
            ->where('approval_requests.id', $id)
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
        
        // Get absent request ID from approval request
        $absentRequest = DB::table('absent_requests')
            ->where('approval_request_id', $id)
            ->first();
        
        // Check if user is valid approver (either old flow or new flow)
        $isValidApprover = false;
        $currentApprovalFlow = null;
        
        if ($absentRequest) {
            // Check new flow: user must be the next approver in line
            $pendingFlows = DB::table('absent_request_approval_flows')
                ->where('absent_request_id', $absentRequest->id)
                ->where('status', 'PENDING')
                ->orderBy('approval_level')
                ->get();
            
            if (!$pendingFlows->isEmpty()) {
                $nextApprover = $pendingFlows->first();
                if ($nextApprover->approver_id == $userId) {
                    $isValidApprover = true;
                    $currentApprovalFlow = DB::table('absent_request_approval_flows')
                        ->where('id', $nextApprover->id)
                        ->first();
                }
            }
        }
        
        // Fallback to old flow check
        if (!$isValidApprover && $approvalRequest->approver_id == $userId) {
            $isValidApprover = true;
        }
        
        if (!$isValidApprover) {
            return response()->json([
                'success' => false,
                'message' => 'Anda bukan approver yang berhak untuk approval ini atau belum giliran Anda'
            ], 403);
        }
        
        try {
            DB::beginTransaction();
            
            if (!$currentApprovalFlow) {
                // Fallback to old flow (backward compatibility)
                DB::table('approval_requests')
                    ->where('id', $id)
                    ->update([
                        'status' => 'approved',
                        'approved_at' => now(),
                        'approval_notes' => $request->notes,
                        'updated_at' => now()
                    ]);
                
                DB::table('absent_requests')
                    ->where('approval_request_id', $id)
                    ->update([
                        'status' => 'supervisor_approved',
                        'approved_by' => $userId,
                        'approved_at' => now(),
                        'updated_at' => now()
                    ]);
                
                // Send notification to HRD (old flow)
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
            }
            
            // New flow: Update current approval flow
            DB::table('absent_request_approval_flows')
                ->where('id', $currentApprovalFlow->id)
                ->update([
                    'status' => 'APPROVED',
                    'approved_by' => $userId,
                    'approved_at' => now(),
                    'notes' => $request->notes,
                    'updated_at' => now()
                ]);
            
            // Check if there are more pending approvers
            $pendingFlows = DB::table('absent_request_approval_flows')
                ->where('absent_request_id', $absentRequest->id)
                ->where('status', 'PENDING')
                ->orderBy('approval_level')
                ->get();
            
            $approver = auth()->user();
            $totalLevels = DB::table('absent_request_approval_flows')
                ->where('absent_request_id', $absentRequest->id)
                ->count();
            $currentLevel = $currentApprovalFlow->approval_level;
            
            if ($pendingFlows->count() > 0) {
                // Still have pending approvers - send notification to next approver
                $nextApprover = $pendingFlows->first();
                $nextApproverUser = DB::table('users')
                    ->where('id', $nextApprover->approver_id)
                    ->first();
                
                if ($nextApproverUser) {
                    DB::table('notifications')->insert([
                        'user_id' => $nextApprover->approver_id,
                        'type' => 'leave_approval_request',
                        'message' => "Permohonan izin/cuti dari {$approvalRequest->user_name} untuk periode {$approvalRequest->date_from} - {$approvalRequest->date_to} membutuhkan persetujuan Anda (Level {$nextApprover->approval_level}/{$totalLevels}).",
                        'url' => config('app.url') . '/home',
                        'is_read' => 0,
                        'approval_id' => $id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
                
                // Update absent request status to supervisor_approved (still in approval process)
                DB::table('absent_requests')
                    ->where('id', $absentRequest->id)
                    ->update([
                        'status' => 'supervisor_approved',
                        'updated_at' => now()
                    ]);
                
                // Send notification to user
                DB::table('notifications')->insert([
                    'user_id' => $approvalRequest->user_id,
                    'type' => 'leave_approved',
                    'message' => "Permohonan izin/cuti Anda untuk periode {$approvalRequest->date_from} - {$approvalRequest->date_to} telah disetujui oleh {$approver->nama_lengkap} (Level {$currentLevel}/{$totalLevels}). Menunggu persetujuan approver berikutnya.",
                    'url' => config('app.url') . '/attendance',
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
            } else {
                // All approvers have approved - now send to HRD
                $hrdUsers = DB::table('users')
                    ->where('division_id', 6)
                    ->where('status', 'A')
                    ->select('id')
                    ->get();
                
                // Update approval request to set HRD status
                $hrdApprover = DB::table('users')
                    ->where('division_id', 6)
                    ->where('status', 'A')
                    ->first();
                
                if ($hrdApprover) {
                    DB::table('approval_requests')
                        ->where('id', $id)
                        ->update([
                            'hrd_approver_id' => $hrdApprover->id,
                            'hrd_status' => 'pending',
                            'updated_at' => now()
                        ]);
                }
                
                // Update absent request status
                DB::table('absent_requests')
                    ->where('id', $absentRequest->id)
                    ->update([
                        'status' => 'supervisor_approved', // All supervisors approved, waiting HRD
                        'updated_at' => now()
                    ]);
                
                // Send notification to all HRD users
                foreach ($hrdUsers as $hrdUser) {
                    DB::table('notifications')->insert([
                        'user_id' => $hrdUser->id,
                        'type' => 'leave_hrd_approval_request',
                        'message' => "Permohonan izin/cuti dari {$approvalRequest->user_name} untuk periode {$approvalRequest->date_from} - {$approvalRequest->date_to} telah disetujui oleh semua atasan dan membutuhkan persetujuan HRD Anda.",
                        'url' => config('app.url') . '/home',
                        'is_read' => 0,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
                
                // Send notification to user
                DB::table('notifications')->insert([
                    'user_id' => $approvalRequest->user_id,
                    'type' => 'leave_approved',
                    'message' => "Permohonan izin/cuti Anda untuk periode {$approvalRequest->date_from} - {$approvalRequest->date_to} telah disetujui oleh semua atasan. Menunggu persetujuan HRD.",
                    'url' => config('app.url') . '/attendance',
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
            \Log::error('Error approving request: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
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
            'notes' => 'nullable|string|max:500',
            'comment' => 'nullable|string|max:500', // Alias for notes
            'reason' => 'nullable|string|max:500', // Alias for notes
        ]);
        
        // Support 'notes', 'comment', and 'reason' parameters
        $notes = $request->input('notes') ?? $request->input('comment') ?? $request->input('reason');
        if ($notes && !$request->has('notes')) {
            $request->merge(['notes' => $notes]);
        }
        
        $userId = auth()->id();
        
        // Get approval request
        $approvalRequest = DB::table('approval_requests')
            ->join('users', 'approval_requests.user_id', '=', 'users.id')
            ->where('approval_requests.id', $id)
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
        
        // Get absent request ID from approval request
        $absentRequest = DB::table('absent_requests')
            ->where('approval_request_id', $id)
            ->first();
        
        // Check if user is valid approver (either old flow or new flow)
        $isValidApprover = false;
        $currentApprovalFlow = null;
        
        if ($absentRequest) {
            // Check new flow: user must be the next approver in line
            $pendingFlows = DB::table('absent_request_approval_flows')
                ->where('absent_request_id', $absentRequest->id)
                ->where('status', 'PENDING')
                ->orderBy('approval_level')
                ->get();
            
            if (!$pendingFlows->isEmpty()) {
                $nextApprover = $pendingFlows->first();
                if ($nextApprover->approver_id == $userId) {
                    $isValidApprover = true;
                    $currentApprovalFlow = DB::table('absent_request_approval_flows')
                        ->where('id', $nextApprover->id)
                        ->first();
                }
            }
        }
        
        // Fallback to old flow check
        if (!$isValidApprover && $approvalRequest->approver_id == $userId) {
            $isValidApprover = true;
        }
        
        if (!$isValidApprover) {
            return response()->json([
                'success' => false,
                'message' => 'Anda bukan approver yang berhak untuk reject ini atau belum giliran Anda'
            ], 403);
        }
        
        try {
            DB::beginTransaction();
            
            if ($absentRequest && $currentApprovalFlow) {
                // Update approval flow to rejected (new flow)
                DB::table('absent_request_approval_flows')
                    ->where('id', $currentApprovalFlow->id)
                    ->update([
                        'status' => 'REJECTED',
                        'approved_by' => $userId,
                        'rejected_at' => now(),
                        'rejection_reason' => $request->notes,
                        'updated_at' => now()
                    ]);
            }
            
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
            if ($absentRequest) {
                DB::table('absent_requests')
                    ->where('approval_request_id', $id)
                    ->update([
                        'status' => 'rejected',
                        'rejected_by' => $userId,
                        'rejected_at' => now(),
                        'rejection_reason' => $request->notes,
                        'updated_at' => now()
                    ]);
            }
            
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
            \Log::error('Error rejecting request: ' . $e->getMessage());
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
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: User not authenticated'
            ], 401);
        }
        
        $userId = $user->id;
        $limit = $request->get('limit', 10);
        
        // Superadmin: user dengan id_role = '5af56935b011a' bisa melihat semua approval
        $isSuperadmin = $user->id_role === '5af56935b011a';
        
        \Log::info('HRD Approvals check', [
            'user_id' => $userId,
            'id_role' => $user->id_role,
            'division_id' => $user->division_id,
            'isSuperadmin' => $isSuperadmin
        ]);
        
        // Only HRD users (division_id = 6) or superadmin can access this
        if (!$isSuperadmin && $user->division_id != 6) {
            \Log::warning('HRD Approvals: Access denied', [
                'user_id' => $userId,
                'id_role' => $user->id_role,
                'division_id' => $user->division_id,
                'isSuperadmin' => $isSuperadmin
            ]);
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
            // Get approver name - for HRD approvals, get any HRD user
            $approverName = null;
            $hrdUser = DB::table('users')
                ->where('division_id', 6)
                ->where('status', 'A')
                ->select('nama_lengkap')
                ->first();
            $approverName = $hrdUser ? $hrdUser->nama_lengkap : 'HRD';
            
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
                'duration_text' => $this->calculateDuration($approval->date_from, $approval->date_to),
                'approver_name' => $approverName
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
            ->leftJoin('leave_types', 'approval_requests.leave_type_id', '=', 'leave_types.id')
            ->where('approval_requests.id', $id)
            ->where('approval_requests.status', 'approved')
            ->where('approval_requests.hrd_status', 'pending')
            ->select([
                'approval_requests.*',
                'users.nama_lengkap as user_name',
                'users.id as user_id',
                'leave_types.name as leave_type_name'
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
            
            // After commit: deduct balance based on leave type
            try {
                $leaveTypeName = $approvalRequest->leave_type_name ? trim($approvalRequest->leave_type_name) : '';
                $start = Carbon::parse($approvalRequest->date_from)->startOfDay();
                $end = Carbon::parse($approvalRequest->date_to)->startOfDay();
                $days = $start->diffInDays($end) + 1;
                
                if (strcasecmp($leaveTypeName, 'Extra Off') === 0) {
                    // Deduct Extra Off balance
                    $extraOffService = app(ExtraOffService::class);
                    for ($i = 0; $i < $days; $i++) {
                        $useDate = $start->copy()->addDays($i)->format('Y-m-d');
                        try {
                            $extraOffService->useExtraOffDay($approvalRequest->user_id, $useDate, 'Extra Off leave approved HRD');
                        } catch (\Throwable $t) {
                            \Log::error('Failed to deduct extra off balance on HRD approval', [
                                'approval_request_id' => $id,
                                'user_id' => $approvalRequest->user_id,
                                'use_date' => $useDate,
                                'error' => $t->getMessage()
                            ]);
                        }
                    }
                } elseif (strcasecmp($leaveTypeName, 'Annual Leave') === 0) {
                    // Deduct Annual Leave balance
                    $leaveManagementService = app(LeaveManagementService::class);
                    try {
                        $result = $leaveManagementService->useLeave(
                            $approvalRequest->user_id,
                            $days,
                            "Annual Leave approved HRD for period {$approvalRequest->date_from} - {$approvalRequest->date_to}",
                            $userId
                        );
                        if (!$result['success']) {
                            \Log::error('Failed to deduct annual leave balance on HRD approval', [
                                'approval_request_id' => $id,
                                'user_id' => $approvalRequest->user_id,
                                'days' => $days,
                                'error' => $result['error'] ?? 'Unknown error'
                            ]);
                        }
                    } catch (\Throwable $t) {
                        \Log::error('Exception when deducting annual leave balance on HRD approval', [
                            'approval_request_id' => $id,
                            'user_id' => $approvalRequest->user_id,
                            'days' => $days,
                            'error' => $t->getMessage()
                        ]);
                    }
                } elseif (strcasecmp($leaveTypeName, 'Public Holiday') === 0) {
                    // Deduct Public Holiday balance
                    $holidayAttendanceService = app(HolidayAttendanceService::class);
                    $useDate = $start->format('Y-m-d'); // Use the first date as reference
                    try {
                        $holidayAttendanceService->usePublicHolidayBalanceAuto(
                            $approvalRequest->user_id,
                            $days,
                            $useDate,
                            'fifo' // First In First Out strategy
                        );
                    } catch (\Throwable $t) {
                        \Log::error('Failed to deduct public holiday balance on HRD approval', [
                            'approval_request_id' => $id,
                            'user_id' => $approvalRequest->user_id,
                            'days' => $days,
                            'use_date' => $useDate,
                            'error' => $t->getMessage()
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                \Log::error('Post-approval balance deduction error', [
                    'approval_request_id' => $id,
                    'leave_type' => $approvalRequest->leave_type_name ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }

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
