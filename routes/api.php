<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MaintenanceOrderController;
use App\Http\Controllers\MaintenanceLabelController;
use App\Http\Controllers\MaintenancePriorityController;
use App\Http\Controllers\MaintenanceCommentController;
use App\Http\Controllers\ActionPlanController;
use App\Http\Controllers\RetailController;
use App\Http\Controllers\Api\MaintenancePurchaseOrderController;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\MaintenancePurchaseOrderInvoiceController;
use App\Http\Controllers\MaintenancePurchaseOrderReceiveController;
use App\Http\Controllers\Api\MaintenanceEvidenceController;
use App\Http\Controllers\MaintenanceTaskController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\QuoteController;

// Endpoint API untuk Kanban Maintenance Order
Route::get('/outlet', [MaintenanceOrderController::class, 'getOutlets']);
Route::get('/ruko', [MaintenanceOrderController::class, 'getRukos']);
Route::get('/maintenance-order', [MaintenanceOrderController::class, 'index']);
Route::patch('/maintenance-order/{id}', [MaintenanceOrderController::class, 'updateStatus']);
Route::get('/maintenance-labels', [MaintenanceLabelController::class, 'index']);
Route::get('/maintenance-priorities', [MaintenancePriorityController::class, 'index']);
Route::middleware(['auth:web'])->group(function () {
    Route::post('/maintenance-order', [MaintenanceOrderController::class, 'store']);
});
Route::delete('/maintenance-tasks/{id}', [MaintenanceTaskController::class, 'destroy']);
// Maintenance Comment Routes
Route::get('/maintenance-comments/{taskId}', [MaintenanceCommentController::class, 'index']);
Route::get('/maintenance-comments/{taskId}/count', [MaintenanceCommentController::class, 'count']);
Route::post('/maintenance-comments', [MaintenanceCommentController::class, 'store']);
Route::delete('/maintenance-comments/{id}', [MaintenanceCommentController::class, 'destroy']);

// New endpoints
Route::get('/assignable-users', [MaintenanceOrderController::class, 'assignableUsers']);
Route::get('/maintenance-members/{taskId}', [MaintenanceOrderController::class, 'getTaskMembers']);
Route::post('/assign-members', [MaintenanceOrderController::class, 'assignMembers']);

// Action Plan Routes
Route::post('/action-plans', [ActionPlanController::class, 'store']);
Route::get('/action-plans/task/{taskId}', [ActionPlanController::class, 'getByTask']);
Route::delete('/action-plans/media/{mediaId}', [ActionPlanController::class, 'deleteMedia']); 

// Retail Routes
Route::post('/retail', [RetailController::class, 'store']);
Route::get('/retail/task/{taskId}', [RetailController::class, 'getByTask']);
Route::delete('/retail/image/{imageId}/{type}', [RetailController::class, 'deleteImage']); 

// Purchase Order Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('maintenance-tasks/{taskId}/purchase-orders')->group(function () {
        Route::get('/', [MaintenancePurchaseOrderController::class, 'index']);
        Route::post('/', [MaintenancePurchaseOrderController::class, 'store']);
        Route::get('/{poId}', [MaintenancePurchaseOrderController::class, 'show']);
        Route::put('/{poId}', [MaintenancePurchaseOrderController::class, 'update']);
        Route::delete('/{poId}', [MaintenancePurchaseOrderController::class, 'destroy']);
    });
    
    // Maintenance PO routes
    Route::post('/maintenance-tasks/{taskId}/purchase-orders/{poId}/approve', [MaintenancePurchaseOrderController::class, 'approve']);

    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);

    // Evidence routes
    Route::post('/maintenance-evidence', [MaintenanceEvidenceController::class, 'store']);
    Route::get('/maintenance-evidence/{taskId}', [MaintenanceEvidenceController::class, 'show']);
}); 

// Supplier Routes
Route::get('/suppliers', function () {
    return response()->json(DB::table('suppliers')
        ->where('status', 'active')
        ->select('id', 'name')
        ->orderBy('name')
        ->get()
    );
}); 

// Purchase Order Invoice Routes
Route::get('/purchase-orders/{poId}/invoices', [MaintenancePurchaseOrderInvoiceController::class, 'index']);
Route::post('/purchase-orders/{poId}/invoices', [MaintenancePurchaseOrderInvoiceController::class, 'store']);
Route::post('/purchase-orders/{poId}/receive', [MaintenancePurchaseOrderReceiveController::class, 'store']);
Route::get('/purchase-orders/{poId}/receives', [\App\Http\Controllers\MaintenancePurchaseOrderReceiveController::class, 'index']); 

// Dashboard Routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard/maintenance', [DashboardController::class, 'index']);
    Route::get('/dashboard/maintenance/filter', [DashboardController::class, 'filter']);
    Route::get('/dashboard/maintenance/report', [\App\Http\Controllers\DashboardController::class, 'exportExcel']);
    Route::get('/maintenance-tasks/{id}/detail', [\App\Http\Controllers\DashboardController::class, 'taskDetail']);
    Route::get('/maintenance-tasks/all', [\App\Http\Controllers\DashboardController::class, 'allTasks']);
    Route::get('/maintenance-tasks/done', [\App\Http\Controllers\DashboardController::class, 'allDoneTasks']);
    Route::get('/maintenance-tasks/leaderboard-done', [\App\Http\Controllers\DashboardController::class, 'doneTasksLeaderboard']);
    Route::get('/maintenance-po-latest', [\App\Http\Controllers\DashboardController::class, 'polatestWithDetail']);
    Route::get('/maintenance-pr-latest', [\App\Http\Controllers\DashboardController::class, 'allPRWithDetail']);
    Route::get('/retail-latest', [\App\Http\Controllers\DashboardController::class, 'allRetailWithDetail']);
    Route::get('/activity-latest', [\App\Http\Controllers\DashboardController::class, 'allActivityWithDetail']);
    Route::get('/overdue-tasks/all', [\App\Http\Controllers\DashboardController::class, 'allOverdueTasks']);
    Route::get('/dashboard/task-completion-stats', [\App\Http\Controllers\DashboardController::class, 'taskCompletionStats']);
    Route::get('/dashboard/task-by-due-date-stats', [\App\Http\Controllers\DashboardController::class, 'taskByDueDateStats']);
}); 

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->get('/user', [AuthController::class, 'user']);
});

Route::get('/quotes/{dayOfYear}', [QuoteController::class, 'getQuoteByDay']);
