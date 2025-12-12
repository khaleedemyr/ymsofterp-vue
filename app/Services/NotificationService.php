<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Create a notification and trigger push notification via NotificationObserver
     * 
     * This is a drop-in replacement for DB::table('notifications')->insert()
     * It uses Eloquent Notification::create() which triggers NotificationObserver
     * for automatic FCM push notification to mobile apps
     * 
     * @param array $data Notification data with keys:
     *   - user_id (required): The user who will receive the notification
     *   - task_id (optional): Related task ID
     *   - approval_id (optional): Related approval ID
     *   - type (optional): Notification type
     *   - title (optional): Notification title (will be generated if not provided)
     *   - message (required): Notification message
     *   - url (optional): URL to open when notification is clicked
     *   - is_read (optional): Default 0
     * 
     * @return Notification|null Created notification instance or null on failure
     */
    public static function create(array $data)
    {
        try {
            // Validate required fields
            if (empty($data['user_id'])) {
                Log::warning('NotificationService: Missing user_id', ['data' => $data]);
                return null;
            }

            if (empty($data['message'])) {
                Log::warning('NotificationService: Missing message', ['data' => $data]);
                return null;
            }

            // Generate title if not provided
            if (empty($data['title'])) {
                $data['title'] = self::generateTitle($data);
            }

            // Ensure is_read is set (default 0)
            if (!isset($data['is_read'])) {
                $data['is_read'] = 0;
            }

            // Create notification using Eloquent (this triggers NotificationObserver)
            $notification = Notification::create([
                'user_id' => $data['user_id'],
                'task_id' => $data['task_id'] ?? null,
                'approval_id' => $data['approval_id'] ?? null,
                'type' => $data['type'] ?? null,
                'title' => $data['title'],
                'message' => $data['message'],
                'url' => $data['url'] ?? null,
                'is_read' => $data['is_read'],
            ]);

            Log::info('NotificationService: Notification created successfully', [
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'type' => $notification->type,
            ]);

            return $notification;

        } catch (\Exception $e) {
            Log::error('NotificationService: Failed to create notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data,
            ]);
            return null;
        }
    }

    /**
     * Generate notification title based on type or message
     */
    private static function generateTitle(array $data): string
    {
        // Try to generate title from type
        if (!empty($data['type'])) {
            $typeTitles = [
                // Approval types
                'outlet_stock_adjustment_approval' => 'Outlet Stock Adjustment Approval',
                'stock_adjustment_approval' => 'Stock Adjustment Approval',
                'purchase_requisition_approval' => 'Purchase Requisition Approval',
                'purchase_order_approval' => 'Purchase Order Approval',
                'leave_approval' => 'Leave Approval',
                'leave_approval_request' => 'Leave Approval Request',
                'leave_supervisor_approved' => 'Leave Supervisor Approved',
                'leave_hrd_approval_request' => 'Leave HRD Approval Request',
                'leave_rejected' => 'Leave Rejected',
                'leave_cancelled' => 'Leave Cancelled',
                'hrd_approval' => 'HRD Approval',
                'correction_approval' => 'Attendance Correction Approval',
                'stock_opname_approval' => 'Stock Opname Approval',
                'category_cost_approval' => 'Category Cost Approval',
                'contra_bon_approval' => 'Contra Bon Approval',
                'non_food_payment_approval' => 'Non Food Payment Approval',
                'food_payment_approval' => 'Food Payment Approval',
                'employee_movement_approval' => 'Employee Movement Approval',
                'employee_movement_completed' => 'Employee Movement Completed',
                'employee_movement_rejected' => 'Employee Movement Rejected',
                'employee_movement_executed' => 'Employee Movement Executed',
                'employee_resignation_approval' => 'Employee Resignation Approval',
                'floor_order_approval' => 'Floor Order Approval',
                'outlet_internal_use_waste_approval' => 'Outlet Internal Use Waste Approval',
                'outlet_rejection' => 'Outlet Rejection',
                'outlet_transfer_approval' => 'Outlet Transfer Approval',
                'outlet_transfer' => 'Outlet Transfer',
                'stock_opname_approval_request' => 'Stock Opname Approval Request',
                'warehouse_stock_opname_approval_request' => 'Warehouse Stock Opname Approval Request',
                'purchase_requisition_comment' => 'Purchase Requisition Comment',
                'schedule' => 'Schedule',
                'schedule_correction_approval' => 'Schedule Correction Approval',
                'manual_attendance' => 'Manual Attendance',
                'manual_attendance_approval' => 'Manual Attendance Approval',
                'attendance' => 'Attendance',
                'attendance_correction_approval' => 'Attendance Correction Approval',
                'correction_approved' => 'Correction Approved',
                'correction_rejected' => 'Correction Rejected',
                'retail_food' => 'Retail Food',
                'PR Foods' => 'PR Foods',
                'RO Supplier' => 'RO Supplier',
                'new_device_login' => 'New Device Login',
                'welcome' => 'Welcome',
                
                // Maintenance/Task types
                'task_created' => 'Task Created',
                'task_status_changed' => 'Task Status Changed',
                'task_deleted' => 'Task Deleted',
                'assign_member' => 'Assign Member',
                'pr_created' => 'Purchase Requisition Created',
                'pr_approval' => 'Purchase Requisition Approval',
                'pr_approval_request' => 'Purchase Requisition Approval Request',
                'pr_approved_for_po' => 'PR Approved for PO',
                'pr_deleted' => 'Purchase Requisition Deleted',
                'po_approved' => 'Purchase Order Approved',
                'po_approval_request' => 'Purchase Order Approval Request',
                'po_approved_for_payment' => 'PO Approved for Payment',
                'po_rejected' => 'Purchase Order Rejected',
                'bidding_completed' => 'Bidding Completed',
                'good_receive_upload' => 'Good Receive Uploaded',
                'upload_invoice' => 'Invoice Uploaded',
                'add_evidence' => 'Evidence Added',
                'comment' => 'New Comment',
                'action_plan_created' => 'Action Plan Created',
                
                // Ticket types
                'ticket_created' => 'Ticket Created',
                'ticket_comment' => 'Ticket Comment',
                
                // Training/LMS types
                'training_invitation' => 'Training Invitation',
                'trainer_invitation' => 'Trainer Invitation',
                'participant_invitation' => 'Participant Invitation',
                'trainer_assignment' => 'Trainer Assignment',
                
                // Coaching types
                'coaching_approval_required' => 'Coaching Approval Required',
                'coaching_created' => 'Coaching Created',
                'coaching_approved' => 'Coaching Approved',
                'coaching_completed' => 'Coaching Completed',
                'coaching_rejected' => 'Coaching Rejected',
                
                // Other types
                'reminder_created' => 'Reminder Created',
                'daily_report_comment' => 'Daily Report Comment',
                'live_support_conversation' => 'Live Support Conversation',
                'live_support_chat' => 'Live Support Chat',
                'enroll_test' => 'Enroll Test',
                'retail_created' => 'Retail Created',
            ];

            if (isset($typeTitles[$data['type']])) {
                return $typeTitles[$data['type']];
            }
        }

        // Fallback: Use first line of message or generic title
        $message = $data['message'] ?? '';
        $firstLine = explode("\n", $message)[0];
        if (strlen($firstLine) > 50) {
            return substr($firstLine, 0, 47) . '...';
        }
        
        return $firstLine ?: 'New Notification';
    }

    /**
     * Create multiple notifications at once
     * 
     * @param array $notifications Array of notification data arrays
     * @return array Array of created Notification instances
     */
    public static function createMany(array $notifications): array
    {
        $created = [];
        foreach ($notifications as $data) {
            $notification = self::create($data);
            if ($notification) {
                $created[] = $notification;
            }
        }
        return $created;
    }

    /**
     * Drop-in replacement for DB::table('notifications')->insert()
     * 
     * This method accepts the same format as DB::table('notifications')->insert()
     * but uses Eloquent to trigger NotificationObserver for push notifications
     * 
     * Supports both single and batch insert:
     *   // Single notification:
     *   NotificationService::insert([...]);
     * 
     *   // Batch notifications:
     *   NotificationService::insert([[...], [...]]);
     * 
     * @param array $data Single notification array or array of notification arrays
     * @return int|bool|null 
     *   - For single insert: Created notification ID or null on failure
     *   - For batch insert: true on success, false on failure
     */
    public static function insert(array $data)
    {
        // Check if it's a batch insert (array of arrays)
        if (!empty($data) && isset($data[0]) && is_array($data[0])) {
            // Batch insert
            $created = self::createMany($data);
            return !empty($created);
        }
        
        // Single insert
        $notification = self::create($data);
        return $notification ? $notification->id : null;
    }

    /**
     * Drop-in replacement for DB::table('notifications')->insertGetId()
     * 
     * Creates a single notification and returns its ID.
     * Note: This method only supports single insert, not batch.
     * 
     * @param array $data Single notification data array
     * @return int|null Created notification ID or null on failure
     */
    public static function insertGetId(array $data)
    {
        // Ensure it's a single notification (not batch)
        if (!empty($data) && isset($data[0]) && is_array($data[0])) {
            Log::warning('NotificationService: insertGetId() called with batch data, only first notification will be created', ['data' => $data]);
            $data = $data[0];
        }
        
        $notification = self::create($data);
        return $notification ? $notification->id : null;
    }
}

