<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\NotificationService;

class MaintenancePurchaseRequisitionController extends Controller
{
    public function approve(Request $request, $id)
    {
        $request->validate([
            'approval_type' => 'required|in:chief_engineering,coo,ceo',
            'status' => 'required|in:approved,rejected',
            'notes' => 'nullable|string'
        ]);

        $pr = DB::table('maintenance_purchase_requisitions')->find($id);
        if (!$pr) {
            return response()->json(['error' => 'PR not found'], 404);
        }

        $user = Auth::user();
        
        // Validasi hak akses approval
        if (!$this->hasApprovalAccess($user, $request->approval_type, $pr)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Get task and outlet details
        $task = DB::table('maintenance_tasks as mt')
            ->join('tbl_data_outlet as do', 'mt.id_outlet', '=', 'do.id_outlet')
            ->where('mt.id', $pr->task_id)
            ->select('mt.task_number', 'mt.title', 'do.nama_outlet')
            ->first();

        // Update approval status
        $updateData = [
            $request->approval_type . '_approval' => $request->status,
            $request->approval_type . '_approval_date' => now(),
            $request->approval_type . '_approval_by' => $user->id,
            $request->approval_type . '_approval_notes' => $request->notes
        ];

        if ($request->status === 'rejected') {
            $updateData['rejection_notes'] = $request->notes;
            $updateData['status'] = 'REJECTED';
        } else {
            // Cek status approval untuk update status PR
            $chiefApproved = $pr->chief_engineering_approval === 'approved';
            $cooApproved = $pr->coo_approval === 'approved';
            $ceoApproved = $pr->ceo_approval === 'approved';

            // Jika ada yang reject, status tetap REJECTED
            if ($pr->chief_engineering_approval === 'rejected' ||
                $pr->coo_approval === 'rejected' ||
                ($pr->total_amount >= 5000000 && $pr->ceo_approval === 'rejected')) {
                $updateData['status'] = 'REJECTED';
            }
            // Untuk PR < 5jt, jika COO approve maka status jadi APPROVED
            else if ($request->approval_type === 'coo' && 
                    $request->status === 'approved' && 
                    $pr->total_amount < 5000000) {
                $updateData['status'] = 'APPROVED';
            }
            // Untuk PR >= 5jt, jika CEO approve maka status jadi APPROVED
            else if ($request->approval_type === 'ceo' && 
                    $request->status === 'approved' && 
                    $pr->total_amount >= 5000000) {
                $updateData['status'] = 'APPROVED';
            }
        }

        DB::table('maintenance_purchase_requisitions')
            ->where('id', $id)
            ->update($updateData);

        // Get task members and comment users
        $taskMembers = DB::table('maintenance_members')
            ->where('task_id', $pr->task_id)
            ->pluck('user_id');

        $commentUsers = DB::table('maintenance_comments')
            ->where('task_id', $pr->task_id)
            ->pluck('user_id');

        $notifyUsers = $taskMembers->merge($commentUsers)->unique();

        // Prepare notification message
        $approverName = ucfirst(str_replace('_', ' ', $request->approval_type));
        $message = "PR {$pr->pr_number} untuk task {$task->task_number} - {$task->title} di outlet {$task->nama_outlet} telah {$request->status} oleh {$approverName}";

        // Send notification to all task members and comment users
        foreach ($notifyUsers as $userId) {
            DB::table('notifications')->insert([
                'user_id' => $userId,
                'task_id' => $pr->task_id,
                'type' => 'pr_approval',
                'message' => $message,
                'url' => config('app.url') . '/maintenance-order/' . $pr->task_id,
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Send notification to next approver
        if ($request->status === 'approved') {
            switch ($request->approval_type) {
                case 'chief_engineering':
                    // Notify COO and Secretary
                    $coos = DB::table('users')
                        ->where('id_jabatan', 151)
                        ->where('status', 'A')
                        ->pluck('id');

                    $secretaries = DB::table('users')
                        ->where('id_jabatan', 217)
                        ->where('status', 'A')
                        ->pluck('id');

                    $notifyUsers = $coos->merge($secretaries);

                    foreach ($notifyUsers as $userId) {
                        DB::table('notifications')->insert([
                            'user_id' => $userId,
                            'task_id' => $pr->task_id,
                            'type' => 'pr_approval',
                            'message' => "PR {$pr->pr_number} untuk task {$task->task_number} - {$task->title} di outlet {$task->nama_outlet} menunggu persetujuan Anda",
                            'url' => '/maintenance-order/' . $pr->task_id,
                            'is_read' => 0,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                    break;

                case 'coo':
                    if ($pr->total_amount >= 5000000) {
                        // Notify CEO and Secretary
                        $ceos = DB::table('users')
                            ->where('id_jabatan', 149)
                            ->where('status', 'A')
                            ->pluck('id');

                        $secretaries = DB::table('users')
                            ->where('id_jabatan', 217)
                            ->where('status', 'A')
                            ->pluck('id');

                        $notifyUsers = $ceos->merge($secretaries);

                        foreach ($notifyUsers as $userId) {
                            DB::table('notifications')->insert([
                                'user_id' => $userId,
                                'task_id' => $pr->task_id,
                                'type' => 'pr_approval',
                                'message' => "PR {$pr->pr_number} untuk task {$task->task_number} - {$task->title} di outlet {$task->nama_outlet} menunggu persetujuan Anda",
                                'url' => '/maintenance-order/' . $pr->task_id,
                                'is_read' => 0,
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                        }
                    }
                    break;
            }
        }

        // Log aktivitas
        DB::table('maintenance_activity_logs')->insert([
            'task_id' => $pr->task_id,
            'user_id' => $user->id,
            'activity_type' => 'PR_APPROVED',
            'description' => ucfirst(str_replace('_', ' ', $request->approval_type)) . ' ' . $request->status . ' PR ' . $pr->pr_number,
            'created_at' => now()
        ]);

        return response()->json(['success' => true]);
    }

    private function hasApprovalAccess($user, $approvalType, $pr)
    {
        // Superadmin dan sekretaris bisa approve semua level
        if (($user->id_role === '5af56935b011a' || $user->id_jabatan === 217) && $user->status === 'A') {
            return true;
        }

        // Validasi berdasarkan level approval
        switch ($approvalType) {
            case 'chief_engineering':
                return ($user->id_jabatan === 165 || $user->id_jabatan === 262) && $user->status === 'A';
            
            case 'coo':
                return $user->id_jabatan === 151 && $user->status === 'A';
            
            case 'ceo':
                // CEO hanya bisa approve jika total_amount >= 5 juta
                if ($pr->total_amount < 5000000) {
                    return false;
                }
                return $user->id_jabatan === 149 && $user->status === 'A';
            
            default:
                return false;
        }
    }

    public function getApprovalStatus($id)
    {
        $pr = DB::table('maintenance_purchase_requisitions')->find($id);
        if (!$pr) {
            return response()->json(['error' => 'PR not found'], 404);
        }

        $status = [
            'chief_engineering' => [
                'status' => $pr->chief_engineering_approval,
                'date' => $pr->chief_engineering_approval_date,
                'by' => $pr->chief_engineering_approval_by,
                'notes' => $pr->chief_engineering_approval_notes
            ],
            'coo' => [
                'status' => $pr->coo_approval,
                'date' => $pr->coo_approval_date,
                'by' => $pr->coo_approval_by,
                'notes' => $pr->coo_approval_notes
            ]
        ];

        // Tambahkan status CEO jika total_amount >= 5 juta
        if ($pr->total_amount >= 5000000) {
            $status['ceo'] = [
                'status' => $pr->ceo_approval,
                'date' => $pr->ceo_approval_date,
                'by' => $pr->ceo_approval_by,
                'notes' => $pr->ceo_approval_notes
            ];
        }

        return response()->json($status);
    }

    public function preview($prId)
    {
        // Get PR data with task and outlet info
        $pr = DB::table('maintenance_purchase_requisitions as pr')
            ->join('maintenance_tasks as mt', 'pr.task_id', '=', 'mt.id')
            ->join('tbl_data_outlet as do', 'mt.id_outlet', '=', 'do.id_outlet')
            ->where('pr.id', $prId)
            ->select('pr.*', 'mt.task_number', 'mt.description as task_description', 'pr.description as pr_description', 'do.nama_outlet')
            ->first();

        if (!$pr) {
            abort(404);
        }

        // Get PR items with unit info
        $items = DB::table('maintenance_purchase_requisition_items as pri')
            ->leftJoin('units as u', 'pri.unit_id', '=', 'u.id')
            ->where('pri.pr_id', $prId)
            ->select('pri.*', 'u.name as unit_name')
            ->get();

        // Get creator info
        $creator = DB::table('users as u')
            ->join('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->where('u.id', $pr->created_by)
            ->select('u.id', 'u.nama_lengkap', 'u.signature_path', 'j.nama_jabatan')
            ->first();

        // Get signatures of officials and approvers
        $signatures = [
            'chief_engineering' => [
                'official' => null,
                'approver' => null
            ],
            'coo' => [
                'official' => null,
                'approver' => null
            ],
            'ceo' => [
                'official' => null,
                'approver' => null
            ]
        ];

        // Get chief engineering official
        $chiefEngineering = DB::table('users as u')
            ->join('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->where('u.id_jabatan', 165) // Chief Engineering
            ->where('u.status', 'A')
            ->select('u.id', 'u.nama_lengkap', 'u.signature_path', 'j.nama_jabatan')
            ->first();

        if ($chiefEngineering) {
            $signatures['chief_engineering']['official'] = $chiefEngineering;
            
            // Get chief engineering approver if different from official
            if ($pr->chief_engineering_approval_by && $pr->chief_engineering_approval_by != $chiefEngineering->id) {
                $signatures['chief_engineering']['approver'] = DB::table('users as u')
                    ->join('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                    ->where('u.id', $pr->chief_engineering_approval_by)
                    ->select('u.id', 'u.nama_lengkap', 'j.nama_jabatan')
                    ->first();
            }
        }

        // Get COO official
        $coo = DB::table('users as u')
            ->join('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->where('u.id_jabatan', 151) // COO
            ->where('u.status', 'A')
            ->select('u.id', 'u.nama_lengkap', 'u.signature_path', 'j.nama_jabatan')
            ->first();

        if ($coo) {
            $signatures['coo']['official'] = $coo;
            
            // Get COO approver if different from official
            if ($pr->coo_approval_by && $pr->coo_approval_by != $coo->id) {
                $signatures['coo']['approver'] = DB::table('users as u')
                    ->join('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                    ->where('u.id', $pr->coo_approval_by)
                    ->select('u.id', 'u.nama_lengkap', 'j.nama_jabatan')
                    ->first();
            }
        }

        // Get CEO official
        $ceo = DB::table('users as u')
            ->join('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->where('u.id_jabatan', 149) // CEO
            ->where('u.status', 'A')
            ->select('u.id', 'u.nama_lengkap', 'u.signature_path', 'j.nama_jabatan')
            ->first();

        if ($ceo) {
            $signatures['ceo']['official'] = $ceo;
            
            // Get CEO approver if different from official
            if ($pr->ceo_approval_by && $pr->ceo_approval_by != $ceo->id) {
                $signatures['ceo']['approver'] = DB::table('users as u')
                    ->join('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                    ->where('u.id', $pr->ceo_approval_by)
                    ->select('u.id', 'u.nama_lengkap', 'j.nama_jabatan')
                    ->first();
            }
        }

        return view('maintenance.pr-preview', compact('pr', 'items', 'signatures', 'creator'));
    }
} 