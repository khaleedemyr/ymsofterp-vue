<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Debugging absent_requests table...\n";

try {
    // Check all absent requests
    $allAbsentRequests = DB::table('absent_requests')
        ->join('users', 'absent_requests.user_id', '=', 'users.id')
        ->join('leave_types', 'absent_requests.leave_type_id', '=', 'leave_types.id')
        ->leftJoin('users as approvers', 'absent_requests.approved_by', '=', 'approvers.id')
        ->select([
            'absent_requests.id',
            'absent_requests.approval_request_id',
            'absent_requests.user_id',
            'absent_requests.status',
            'absent_requests.date_from',
            'absent_requests.date_to',
            'absent_requests.reason',
            'absent_requests.approved_by',
            'absent_requests.approved_at',
            'absent_requests.rejected_by',
            'absent_requests.rejected_at',
            'users.nama_lengkap as user_name',
            'users.id_jabatan as user_jabatan_id',
            'users.id_outlet as user_outlet_id',
            'approvers.nama_lengkap as approver_name',
            'leave_types.name as leave_type_name'
        ])
        ->orderBy('absent_requests.created_at', 'desc')
        ->get();
        
    echo "✓ Total absent requests: " . $allAbsentRequests->count() . "\n\n";
    
    foreach ($allAbsentRequests as $request) {
        echo "ID: {$request->id}\n";
        echo "Approval Request ID: {$request->approval_request_id}\n";
        echo "User: {$request->user_name} (ID: {$request->user_id})\n";
        echo "User Jabatan ID: {$request->user_jabatan_id}\n";
        echo "User Outlet ID: {$request->user_outlet_id}\n";
        echo "Status: {$request->status}\n";
        echo "Approved By: {$request->approved_by} ({$request->approver_name})\n";
        echo "Approved At: {$request->approved_at}\n";
        echo "Rejected By: {$request->rejected_by}\n";
        echo "Rejected At: {$request->rejected_at}\n";
        echo "Leave Type: {$request->leave_type_name}\n";
        echo "Date: {$request->date_from} to {$request->date_to}\n";
        echo "Reason: {$request->reason}\n";
        echo "---\n";
    }
    
    // Check pending absent requests
    $pendingAbsentRequests = $allAbsentRequests->where('status', 'pending');
    echo "\n✓ Pending absent requests: " . $pendingAbsentRequests->count() . "\n";
    
    foreach ($pendingAbsentRequests as $request) {
        echo "Pending ID: {$request->id} - User: {$request->user_name}\n";
    }
    
    // Check approved absent requests
    $approvedAbsentRequests = $allAbsentRequests->where('status', 'approved');
    echo "\n✓ Approved absent requests: " . $approvedAbsentRequests->count() . "\n";
    
    foreach ($approvedAbsentRequests as $request) {
        echo "Approved ID: {$request->id} - User: {$request->user_name} - Approved by: {$request->approver_name}\n";
    }
    
    // Check rejected absent requests
    $rejectedAbsentRequests = $allAbsentRequests->where('status', 'rejected');
    echo "\n✓ Rejected absent requests: " . $rejectedAbsentRequests->count() . "\n";
    
    foreach ($rejectedAbsentRequests as $request) {
        echo "Rejected ID: {$request->id} - User: {$request->user_name}\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
