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
use App\Http\Controllers\PurchaseOrderFoodsController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\FOScheduleController;
use App\Http\Controllers\ItemScheduleController;
use App\Http\Controllers\Api\GoodReceiveController;
use App\Http\Controllers\Api\ItemController as ApiItemController;
use App\Models\FoodGoodReceive;
use App\Http\Controllers\Mobile\RegisterController;
use App\Http\Controllers\DeliveryOrderController;
use App\Http\Controllers\InvestorController;
use App\Http\Controllers\ReportDailyOutletRevenueController;
use App\Http\Controllers\ReportWeeklyOutletFbRevenueController;
use App\Http\Controllers\ReportDailyRevenueForecastController;
use App\Http\Controllers\ReportMonthlyFbRevenuePerformanceController;
use App\Http\Controllers\OutletController;


// Test route for approvers
Route::get('/test-po-ops-approvers', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'getApprovers']);

// Endpoint API untuk Kanban Maintenance Order
Route::get('/maintenance-outlet', [MaintenanceOrderController::class, 'getOutlets']);
Route::get('/ruko', [MaintenanceOrderController::class, 'getRukos']);
Route::get('/ruko/test', function() {
    try {
        $rukos = DB::table('tbl_data_ruko')->get();
        return response()->json([
            'success' => true,
            'count' => $rukos->count(),
            'data' => $rukos,
            'table_exists' => DB::getSchemaBuilder()->hasTable('tbl_data_ruko'),
            'columns' => DB::getSchemaBuilder()->getColumnListing('tbl_data_ruko')
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'table_exists' => DB::getSchemaBuilder()->hasTable('tbl_data_ruko')
        ]);
    }
});
Route::get('/maintenance-order', [MaintenanceOrderController::class, 'index']);
Route::patch('/maintenance-order/{id}', [MaintenanceOrderController::class, 'updateStatus']);

// Endpoint API untuk List View Maintenance Order
Route::get('/maintenance-order-list', [MaintenanceOrderController::class, 'listAll']);

// Endpoint API untuk Get Assignable Users (harus di atas route dengan parameter)
Route::get('/maintenance-order/assignable-users', [MaintenanceOrderController::class, 'getAssignableUsers']);

// Endpoint API untuk Maintenance Order Detail
Route::get('/maintenance-order/{id}', [MaintenanceOrderController::class, 'show']);

// Endpoint API untuk Maintenance Order Update
Route::put('/maintenance-order/{id}', [MaintenanceOrderController::class, 'update']);

// Endpoint API untuk Assign Member
Route::post('/maintenance-order/{id}/assign-members', [MaintenanceOrderController::class, 'assignMembers']);

// Endpoint API untuk Remove Member
Route::delete('/maintenance-order/{id}/remove-member/{memberId}', [MaintenanceOrderController::class, 'removeMember']);

// Endpoint API untuk Media
Route::get('/maintenance-order/{id}/media', [MaintenanceOrderController::class, 'getMedia']);

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
Route::middleware('auth')->group(function () {
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
    Route::get('/dashboard/maintenance/task-per-member', [\App\Http\Controllers\DashboardController::class, 'taskCountPerMember']);
}); 

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::middleware('auth')->get('/user', [AuthController::class, 'user']);
});

Route::get('/quotes/{dayOfYear}', [QuoteController::class, 'getQuoteByDay']);

Route::get('items/last-price', [\App\Http\Controllers\PurchaseOrderFoodsController::class, 'getLastPrice']);

Route::get('/items/search-for-warehouse-transfer', [ItemController::class, 'searchForWarehouseTransfer']);
Route::get('/items/search-for-outlet-transfer', [ItemController::class, 'searchForOutletTransfer']);
Route::get('/items/search-for-internal-warehouse-transfer', [ItemController::class, 'searchForInternalWarehouseTransfer']);
Route::get('/warehouse-outlets/by-outlet', function (Request $request) {
    $outlet_id = $request->get('outlet_id');
    
    $warehouse_outlets = DB::table('warehouse_outlets')
        ->where('outlet_id', $outlet_id)
        ->where('status', 'active')
        ->select('id', 'name', 'outlet_id')
        ->orderBy('name')
        ->get();
    
    return response()->json($warehouse_outlets);
});

Route::get('/warehouse-divisions/by-warehouse', function (Request $request) {
    $warehouse_id = $request->get('warehouse_id');
    
    $warehouse_divisions = DB::table('warehouse_division')
        ->where('warehouse_id', $warehouse_id)
        ->where('status', 'active')
        ->select('id', 'name', 'warehouse_id')
        ->orderBy('name')
        ->get();
    
    return response()->json($warehouse_divisions);
});

Route::get('/fo-schedules/check', [\App\Http\Controllers\FOScheduleController::class, 'check']);

Route::get('fo-schedules/outlet-schedules', [\App\Http\Controllers\FOScheduleController::class, 'getOutletSchedules']);

Route::get('/items/by-fo-khusus', [App\Http\Controllers\ItemController::class, 'getByFOKhusus']);

Route::get('/item-schedules/today', [ItemScheduleController::class, 'getTodaySchedules']);

Route::get('/good-receives/autocomplete', [GoodReceiveController::class, 'autocomplete']);
Route::get('/good-receives/{id}/items', function ($id) {
    $items = DB::table('food_good_receive_items as gri')
        ->join('items as i', 'gri.item_id', '=', 'i.id')
        ->join('units as u', 'gri.unit_id', '=', 'u.id')
        ->join('purchase_order_food_items as poi', 'gri.po_item_id', '=', 'poi.id')
        ->select(
            'gri.id',
            'gri.item_id',
            'i.name',
            'i.sku',
            'gri.qty_received',
            'gri.used_qty as sisa_qty',
            'u.name as unit',
            'gri.unit_id',
            'poi.price as po_price',
            'poi.unit_id as po_unit_id',
            'i.small_unit_id',
            'i.small_conversion_qty'
        )
        ->where('gri.good_receive_id', $id)
        ->get();

    return $items;
});

Route::get('/items/autocomplete-pcs', [ItemController::class, 'autocompletePcs']);

Route::post('/mobile/register', [RegisterController::class, 'register']);
Route::post('/mobile/login', [\App\Http\Controllers\Api\MobileAuthController::class, 'login']);

// Endpoint untuk dropdown jabatan
Route::get('/jabatan', function () {
    return response()->json(DB::table('tbl_data_jabatan')
        ->where('status', 'A')
        ->select('id_jabatan', 'nama_jabatan')
        ->orderBy('nama_jabatan')
        ->get()
    );
});

Route::get('/quotes/of-the-day', [QuoteController::class, 'getQuoteByDayOfYear']);

Route::get('/outlet-inventory/stock', function (Request $request) {
    $item_id = $request->get('item_id');
    $warehouse_outlet_id = $request->get('warehouse_outlet_id');
    
    // Get stock with item and unit information
    $stock = DB::table('outlet_food_inventory_stocks as ofis')
        ->join('outlet_food_inventory_items as ofii', 'ofis.inventory_item_id', '=', 'ofii.id')
        ->join('items as i', 'ofii.item_id', '=', 'i.id')
        ->leftJoin('units as u_small', 'i.small_unit_id', '=', 'u_small.id')
        ->leftJoin('units as u_medium', 'i.medium_unit_id', '=', 'u_medium.id')
        ->leftJoin('units as u_large', 'i.large_unit_id', '=', 'u_large.id')
        ->where('ofii.item_id', $item_id)
        ->where('ofis.warehouse_outlet_id', $warehouse_outlet_id)
        ->select(
            'ofis.qty_small',
            'ofis.qty_medium', 
            'ofis.qty_large',
            'ofis.last_cost_small',
            'ofis.last_cost_medium',
            'ofis.last_cost_large',
            'u_small.name as unit_small',
            'u_medium.name as unit_medium',
            'u_large.name as unit_large',
            'i.small_conversion_qty',
            'i.medium_conversion_qty'
        )
        ->first();
    
    if (!$stock) {
        return response()->json([
            'qty_small' => 0,
            'qty_medium' => 0,
            'qty_large' => 0,
            'last_cost_small' => 0,
            'last_cost_medium' => 0,
            'last_cost_large' => 0,
            'unit_small' => '',
            'unit_medium' => '',
            'unit_large' => '',
            'small_conversion_qty' => 1,
            'medium_conversion_qty' => 1
        ]);
    }
    
    return response()->json($stock);
});

Route::get('/items', [ItemController::class, 'apiIndex']);

Route::get('/delivery-order/{id}/struk', [\App\Http\Controllers\DeliveryOrderController::class, 'strukData']);
Route::get('/food-good-receive/{id}/struk', [\App\Http\Controllers\FoodGoodReceiveController::class, 'strukData']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/investors', [InvestorController::class, 'index']);
    Route::post('/investors', [InvestorController::class, 'store']);
    Route::put('/investors/{id}', [InvestorController::class, 'update']);
    Route::delete('/investors/{id}', [InvestorController::class, 'destroy']);
});

// Report routes
Route::middleware(['auth:web'])->group(function () {
    Route::get('/report/daily-outlet-revenue', [\App\Http\Controllers\ReportDailyOutletRevenueController::class, 'index']);
    Route::get('/report/weekly-outlet-fb-revenue', [\App\Http\Controllers\ReportWeeklyOutletFbRevenueController3::class, 'index']);
    Route::post('/report/weekly-outlet-fb-revenue/budget', [\App\Http\Controllers\ReportWeeklyOutletFbRevenueController3::class, 'storeBudget']);
    Route::get('/report/daily-revenue-forecast', [\App\Http\Controllers\ReportDailyRevenueForecastController::class, 'index']);
    Route::post('/report/daily-revenue-forecast/settings', [\App\Http\Controllers\ReportDailyRevenueForecastController::class, 'storeForecastSettings']);
    Route::get('/report/monthly-fb-revenue-performance', [\App\Http\Controllers\ReportMonthlyFbRevenuePerformanceController::class, 'index']);
});

// Investor outlet routes (must be before /outlet routes)
Route::get('/outlets/investor', [InvestorController::class, 'outlets'])->middleware(['auth:sanctum']);

// Outlet routes
Route::get('/outlet', [OutletController::class, 'index']);
Route::get('/outlet/active', [OutletController::class, 'getActiveOutlets']);
Route::get('/outlet/{id}', [OutletController::class, 'show']);
Route::post('/outlet', [OutletController::class, 'store']);
Route::put('/outlet/{id}', [OutletController::class, 'update']);
Route::delete('/outlet/{id}', [OutletController::class, 'destroy']);

// PR Foods routes
Route::get('/pr-foods/available', [PurchaseOrderFoodsController::class, 'getAvailablePR']);
Route::post('/pr-foods/items', [PurchaseOrderFoodsController::class, 'getPRItems']);

// Purchase Order Ops API routes
Route::get('/pr-ops/available', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'getAvailablePR']);

// PR Tracking Report API
Route::get('/pr-tracking-report', [\App\Http\Controllers\PurchaseRequisitionController::class, 'getPRTrackingReport']);

// Organization Chart API routes
Route::get('/organization-chart', [\App\Http\Controllers\OrganizationChartController::class, 'getOrganizationData']);
Route::get('/organization-chart/outlets', [\App\Http\Controllers\OrganizationChartController::class, 'getOutlets']);
Route::get('/organization-chart/outlet/{outletId}', [\App\Http\Controllers\OrganizationChartController::class, 'getOrganizationByOutlet']);
Route::get('/organization-chart/debug', [\App\Http\Controllers\OrganizationChartController::class, 'debugData']);
Route::get('/divisions', [\App\Http\Controllers\PurchaseRequisitionController::class, 'getDivisions']);

// Payment API routes

// Daily Report API routes
Route::get('/daily-report/summary-rating', [\App\Http\Controllers\DailyReportController::class, 'getSummaryRating']);
Route::get('/daily-report/regions', [\App\Http\Controllers\DailyReportController::class, 'getRegions']);
Route::get('/daily-report/department-ratings', [\App\Http\Controllers\DailyReportController::class, 'getDepartmentRatings']);

// User Profile Upload Routes
Route::middleware(['auth'])->group(function () {
    Route::post('/user/upload-avatar', [\App\Http\Controllers\UserController::class, 'uploadAvatar'])->name('api.user.upload-avatar');
    Route::post('/user/upload-banner', [\App\Http\Controllers\UserController::class, 'uploadBanner'])->name('api.user.upload-banner');
});

// Live Support Routes
Route::middleware(['auth'])->group(function () {
    Route::post('/support/conversations', [\App\Http\Controllers\LiveSupportController::class, 'createConversation'])->name('api.support.create-conversation');
    Route::get('/support/conversations', [\App\Http\Controllers\LiveSupportController::class, 'getUserConversations'])->name('api.support.get-conversations');
    Route::get('/support/conversations/{id}/messages', [\App\Http\Controllers\LiveSupportController::class, 'getConversationMessages'])->name('api.support.get-messages');
    Route::post('/support/conversations/{id}/messages', [\App\Http\Controllers\LiveSupportController::class, 'sendMessage'])->name('api.support.send-message');
    Route::get('/support/conversations/{conversationId}/messages/{messageId}/files/{fileIndex}', [\App\Http\Controllers\LiveSupportController::class, 'serveAttachment'])->name('api.support.serve-attachment');
    
    // Admin routes
    Route::get('/support/admin/conversations', [\App\Http\Controllers\LiveSupportController::class, 'getAllConversations'])->name('api.support.admin.get-conversations');
    Route::post('/support/admin/conversations/{id}/reply', [\App\Http\Controllers\LiveSupportController::class, 'adminReply'])->name('api.support.admin.reply');
    Route::put('/support/admin/conversations/{id}/status', [\App\Http\Controllers\LiveSupportController::class, 'updateConversationStatus'])->name('api.support.admin.update-status');
});

