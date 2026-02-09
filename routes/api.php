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
use App\Http\Controllers\WarehouseTransferController;
use App\Http\Controllers\OutletTransferController;
use App\Http\Controllers\InternalWarehouseTransferController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\BirthdayController;
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
use App\Http\Controllers\FoodFloorOrderController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SubCategoryController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\Api\PosOrderController;
use App\Http\Controllers\Api\ClosingShiftController;
use App\Http\Controllers\Api\PosSyncController;
use App\Http\Controllers\OutletFoodGoodReceiveController;
use App\Http\Controllers\GoodReceiveOutletSupplierController;
use App\Http\Middleware\ApprovalAppAuth;


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

Route::prefix('approval-app')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware([ApprovalAppAuth::class])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'user']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/outlet-food-good-receives', [OutletFoodGoodReceiveController::class, 'apiIndex']);
        Route::get('/outlet-food-good-receives/available-dos', [OutletFoodGoodReceiveController::class, 'availableDOs']);
        Route::get('/outlet-food-good-receives/do-detail/{doId}', [OutletFoodGoodReceiveController::class, 'doDetail']);
        Route::get('/outlet-food-good-receives/{id}', [OutletFoodGoodReceiveController::class, 'apiShow']);
        Route::post('/outlet-food-good-receives', [OutletFoodGoodReceiveController::class, 'store']);
        Route::delete('/outlet-food-good-receives/{id}', [OutletFoodGoodReceiveController::class, 'destroy']);

        Route::get('/outlet-supplier-good-receives', [GoodReceiveOutletSupplierController::class, 'apiIndex']);
        Route::get('/outlet-supplier-good-receives/available-ros', [GoodReceiveOutletSupplierController::class, 'apiAvailableROs']);
        Route::get('/outlet-supplier-good-receives/available-dos', [GoodReceiveOutletSupplierController::class, 'apiAvailableDOs']);
        Route::get('/outlet-supplier-good-receives/ro-detail/{roSupplierId}', [GoodReceiveOutletSupplierController::class, 'apiRoDetail']);
        Route::get('/outlet-supplier-good-receives/do-detail/{doId}', [GoodReceiveOutletSupplierController::class, 'apiDoDetail']);
        Route::get('/outlet-supplier-good-receives/{id}', [GoodReceiveOutletSupplierController::class, 'apiShow']);
        Route::post('/outlet-supplier-good-receives', [GoodReceiveOutletSupplierController::class, 'apiStore']);
        Route::delete('/outlet-supplier-good-receives/{id}', [GoodReceiveOutletSupplierController::class, 'destroy']);
    });
});

Route::get('/quotes/{dayOfYear}', [QuoteController::class, 'getQuoteByDay']);

Route::get('items/last-price', [\App\Http\Controllers\PurchaseOrderFoodsController::class, 'getLastPrice']);

Route::get('/items/search-for-warehouse-transfer', [ItemController::class, 'searchForWarehouseTransfer']);
Route::get('/items/search-for-outlet-transfer', [ItemController::class, 'searchForOutletTransfer']);
Route::get('/items/search-for-internal-warehouse-transfer', [ItemController::class, 'searchForInternalWarehouseTransfer']);
Route::get('/items/search-for-pr', [ItemController::class, 'searchForPr']);
Route::get('/items/search-for-outlet-stock-adjustment', [ItemController::class, 'searchForOutletStockAdjustment']);
Route::get('/items/{id}/detail', [ApiItemController::class, 'detail']);
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

// Approval App Routes (separate from web and member app)
Route::prefix('approval-app')->group(function () {
    // Auth routes (no auth required)
    Route::post('/auth/login', [\App\Http\Controllers\Mobile\ApprovalApp\AuthController::class, 'login']);
    
    // Protected routes (require auth via remember_token)
    Route::middleware(['approval.app.auth'])->group(function () {
        Route::get('/auth/me', [\App\Http\Controllers\Mobile\ApprovalApp\AuthController::class, 'me']);
        Route::post('/auth/logout', [\App\Http\Controllers\Mobile\ApprovalApp\AuthController::class, 'logout']);
        Route::post('/user/upload-banner', [\App\Http\Controllers\Mobile\ApprovalApp\AuthController::class, 'uploadBanner']);
        Route::post('/user/upload-avatar', [\App\Http\Controllers\Mobile\ApprovalApp\AuthController::class, 'uploadAvatar']);
        Route::get('/allowed-menus', [\App\Http\Controllers\Mobile\ApprovalApp\AuthController::class, 'getAllowedMenus']);

        // Announcement & Birthday routes for Approval App
        Route::get('/user-announcements', [AnnouncementController::class, 'getUserAnnouncements']);
        Route::get('/birthdays', [BirthdayController::class, 'getBirthdays']);

        // Warehouse Transfer (Approval App)
        Route::get('/warehouse-transfers', [WarehouseTransferController::class, 'apiIndex']);
        Route::get('/warehouse-transfers/{id}', [WarehouseTransferController::class, 'apiShow']);
        Route::post('/warehouse-transfers', [WarehouseTransferController::class, 'apiStore']);

        // Internal Warehouse Transfer (Approval App - transfer antar gudang dalam 1 outlet)
        Route::get('/internal-warehouse-transfers', [InternalWarehouseTransferController::class, 'apiIndex']);
        Route::get('/internal-warehouse-transfers/create-data', [InternalWarehouseTransferController::class, 'apiCreateData']);
        Route::get('/internal-warehouse-transfers/{id}', [InternalWarehouseTransferController::class, 'apiShow']);
        Route::post('/internal-warehouse-transfers', [InternalWarehouseTransferController::class, 'apiStore']);

        // Retail Food (Approval App - Outlet Retail Food)
        Route::get('/retail-food', [\App\Http\Controllers\RetailFoodController::class, 'apiIndex']);
        Route::get('/retail-food/create-data', [\App\Http\Controllers\RetailFoodController::class, 'apiCreateData']);
        Route::get('/retail-food/get-item-units/{itemId}', [\App\Http\Controllers\RetailFoodController::class, 'getItemUnits']);
        Route::get('/retail-food/{id}', [\App\Http\Controllers\RetailFoodController::class, 'apiShow']);
        Route::post('/retail-food', [\App\Http\Controllers\RetailFoodController::class, 'apiStore']);

        // Retail Non Food (Approval App - Outlet Retail Non Food)
        Route::get('/retail-non-food', [\App\Http\Controllers\RetailNonFoodController::class, 'apiIndex']);
        Route::get('/retail-non-food/create-data', [\App\Http\Controllers\RetailNonFoodController::class, 'apiCreateData']);
        Route::get('/retail-non-food/{id}', [\App\Http\Controllers\RetailNonFoodController::class, 'apiShow']);
        Route::post('/retail-non-food', [\App\Http\Controllers\RetailNonFoodController::class, 'apiStore']);

        // Outlet Food Return (Approval App)
        Route::get('/outlet-food-return', [\App\Http\Controllers\OutletFoodReturnController::class, 'apiIndex']);
        Route::get('/outlet-food-return/create-data', [\App\Http\Controllers\OutletFoodReturnController::class, 'apiCreateData']);
        Route::get('/outlet-food-return/get-warehouse-outlets', [\App\Http\Controllers\OutletFoodReturnController::class, 'getWarehouseOutlets']);
        Route::get('/outlet-food-return/get-good-receives', [\App\Http\Controllers\OutletFoodReturnController::class, 'getGoodReceives']);
        Route::get('/outlet-food-return/get-good-receive-items', [\App\Http\Controllers\OutletFoodReturnController::class, 'getGoodReceiveItems']);
        Route::get('/outlet-food-return/{id}', [\App\Http\Controllers\OutletFoodReturnController::class, 'apiShow']);
        Route::post('/outlet-food-return', [\App\Http\Controllers\OutletFoodReturnController::class, 'store']);

        // Outlet Stock Opname (Approval App) — route statis dulu, {id} terakhir dengan constraint numerik
        Route::get('/stock-opnames', [\App\Http\Controllers\StockOpnameController::class, 'apiIndex']);
        Route::get('/stock-opnames/create-data', [\App\Http\Controllers\StockOpnameController::class, 'apiCreateData']);
        Route::get('/stock-opnames/get-inventory-items', [\App\Http\Controllers\StockOpnameController::class, 'apiGetInventoryItems']);
        Route::get('/stock-opnames/approvers', [\App\Http\Controllers\StockOpnameController::class, 'getApprovers']);
        Route::get('/stock-opnames/pending-approvals', [\App\Http\Controllers\StockOpnameController::class, 'getPendingApprovals']);
        Route::get('/stock-opnames/{id}', [\App\Http\Controllers\StockOpnameController::class, 'apiShow'])->where('id', '[0-9]+');
        Route::post('/stock-opnames', [\App\Http\Controllers\StockOpnameController::class, 'apiStore']);
        Route::put('/stock-opnames/{id}', [\App\Http\Controllers\StockOpnameController::class, 'apiUpdate'])->where('id', '[0-9]+');
        Route::delete('/stock-opnames/{id}', [\App\Http\Controllers\StockOpnameController::class, 'destroy'])->where('id', '[0-9]+');
        Route::post('/stock-opnames/{id}/submit-approval', [\App\Http\Controllers\StockOpnameController::class, 'apiSubmitForApproval'])->where('id', '[0-9]+');
        Route::post('/stock-opnames/{id}/approve', [\App\Http\Controllers\StockOpnameController::class, 'apiApprove'])->where('id', '[0-9]+');
        Route::post('/stock-opnames/{id}/process', [\App\Http\Controllers\StockOpnameController::class, 'apiProcess'])->where('id', '[0-9]+');

        // Outlet Transfer (Approval App / Pindah Outlet)
        Route::get('/outlet-transfers', [OutletTransferController::class, 'apiIndex']);
        Route::get('/outlet-transfers/pending-approvals', [OutletTransferController::class, 'getPendingApprovals']);
        Route::get('/outlet-transfers/create-data', [OutletTransferController::class, 'apiCreateData']);
        Route::get('/outlet-transfers/{id}', [OutletTransferController::class, 'apiShow']);
        Route::post('/outlet-transfers', [OutletTransferController::class, 'apiStore']);
        Route::post('/outlet-transfers/{id}/submit', [OutletTransferController::class, 'submit']);
        Route::post('/outlet-transfers/{id}/approve', [OutletTransferController::class, 'apiApprove']);
        Route::get('/outlet-transfer/approvers', [OutletTransferController::class, 'getApprovers']);

        // Report Invoice Outlet (Approval App - Laporan Invoice Outlet)
        Route::get('/report-invoice-outlet', [\App\Http\Controllers\OutletPaymentController::class, 'apiReportInvoiceOutlet']);

        // Stock Cut (Approval App - Potong Stock)
        Route::get('/stock-cut/form-data', [\App\Http\Controllers\StockCutController::class, 'apiFormData']);
        Route::get('/stock-cut/logs', [\App\Http\Controllers\StockCutController::class, 'getLogs']);
        Route::post('/stock-cut/check-status', [\App\Http\Controllers\StockCutController::class, 'checkStockCutStatus']);
        Route::post('/stock-cut/cek-kebutuhan', [\App\Http\Controllers\StockCutController::class, 'cekKebutuhanStockV2']);
        Route::post('/stock-cut/dispatch', [\App\Http\Controllers\StockCutController::class, 'dispatchStockCut']);
        Route::delete('/stock-cut/{id}', [\App\Http\Controllers\StockCutController::class, 'rollback'])->where('id', '[0-9]+');

        // Floor Order (Approval App)
        Route::get('/floor-orders', [FoodFloorOrderController::class, 'apiIndex']);
        Route::get('/floor-orders/check-exists', [FoodFloorOrderController::class, 'checkExists']);
        Route::get('/floor-orders/supplier-available', [FoodFloorOrderController::class, 'supplierAvailable']);
        Route::get('/floor-orders/{id}', [FoodFloorOrderController::class, 'apiShow']);
        Route::post('/floor-orders', [FoodFloorOrderController::class, 'store']);
        Route::put('/floor-orders/{id}', [FoodFloorOrderController::class, 'update']);
        Route::delete('/floor-orders/{id}', [FoodFloorOrderController::class, 'destroy']);
        Route::post('/floor-orders/{id}/submit', [FoodFloorOrderController::class, 'submit']);
        Route::get('/floor-orders/{id}/export-pdf', [FoodFloorOrderController::class, 'exportPdf']);

        // Categories (Master Data - Approval App)
        Route::get('/categories', [CategoryController::class, 'apiIndex']);
        Route::get('/categories/create-data', [CategoryController::class, 'apiCreateData']);
        Route::get('/categories/{id}', [CategoryController::class, 'apiShow'])->where('id', '[0-9]+');
        Route::post('/categories', [CategoryController::class, 'apiStore']);
        Route::put('/categories/{id}', [CategoryController::class, 'apiUpdate'])->where('id', '[0-9]+');
        Route::patch('/categories/{id}/toggle-status', [CategoryController::class, 'apiToggleStatus'])->where('id', '[0-9]+');
        Route::delete('/categories/{id}', [CategoryController::class, 'apiDestroy'])->where('id', '[0-9]+');

        // Sub Categories (Master Data - Approval App)
        Route::get('/sub-categories', [SubCategoryController::class, 'apiIndex']);
        Route::get('/sub-categories/create-data', [SubCategoryController::class, 'apiCreateData']);
        Route::get('/sub-categories/{id}', [SubCategoryController::class, 'apiShow'])->where('id', '[0-9]+');
        Route::post('/sub-categories', [SubCategoryController::class, 'apiStore']);
        Route::put('/sub-categories/{id}', [SubCategoryController::class, 'apiUpdate'])->where('id', '[0-9]+');
        Route::patch('/sub-categories/{id}/toggle-status', [SubCategoryController::class, 'apiToggleStatus'])->where('id', '[0-9]+');
        Route::delete('/sub-categories/{id}', [SubCategoryController::class, 'apiDestroy'])->where('id', '[0-9]+');

        // Units (Master Data - Approval App)
        Route::get('/units', [UnitController::class, 'apiIndex']);
        Route::get('/units/{id}', [UnitController::class, 'apiShow'])->where('id', '[0-9]+');
        Route::post('/units', [UnitController::class, 'apiStore']);
        Route::put('/units/{id}', [UnitController::class, 'apiUpdate'])->where('id', '[0-9]+');
        Route::patch('/units/{id}/toggle-status', [UnitController::class, 'apiToggleStatus'])->where('id', '[0-9]+');
        Route::delete('/units/{id}', [UnitController::class, 'apiDestroy'])->where('id', '[0-9]+');

        // Items (Master Data - Approval App)
        Route::get('/items', [ItemController::class, 'apiIndex']);
        Route::get('/items/create-data', [ItemController::class, 'apiCreateData']);
        Route::get('/items/{id}', [ItemController::class, 'apiShow'])->where('id', '[0-9]+');
        Route::post('/items', [ItemController::class, 'apiStore']);
        Route::put('/items/{id}', [ItemController::class, 'apiUpdate'])->where('id', '[0-9]+');
        Route::patch('/items/{id}/toggle-status', [ItemController::class, 'apiToggleStatus'])->where('id', '[0-9]+');
        Route::delete('/items/{id}', [ItemController::class, 'apiDestroy'])->where('id', '[0-9]+');

        // Outlet Inventory Stock Position (Approval App)
        Route::get('/outlet-inventory/stock-position', [
            \App\Http\Controllers\OutletInventoryReportController::class,
            'apiStockPosition'
        ]);
        Route::get('/outlet-inventory/stock-card/detail', [
            \App\Http\Controllers\OutletInventoryReportController::class,
            'apiStockCardDetail'
        ]);
        Route::get('/outlet-inventory/stock-card', [
            \App\Http\Controllers\OutletInventoryReportController::class,
            'apiStockCard'
        ]);
        Route::get('/outlet-inventory/stock-card/items', [
            \App\Http\Controllers\OutletInventoryReportController::class,
            'apiStockCardItems'
        ]);
        Route::get('/outlet-inventory/warehouse-outlets', [
            \App\Http\Controllers\OutletInventoryReportController::class,
            'apiWarehouseOutlets'
        ]);
        Route::get('/outlet-inventory/stock', function (Request $request) {
            $item_id = $request->get('item_id');
            $warehouse_outlet_id = $request->get('warehouse_outlet_id');
            if (!$item_id || !$warehouse_outlet_id) {
                return response()->json(['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0, 'unit_small' => '', 'unit_medium' => '', 'unit_large' => ''], 400);
            }
            $stock = DB::table('outlet_food_inventory_stocks as ofis')
                ->join('outlet_food_inventory_items as ofii', 'ofis.inventory_item_id', '=', 'ofii.id')
                ->join('items as i', 'ofii.item_id', '=', 'i.id')
                ->leftJoin('units as u_small', 'i.small_unit_id', '=', 'u_small.id')
                ->leftJoin('units as u_medium', 'i.medium_unit_id', '=', 'u_medium.id')
                ->leftJoin('units as u_large', 'i.large_unit_id', '=', 'u_large.id')
                ->where('ofii.item_id', $item_id)
                ->where('ofis.warehouse_outlet_id', $warehouse_outlet_id)
                ->select(
                    'ofis.qty_small', 'ofis.qty_medium', 'ofis.qty_large',
                    'ofis.last_cost_small', 'ofis.last_cost_medium', 'ofis.last_cost_large',
                    'u_small.name as unit_small', 'u_medium.name as unit_medium', 'u_large.name as unit_large',
                    'i.small_conversion_qty', 'i.medium_conversion_qty'
                )
                ->first();
            if (!$stock) {
                return response()->json([
                    'qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0,
                    'unit_small' => '', 'unit_medium' => '', 'unit_large' => '',
                    'small_conversion_qty' => 1, 'medium_conversion_qty' => 1,
                ]);
            }
            return response()->json((array) $stock);
        });

        // Outlet Stock Adjustment (Approval App)
        Route::get('/outlet-food-inventory-adjustments', [
            \App\Http\Controllers\OutletFoodInventoryAdjustmentController::class,
            'apiIndex'
        ]);
        Route::get('/outlet-food-inventory-adjustments/{id}', [
            \App\Http\Controllers\OutletFoodInventoryAdjustmentController::class,
            'apiShow'
        ]);
        Route::post('/outlet-food-inventory-adjustments', [
            \App\Http\Controllers\OutletFoodInventoryAdjustmentController::class,
            'apiStore'
        ]);
        Route::get('/outlet-food-inventory-adjustment/warehouse-outlets', [
            \App\Http\Controllers\OutletFoodInventoryAdjustmentController::class,
            'getWarehouseOutlets'
        ]);
        Route::get('/outlet-food-inventory-adjustment/approvers', [
            \App\Http\Controllers\OutletFoodInventoryAdjustmentController::class,
            'getApprovers'
        ]);

        // Outlet WIP Production (approval app)
        Route::get('/outlet-wip', [\App\Http\Controllers\OutletWIPController::class, 'apiIndex']);
        Route::get('/outlet-wip/create-data', [\App\Http\Controllers\OutletWIPController::class, 'apiCreateData']);
        Route::post('/outlet-wip/bom', [\App\Http\Controllers\OutletWIPController::class, 'getBomAndStock']);
        Route::post('/outlet-wip', [\App\Http\Controllers\OutletWIPController::class, 'store']);
        Route::post('/outlet-wip/store-and-submit', [\App\Http\Controllers\OutletWIPController::class, 'storeAndSubmit']);
        Route::post('/outlet-wip/{id}/submit', [\App\Http\Controllers\OutletWIPController::class, 'submit']);
        Route::get('/outlet-wip/{id}', [\App\Http\Controllers\OutletWIPController::class, 'apiShow']);
        Route::delete('/outlet-wip/{id}', [\App\Http\Controllers\OutletWIPController::class, 'destroy']);
        Route::get('/outlet-wip-report', [\App\Http\Controllers\OutletWIPController::class, 'apiReport']);

        // Device Token routes for Approval App
        Route::post('/device-token/register', [\App\Http\Controllers\Mobile\ApprovalApp\DeviceTokenController::class, 'register'])->name('api.approval-app.device-token.register');
        Route::post('/device-token/unregister', [\App\Http\Controllers\Mobile\ApprovalApp\DeviceTokenController::class, 'unregister'])->name('api.approval-app.device-token.unregister');
        Route::get('/device-token', [\App\Http\Controllers\Mobile\ApprovalApp\DeviceTokenController::class, 'index'])->name('api.approval-app.device-token.index');
        
        // User PIN routes for Approval App
        Route::get('/user/pins', [\App\Http\Controllers\UserPinController::class, 'indexForApprovalApp']);
        Route::post('/user/pins', [\App\Http\Controllers\UserPinController::class, 'storeForApprovalApp']);
        Route::put('/user/pins/{id}', [\App\Http\Controllers\UserPinController::class, 'updateForApprovalApp']);
        Route::delete('/user/pins/{id}', [\App\Http\Controllers\UserPinController::class, 'destroyForApprovalApp']);
        Route::get('/outlets', [\App\Http\Controllers\UserPinController::class, 'getOutletsForApprovalApp']);
        Route::get('/divisions', [\App\Http\Controllers\PurchaseRequisitionController::class, 'getDivisions']);
        
        // OPTIMASI: Single endpoint untuk semua pending approvals (mengurangi API calls dari 15+ menjadi 1)
        Route::get('/pending-approvals/all', [\App\Http\Controllers\PendingApprovalController::class, 'getAllPendingApprovals']);
        Route::post('/pending-approvals/clear-cache', [\App\Http\Controllers\PendingApprovalController::class, 'clearCache']);
        
        // Purchase Requisition routes - using existing controller methods
        // IMPORTANT: Specific routes (without {id}) must be defined BEFORE routes with {id} parameter
        Route::get('/purchase-requisitions', [\App\Http\Controllers\PurchaseRequisitionController::class, 'index']);
        Route::get('/purchase-requisitions/pending-approvals', [\App\Http\Controllers\PurchaseRequisitionController::class, 'getPendingApprovals']);
        Route::get('/purchase-requisitions/categories', [\App\Http\Controllers\PurchaseRequisitionController::class, 'getCategories']);
        Route::get('/purchase-requisitions/approvers', [\App\Http\Controllers\PurchaseRequisitionController::class, 'getApprovers']);
        Route::get('/purchase-requisitions/budget-info', [\App\Http\Controllers\PurchaseRequisitionController::class, 'getBudgetInfo']);
        Route::get('/purchase-requisitions/check-kasbon-period', [\App\Http\Controllers\PurchaseRequisitionController::class, 'checkKasbonPeriod']);
        Route::get('/purchase-requisitions/attachments/{attachmentId}/view', [\App\Http\Controllers\PurchaseRequisitionController::class, 'viewAttachmentApi']);
        // Routes with {id} parameter must come after specific routes
        Route::get('/purchase-requisitions/{id}', [\App\Http\Controllers\PurchaseRequisitionController::class, 'show']);
        Route::post('/purchase-requisitions', [\App\Http\Controllers\PurchaseRequisitionController::class, 'store']);
        Route::put('/purchase-requisitions/{id}', [\App\Http\Controllers\PurchaseRequisitionController::class, 'update']);
        Route::delete('/purchase-requisitions/{id}', [\App\Http\Controllers\PurchaseRequisitionController::class, 'destroy']);
        Route::post('/purchase-requisitions/{id}/submit', [\App\Http\Controllers\PurchaseRequisitionController::class, 'submit']);
        Route::get('/purchase-requisitions/{id}/approval-details', [\App\Http\Controllers\PurchaseRequisitionController::class, 'getApprovalDetails']);
        Route::post('/purchase-requisitions/{id}/approve', [\App\Http\Controllers\PurchaseRequisitionController::class, 'approve']);
        Route::post('/purchase-requisitions/{id}/reject', [\App\Http\Controllers\PurchaseRequisitionController::class, 'reject']);
        Route::post('/purchase-requisitions/{id}/comments', [\App\Http\Controllers\PurchaseRequisitionController::class, 'addComment']);
        Route::get('/purchase-requisitions/{id}/comments', [\App\Http\Controllers\PurchaseRequisitionController::class, 'getComments']);
        Route::delete('/purchase-requisitions/{id}/comments/{commentId}', [\App\Http\Controllers\PurchaseRequisitionController::class, 'deleteComment']);
        Route::post('/purchase-requisitions/{id}/attachments', [\App\Http\Controllers\PurchaseRequisitionController::class, 'uploadAttachment']);
        
        Route::get('/po-ops/pending-approvals', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'getPendingApprovals']);
        Route::get('/po-ops/{id}', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'show']);
        Route::post('/po-ops/{id}/approve', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'approve']);
        Route::get('/po-ops/attachments/{attachmentId}/view', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'viewAttachmentApi']);
        
        Route::get('/approval/pending', [\App\Http\Controllers\ApprovalController::class, 'getPendingApprovals']);
        Route::get('/approval/pending-hrd', [\App\Http\Controllers\ApprovalController::class, 'getPendingHrdApprovals']);
        Route::get('/approval/{id}', [\App\Http\Controllers\ApprovalController::class, 'getApprovalDetails']);
        Route::post('/approval/{id}/approve', [\App\Http\Controllers\ApprovalController::class, 'approve']);
        Route::post('/approval/{id}/reject', [\App\Http\Controllers\ApprovalController::class, 'reject']);
        Route::post('/approval/{id}/hrd-approve', [\App\Http\Controllers\ApprovalController::class, 'hrdApprove']);
        Route::post('/approval/{id}/hrd-reject', [\App\Http\Controllers\ApprovalController::class, 'hrdReject']);
        
        // API endpoints for Outlet Internal Use / Waste (Category Cost)
        // List, item search, detail, store, store-and-submit, and item units
        Route::get('/outlet-internal-use-waste', [\App\Http\Controllers\OutletInternalUseWasteController::class, 'index']);
        Route::get('/outlet-internal-use-waste/items', [\App\Http\Controllers\OutletInternalUseWasteController::class, 'items']);
        Route::get('/outlet-internal-use-waste/{id}', [\App\Http\Controllers\OutletInternalUseWasteController::class, 'show']);
        Route::post('/outlet-internal-use-waste', [\App\Http\Controllers\OutletInternalUseWasteController::class, 'store']);
        Route::post('/outlet-internal-use-waste/store-and-submit', [\App\Http\Controllers\OutletInternalUseWasteController::class, 'storeAndSubmit']);
        Route::post('/outlet-internal-use-waste/{id}/submit', [\App\Http\Controllers\OutletInternalUseWasteController::class, 'submit']);
        Route::get('/outlet-internal-use-waste/get-item-units/{id}', [\App\Http\Controllers\OutletInternalUseWasteController::class, 'getItemUnits']);
        Route::get('/outlet-internal-use-waste/stock', [\App\Http\Controllers\OutletInternalUseWasteController::class, 'getStock']);
        Route::get('/outlet-internal-use-waste/approvers', [\App\Http\Controllers\OutletInternalUseWasteController::class, 'getApprovers']);

        // Approval-related endpoints (existing)
        Route::get('/outlet-internal-use-waste/approvals/pending', [\App\Http\Controllers\OutletInternalUseWasteController::class, 'getPendingApprovals']);
        Route::get('/outlet-internal-use-waste/{id}/approval-details', [\App\Http\Controllers\OutletInternalUseWasteController::class, 'getApprovalDetails']);
        Route::post('/outlet-internal-use-waste/{id}/approve', [\App\Http\Controllers\OutletInternalUseWasteController::class, 'approve']);
        Route::post('/outlet-internal-use-waste/{id}/reject', [\App\Http\Controllers\OutletInternalUseWasteController::class, 'reject']);
        
        Route::get('/outlet-food-inventory-adjustment/pending-approvals', [\App\Http\Controllers\OutletFoodInventoryAdjustmentController::class, 'getPendingApprovals']);
        Route::get('/outlet-food-inventory-adjustment/{id}/approval-details', [\App\Http\Controllers\OutletFoodInventoryAdjustmentController::class, 'getApprovalDetails']);
        Route::post('/outlet-food-inventory-adjustment/{id}/approve', [\App\Http\Controllers\OutletFoodInventoryAdjustmentController::class, 'approve']);
        Route::post('/outlet-food-inventory-adjustment/{id}/reject', [\App\Http\Controllers\OutletFoodInventoryAdjustmentController::class, 'reject']);
        
        Route::get('/food-inventory-adjustment/pending-approvals', [\App\Http\Controllers\FoodInventoryAdjustmentController::class, 'getPendingApprovals']);
        Route::get('/food-inventory-adjustment/{id}/approval-details', [\App\Http\Controllers\FoodInventoryAdjustmentController::class, 'getApprovalDetails']);
        Route::post('/food-inventory-adjustment/{id}/approve', [\App\Http\Controllers\FoodInventoryAdjustmentController::class, 'approve']);
        Route::post('/food-inventory-adjustment/{id}/reject', [\App\Http\Controllers\FoodInventoryAdjustmentController::class, 'reject']);
        
        Route::get('/outlet-transfer/pending-approvals', [OutletTransferController::class, 'getPendingApprovals']);
        Route::get('/contra-bon/pending-approvals', [\App\Http\Controllers\ContraBonController::class, 'getPendingApprovals']);
        Route::get('/contra-bon/{id}', [\App\Http\Controllers\ContraBonController::class, 'getDetail']);
        Route::post('/contra-bon/{id}/approve', [\App\Http\Controllers\ContraBonController::class, 'approve']);
        Route::post('/contra-bon/{id}/reject', [\App\Http\Controllers\ContraBonController::class, 'reject']);
        
        Route::get('/employee-movements/pending-approvals', [\App\Http\Controllers\EmployeeMovementController::class, 'getPendingApprovals']);
        Route::get('/employee-movements/{id}/approval-details', [\App\Http\Controllers\EmployeeMovementController::class, 'getApprovalDetails']);
        Route::post('/employee-movements/{id}/approve', [\App\Http\Controllers\EmployeeMovementController::class, 'approve']);
        Route::post('/employee-movements/{id}/reject', function(\Illuminate\Http\Request $request, $id) {
            $request->merge(['status' => 'rejected']);
            return app(\App\Http\Controllers\EmployeeMovementController::class)->approve($request, $id);
        });
        
        Route::get('/coaching/pending-approvals', [\App\Http\Controllers\CoachingController::class, 'getPendingApprovals']);
        Route::get('/coaching/{id}', [\App\Http\Controllers\CoachingController::class, 'getDetail']);
        Route::post('/coaching/{id}/approve', [\App\Http\Controllers\CoachingController::class, 'approve']);
        Route::post('/coaching/{id}/reject', [\App\Http\Controllers\CoachingController::class, 'reject']);
        
        Route::get('/schedule-attendance-correction/pending-approvals', [\App\Http\Controllers\ScheduleAttendanceCorrectionController::class, 'getPendingApprovals']);
        Route::get('/schedule-attendance-correction/{id}', [\App\Http\Controllers\ScheduleAttendanceCorrectionController::class, 'getApprovalDetail']);
        Route::post('/schedule-attendance-correction/{id}/approve', [\App\Http\Controllers\ScheduleAttendanceCorrectionController::class, 'approveCorrection']);
        Route::post('/schedule-attendance-correction/{id}/reject', [\App\Http\Controllers\ScheduleAttendanceCorrectionController::class, 'rejectCorrection']);
        
        // Food Payment routes
        Route::get('/food-payment/pending-approvals', [\App\Http\Controllers\FoodPaymentController::class, 'getPendingApprovals']);
        Route::get('/food-payment/{id}', [\App\Http\Controllers\FoodPaymentController::class, 'getDetail']);
        Route::post('/food-payment/{id}/approve', [\App\Http\Controllers\FoodPaymentController::class, 'approve']); // approve dengan approved=true/false
        
        // Non Food Payment routes
        Route::get('/non-food-payment/pending-approvals', [\App\Http\Controllers\NonFoodPaymentController::class, 'getPendingApprovals']);
        Route::get('/non-food-payment/{id}', [\App\Http\Controllers\NonFoodPaymentController::class, 'getDetail']);
        Route::post('/non-food-payment/{id}/approve', [\App\Http\Controllers\NonFoodPaymentController::class, 'approve']);
        Route::post('/non-food-payment/{id}/reject', [\App\Http\Controllers\NonFoodPaymentController::class, 'reject']);
        
        // PR Food approval routes (multiple approval levels) - keep inside approval-app prefix
        Route::get('/pr-food/pending-approvals', [\App\Http\Controllers\PrFoodController::class, 'getPendingApprovals']);
        Route::get('/pr-food/{id}', [\App\Http\Controllers\PrFoodController::class, 'getDetail']);
        Route::post('/pr-food/{id}/approve-assistant-ssd-manager', [\App\Http\Controllers\PrFoodController::class, 'approveAssistantSsdManager']);
        Route::post('/pr-food/{id}/approve-ssd-manager', [\App\Http\Controllers\PrFoodController::class, 'approveSsdManager']);
        Route::post('/pr-food/{id}/approve-vice-coo', [\App\Http\Controllers\PrFoodController::class, 'approveViceCoo']);
        
        // PO Food routes (multiple approval levels)
        Route::get('/po-food/pending-approvals', [\App\Http\Controllers\PurchaseOrderFoodsController::class, 'getPendingApprovals']);
        Route::get('/po-food/{id}', [\App\Http\Controllers\PurchaseOrderFoodsController::class, 'getDetail']);
        Route::post('/po-food/{id}/approve-purchasing-manager', [\App\Http\Controllers\PurchaseOrderFoodsController::class, 'approvePurchasingManager']);
        Route::post('/po-food/{id}/approve-gm-finance', [\App\Http\Controllers\PurchaseOrderFoodsController::class, 'approveGMFinance']);
        Route::post('/po-food/{id}/reject', [\App\Http\Controllers\PurchaseOrderFoodsController::class, 'approvePurchasingManager']); // Reject uses same method with approved=false
        
        // Inventory stock route for approval app
        Route::get('/inventory/stock', [\App\Http\Controllers\ItemController::class, 'getStock']);

        // Item search route for approval app (FO/RO)
        Route::get('/items/search', [ItemController::class, 'search']);
        
        // Warehouses route for approval app
        Route::get('/warehouses', function () {
            $warehouses = \App\Models\Warehouse::where('status', 'active')
                ->orderBy('name')
                ->select('id', 'name', 'status')
                ->get();
            return response()->json($warehouses);
        });
        
        // Warehouse divisions route for approval app
        Route::get('/warehouse-divisions', function (Request $request) {
            $query = \App\Models\WarehouseDivision::where('status', 'active');
            
            if ($request->has('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }
            
            $divisions = $query->orderBy('name')
                ->select('id', 'name', 'warehouse_id', 'status')
                ->get();
            
            return response()->json($divisions);
        });
        
        // RO Khusus routes
        Route::get('/ro-khusus/pending-approvals', [\App\Http\Controllers\FoodFloorOrderController::class, 'getPendingROKhususApprovals']);
        Route::get('/ro-khusus/{id}', [\App\Http\Controllers\FoodFloorOrderController::class, 'getROKhususDetail']);
        Route::post('/ro-khusus/{id}/approve', [\App\Http\Controllers\FoodFloorOrderController::class, 'approve']); // approve dengan approved=true/false
        Route::post('/ro-khusus/{id}/reject', [\App\Http\Controllers\FoodFloorOrderController::class, 'approve']); // reject menggunakan approve dengan approved=false
        
        // Employee Resignation routes
        Route::get('/employee-resignation/pending-approvals', [\App\Http\Controllers\EmployeeResignationController::class, 'pendingApprovals']);
        
        // Attendance routes
        Route::get('/attendance/data', [\App\Http\Controllers\AttendanceController::class, 'getAttendanceDataApi'])->name('api.approval-app.attendance.data');
        Route::get('/attendance/calendar-data', [\App\Http\Controllers\AttendanceController::class, 'getCalendarData'])->name('api.approval-app.attendance.calendar-data');
        Route::post('/attendance/absent-request', [\App\Http\Controllers\AttendanceController::class, 'submitAbsentRequest'])->name('api.approval-app.attendance.absent-request');
        Route::get('/attendance/approvers', [\App\Http\Controllers\AttendanceController::class, 'getApprovers'])->name('api.approval-app.attendance.approvers');
        Route::post('/attendance/cancel-leave/{id}', [\App\Http\Controllers\AttendanceController::class, 'cancelLeaveRequest'])->name('api.approval-app.attendance.cancel-leave');
        Route::get('/employee-resignation/{id}', [\App\Http\Controllers\EmployeeResignationController::class, 'show']);
        Route::post('/employee-resignation/{id}/approve', [\App\Http\Controllers\EmployeeResignationController::class, 'approve']);
        Route::post('/employee-resignation/{id}/reject', [\App\Http\Controllers\EmployeeResignationController::class, 'reject']);
        
        // Video Tutorial routes
        Route::get('/video-tutorials/gallery', [\App\Http\Controllers\VideoTutorialController::class, 'galleryApi']);
        Route::get('/video-tutorials/groups', [\App\Http\Controllers\VideoTutorialController::class, 'getGroupsApi']);
        
        // Sales Outlet Dashboard API
        Route::get('/sales-outlet-dashboard', [\App\Http\Controllers\SalesOutletDashboardController::class, 'dashboardApi']);
        Route::get('/sales-outlet-dashboard/outlet-details', [\App\Http\Controllers\SalesOutletDashboardController::class, 'getOutletDetailsByDate']);
        Route::get('/sales-outlet-dashboard/outlet-daily-revenue', [\App\Http\Controllers\SalesOutletDashboardController::class, 'getOutletDailyRevenue']);
        Route::get('/sales-outlet-dashboard/outlet-lunch-dinner-detail', [\App\Http\Controllers\SalesOutletDashboardController::class, 'getOutletLunchDinnerDetail']);
        Route::get('/sales-outlet-dashboard/outlet-weekend-weekday-detail', [\App\Http\Controllers\SalesOutletDashboardController::class, 'getOutletWeekendWeekdayDetail']);
        Route::get('/sales-outlet-dashboard/holidays', [\App\Http\Controllers\SalesOutletDashboardController::class, 'getHolidays']);
        Route::get('/sales-outlet-dashboard/outlet-orders', [\App\Http\Controllers\SalesOutletDashboardController::class, 'getOutletOrders']);
        
        // Live Support Routes for Approval App
        Route::post('/support/conversations', [\App\Http\Controllers\LiveSupportController::class, 'createConversation'])->name('api.approval-app.support.create-conversation');
        Route::get('/support/conversations', [\App\Http\Controllers\LiveSupportController::class, 'getUserConversations'])->name('api.approval-app.support.get-conversations');
        Route::get('/support/conversations/{id}/messages', [\App\Http\Controllers\LiveSupportController::class, 'getConversationMessages'])->name('api.approval-app.support.get-messages');
        Route::post('/support/conversations/{id}/messages', [\App\Http\Controllers\LiveSupportController::class, 'sendMessage'])->name('api.approval-app.support.send-message');
        Route::get('/support/conversations/{conversationId}/messages/{messageId}/files/{fileIndex}', [\App\Http\Controllers\LiveSupportController::class, 'serveAttachment'])->name('api.approval-app.support.serve-attachment');
        
        // Admin routes for Support
        Route::get('/support/admin/conversations', [\App\Http\Controllers\LiveSupportController::class, 'getAllConversations'])->name('api.approval-app.support.admin.get-conversations');
        Route::post('/support/admin/conversations/{id}/reply', [\App\Http\Controllers\LiveSupportController::class, 'adminReply'])->name('api.approval-app.support.admin.reply');
        Route::put('/support/admin/conversations/{id}/status', [\App\Http\Controllers\LiveSupportController::class, 'updateConversationStatus'])->name('api.approval-app.support.admin.update-status');
        
        // User Role Settings routes
        Route::get('/user-roles', [\App\Http\Controllers\UserRoleController::class, 'index'])->name('api.approval-app.user-roles.index');
        Route::put('/user-roles/{id}', [\App\Http\Controllers\UserRoleController::class, 'update'])->name('api.approval-app.user-roles.update');
        Route::post('/user-roles/bulk-assign', [\App\Http\Controllers\UserRoleController::class, 'bulkAssign'])->name('api.approval-app.user-roles.bulk-assign');
        
        // Role Management routes
        Route::get('/roles', [\App\Http\Controllers\RoleController::class, 'index'])->name('api.approval-app.roles.index');
        Route::post('/roles', [\App\Http\Controllers\RoleController::class, 'store'])->name('api.approval-app.roles.store');
        Route::put('/roles/{role}', [\App\Http\Controllers\RoleController::class, 'update'])->name('api.approval-app.roles.update');
        Route::delete('/roles/{role}', [\App\Http\Controllers\RoleController::class, 'destroy'])->name('api.approval-app.roles.destroy');
        
        // Activity Log Report routes
        Route::get('/report/activity-log', [\App\Http\Controllers\ReportController::class, 'reportActivityLog'])->name('api.approval-app.report.activity-log');
        
        // User Shift routes
        Route::get('/user-shifts', [\App\Http\Controllers\UserShiftController::class, 'index'])->name('api.approval-app.user-shifts.index');
        Route::post('/user-shifts', [\App\Http\Controllers\UserShiftController::class, 'store'])->name('api.approval-app.user-shifts.store');
        
        // Notification routes
        Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('api.approval-app.notifications.index');
        Route::post('/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('api.approval-app.notifications.mark-read');
        Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('api.approval-app.notifications.mark-all-read');
        Route::get('/notifications/unread-count', [\App\Http\Controllers\NotificationController::class, 'unreadCount'])->name('api.approval-app.notifications.unread-count');
        
        // Food Good Receive routes
        Route::get('/food-good-receives', [\App\Http\Controllers\FoodGoodReceiveController::class, 'index']);
        Route::post('/food-good-receives', [\App\Http\Controllers\FoodGoodReceiveController::class, 'store']);
        Route::get('/food-good-receives/{id}', [\App\Http\Controllers\FoodGoodReceiveController::class, 'show']);
        Route::put('/food-good-receives/{id}', [\App\Http\Controllers\FoodGoodReceiveController::class, 'update']);
        Route::delete('/food-good-receives/{id}', [\App\Http\Controllers\FoodGoodReceiveController::class, 'destroy']);
        Route::post('/food-good-receives/fetch-po', [\App\Http\Controllers\FoodGoodReceiveController::class, 'fetchPO']);
        
        // Member History routes
        Route::get('/member-history/info', [\App\Http\Controllers\Api\MemberHistoryController::class, 'getMemberInfo']);
        Route::get('/member-history/transactions', [\App\Http\Controllers\Api\MemberHistoryController::class, 'getMemberHistory']);
        Route::get('/member-history/order/{orderId}', [\App\Http\Controllers\Api\MemberHistoryController::class, 'getOrderDetail']);
        Route::get('/member-history/preferences', [\App\Http\Controllers\Api\MemberHistoryController::class, 'getMemberPreferences']);
        Route::get('/member-history/vouchers', [\App\Http\Controllers\Api\MemberHistoryController::class, 'getMemberVouchers']);
        Route::get('/member-history/challenges', [\App\Http\Controllers\Api\MemberHistoryController::class, 'getMemberChallenges']);
    });
});

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

// Stock card detail endpoint (requires authentication)
Route::middleware('auth:web')->get('/outlet-inventory/stock-card/detail', [\App\Http\Controllers\OutletInventoryReportController::class, 'getStockCardDetail'])->name('api.outlet-inventory.stock-card.detail');

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

// PR Food routes (CRUD) - for mobile app
Route::middleware(['approval.app.auth'])->group(function () {
    Route::get('/pr-foods', [\App\Http\Controllers\PrFoodController::class, 'index']);
    Route::post('/pr-foods', [\App\Http\Controllers\PrFoodController::class, 'store']);
    Route::get('/pr-foods/{id}', [\App\Http\Controllers\PrFoodController::class, 'show']);
    Route::put('/pr-foods/{id}', [\App\Http\Controllers\PrFoodController::class, 'update']);
    Route::delete('/pr-foods/{id}', [\App\Http\Controllers\PrFoodController::class, 'destroy']);
});

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

// Mobile Member API Routes
Route::prefix('mobile/member')->group(function () {
        // Public routes (no auth required)
        Route::get('/brands', [\App\Http\Controllers\Mobile\Member\BrandController::class, 'index'])->name('api.mobile.member.brands.index');
        Route::get('/brands/{id}', [\App\Http\Controllers\Mobile\Member\BrandController::class, 'show'])->name('api.mobile.member.brands.show');
    Route::get('/rewards', [\App\Http\Controllers\Mobile\Member\RewardController::class, 'index'])->name('api.mobile.member.rewards.index');
    Route::get('/rewards/brands', [\App\Http\Controllers\Mobile\Member\RewardController::class, 'getBrandsForRewards'])->name('api.mobile.member.rewards.brands');
    Route::get('/rewards/brands/{brandId}/outlets', [\App\Http\Controllers\Mobile\Member\RewardController::class, 'getBrandOutletsWithRewards'])->name('api.mobile.member.rewards.brand-outlets');
    Route::post('/rewards/challenge/{challengeId}/claim', [\App\Http\Controllers\Mobile\Member\RewardController::class, 'claimChallengeReward'])->name('api.mobile.member.rewards.claim-challenge')->middleware('auth:sanctum');
    // Serial code validation and redemption (for POS)
    Route::post('/rewards/validate-serial', [\App\Http\Controllers\Mobile\Member\RewardController::class, 'validateSerialCode'])->name('api.mobile.member.rewards.validate-serial');
    Route::post('/rewards/redeem-serial', [\App\Http\Controllers\Mobile\Member\RewardController::class, 'redeemBySerialCode'])->name('api.mobile.member.rewards.redeem-serial');
    Route::post('/rewards/reset-redeemed', [\App\Http\Controllers\Mobile\Member\RewardController::class, 'resetRedeemedAt'])->name('api.mobile.member.rewards.reset-redeemed');
    // Get all member rewards for POS (point rewards, challenge rewards, voucher items)
    Route::get('/rewards/member-pos', [\App\Http\Controllers\Mobile\Member\RewardController::class, 'getMemberRewardsForPos'])->name('api.mobile.member.rewards.member-pos');
    
    // Voucher redemption (for POS - no auth required)
    Route::post('/vouchers/redeem-serial', [\App\Http\Controllers\Mobile\Member\VoucherController::class, 'redeemBySerialCode'])->name('api.mobile.member.vouchers.redeem-serial');
    Route::post('/vouchers/mark-used', [\App\Http\Controllers\Mobile\Member\VoucherController::class, 'markAsUsed'])->name('api.mobile.member.vouchers.mark-used');
    Route::post('/vouchers/rollback-used', [\App\Http\Controllers\Mobile\Member\VoucherController::class, 'rollbackUsed'])->name('api.mobile.member.vouchers.rollback-used');
    Route::get('/banners', [\App\Http\Controllers\Mobile\Member\BannerController::class, 'index'])->name('api.mobile.member.banners.index');
    Route::get('/challenges', [\App\Http\Controllers\Mobile\Member\ChallengeController::class, 'index'])->name('api.mobile.member.challenges.index');
    Route::get('/challenges/{id}', [\App\Http\Controllers\Mobile\Member\ChallengeController::class, 'show'])->name('api.mobile.member.challenges.show');
    Route::post('/challenges/{id}/start', [\App\Http\Controllers\Mobile\Member\ChallengeController::class, 'start'])->name('api.mobile.member.challenges.start')->middleware('auth:sanctum');
    Route::post('/challenges/{id}/refresh', [\App\Http\Controllers\Mobile\Member\ChallengeController::class, 'refresh'])->name('api.mobile.member.challenges.refresh')->middleware('auth:sanctum');
    Route::post('/challenges/update-progress', [\App\Http\Controllers\Mobile\Member\ChallengeController::class, 'updateProgressFromPos'])->name('api.mobile.member.challenges.update-progress');
    
    // Point earning endpoint (called from POS)
    Route::post('/points/earn', [\App\Http\Controllers\Mobile\Member\PointController::class, 'earn'])->name('api.mobile.member.points.earn');
    Route::post('/points/bonus', [\App\Http\Controllers\Mobile\Member\PointController::class, 'earnBonus'])->name('api.mobile.member.points.bonus');
    Route::post('/points/rollback-redemption', [\App\Http\Controllers\Mobile\Member\PointController::class, 'rollbackPointRedemption'])->name('api.mobile.member.points.rollback-redemption');
    // Point history and expiring soon (Auth required)
    Route::get('/points/history', [\App\Http\Controllers\Mobile\Member\PointController::class, 'history'])->name('api.mobile.member.points.history')->middleware('auth:sanctum');
    Route::get('/points/transaction/{transactionId}', [\App\Http\Controllers\Mobile\Member\PointController::class, 'transactionDetail'])->name('api.mobile.member.points.transaction-detail')->middleware('auth:sanctum');
    Route::get('/points/expiring-soon', [\App\Http\Controllers\Mobile\Member\PointController::class, 'expiringSoon'])->name('api.mobile.member.points.expiring-soon')->middleware('auth:sanctum');
    Route::get('/points/expiring-detail', [\App\Http\Controllers\Mobile\Member\PointController::class, 'expiringDetail'])->name('api.mobile.member.points.expiring-detail')->middleware('auth:sanctum');
    Route::get('/faqs', [\App\Http\Controllers\Mobile\Member\FaqController::class, 'index'])->name('api.mobile.member.faqs.index');
    Route::get('/whats-on', [\App\Http\Controllers\Mobile\Member\WhatsOnController::class, 'index'])->name('api.mobile.member.whats-on.index');
    Route::get('/terms-conditions', [\App\Http\Controllers\Mobile\Member\TermConditionController::class, 'index'])->name('api.mobile.member.terms-conditions.index');
    Route::get('/about-us', [\App\Http\Controllers\Mobile\Member\AboutUsController::class, 'index'])->name('api.mobile.member.about-us.index');
    Route::get('/benefits', [\App\Http\Controllers\Mobile\Member\BenefitsController::class, 'index'])->name('api.mobile.member.benefits.index');
    Route::get('/contact-us', [\App\Http\Controllers\Mobile\Member\ContactUsController::class, 'index'])->name('api.mobile.member.contact-us.index');
    
    // App version check (no auth required)
    Route::post('/app/check-version', [\App\Http\Controllers\Mobile\Member\AppVersionController::class, 'checkVersion'])->name('api.mobile.member.app.check-version');
    
    // Auth routes (no auth required)
    Route::get('/auth/member-data', [\App\Http\Controllers\Mobile\Member\AuthController::class, 'getMemberData'])->name('api.mobile.member.auth.member-data');
    Route::post('/auth/register', [\App\Http\Controllers\Mobile\Member\AuthController::class, 'register'])->name('api.mobile.member.auth.register');
    Route::post('/auth/login', [\App\Http\Controllers\Mobile\Member\AuthController::class, 'login'])->name('api.mobile.member.auth.login');
    Route::post('/auth/forgot-password', [\App\Http\Controllers\Mobile\Member\AuthController::class, 'forgotPassword'])->name('api.mobile.member.auth.forgot-password');
    Route::post('/auth/reset-password', [\App\Http\Controllers\Mobile\Member\AuthController::class, 'resetPassword'])->name('api.mobile.member.auth.reset-password');
    Route::post('/auth/resend-verification', [\App\Http\Controllers\Mobile\Member\AuthController::class, 'resendVerificationEmail'])->name('api.mobile.member.auth.resend-verification');
    Route::get('/auth/occupations', [\App\Http\Controllers\Mobile\Member\AuthController::class, 'getOccupations'])->name('api.mobile.member.auth.occupations');
    
    // Debug route - test token validation (NO AUTH - untuk debugging)
    // Support both GET and OPTIONS (for CORS preflight)
    Route::match(['GET', 'OPTIONS'], '/auth/test-token', function (Request $request) {
        // Handle OPTIONS preflight request
        if ($request->getMethod() === 'OPTIONS') {
            return response()->json([], 200)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept');
        }
        
        try {
            $token = $request->bearerToken();
            $authHeader = $request->header('Authorization');
            $allHeaders = $request->headers->all();
            
            $result = [
                'success' => true,
                'has_token' => $token !== null,
                'token_preview' => $token ? substr($token, 0, 30) . '...' : 'no token',
                'token_length' => $token ? strlen($token) : 0,
                'authorization_header' => $authHeader,
                'authorization_header_raw' => $request->server('HTTP_AUTHORIZATION'),
                'all_headers' => $allHeaders,
            ];
            
            // Try to find token in database
            if ($token) {
                $tokenParts = explode('|', $token);
                if (count($tokenParts) === 2) {
                    $tokenId = $tokenParts[0];
                    
                    $dbToken = \DB::table('personal_access_tokens')
                        ->where('id', $tokenId)
                        ->first();
                    
                    $result['token_parsed'] = true;
                    $result['token_id'] = $tokenId;
                    $result['db_token_found'] = $dbToken !== null;
                    
                    if ($dbToken) {
                        $result['db_token_info'] = [
                            'id' => $dbToken->id,
                            'tokenable_id' => $dbToken->tokenable_id,
                            'tokenable_type' => $dbToken->tokenable_type,
                            'name' => $dbToken->name,
                            'last_used_at' => $dbToken->last_used_at,
                            'expires_at' => $dbToken->expires_at,
                            'created_at' => $dbToken->created_at,
                        ];
                        
                        // Try to get member
                        if ($dbToken->tokenable_type === 'App\\Models\\MemberAppsMember') {
                            $member = \App\Models\MemberAppsMember::find($dbToken->tokenable_id);
                            $result['member_found'] = $member !== null;
                            if ($member) {
                                $result['member_info'] = [
                                    'id' => $member->id,
                                    'member_id' => $member->member_id,
                                    'email' => $member->email,
                                    'nama_lengkap' => $member->nama_lengkap,
                                ];
                            }
                        }
                    }
                } else {
                    $result['token_parsed'] = false;
                    $result['error'] = 'Token format invalid - should be {id}|{hash}';
                }
            }
            
            \Log::info('Test Token Debug', $result);
            
            return response()->json($result, 200);
        } catch (\Exception $e) {
            \Log::error('Test Token Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    });
    
    // Debug route - test dengan auth:sanctum (TEMPORARY - hapus setelah fix)
    Route::get('/auth/test-token-auth', function (Request $request) {
        $token = $request->bearerToken();
        $user = $request->user();
        
        \Log::info('Test Token Debug (With Auth)', [
            'has_token' => $token !== null,
            'token_preview' => $token ? substr($token, 0, 30) . '...' : 'no token',
            'user_authenticated' => $user !== null,
            'user_id' => $user ? $user->id : null,
            'user_email' => $user ? $user->email : null,
        ]);
        
        return response()->json([
            'has_token' => $token !== null,
            'token_preview' => $token ? substr($token, 0, 30) . '...' : 'no token',
            'user_authenticated' => $user !== null,
            'user_id' => $user ? $user->id : null,
            'user_email' => $user ? $user->email : null,
        ]);
    })->middleware('auth:sanctum');
    
    // Protected routes (require auth)
    Route::middleware(['auth:sanctum'])->group(function () {
                // Auth
                Route::post('/auth/logout', [\App\Http\Controllers\Mobile\Member\AuthController::class, 'logout'])->name('api.mobile.member.auth.logout');
                Route::get('/auth/me', [\App\Http\Controllers\Mobile\Member\AuthController::class, 'me'])->name('api.mobile.member.auth.me');
                Route::post('/auth/upload-photo', [\App\Http\Controllers\Mobile\Member\AuthController::class, 'uploadPhoto'])->name('api.mobile.member.auth.upload-photo');
                Route::post('/auth/update-notification-preference', [\App\Http\Controllers\Mobile\Member\AuthController::class, 'updateNotificationPreference'])->name('api.mobile.member.auth.update-notification-preference');
                Route::post('/auth/change-password', [\App\Http\Controllers\Mobile\Member\AuthController::class, 'changePassword'])->name('api.mobile.member.auth.change-password');
                Route::post('/auth/change-mobile-number', [\App\Http\Controllers\Mobile\Member\AuthController::class, 'changeMobileNumber'])->name('api.mobile.member.auth.change-mobile-number');
                Route::put('/auth/update-profile', [\App\Http\Controllers\Mobile\Member\AuthController::class, 'updateProfile'])->name('api.mobile.member.auth.update-profile');
        
        // Device Token Management
        Route::post('/device-token/register', [\App\Http\Controllers\Mobile\Member\DeviceTokenController::class, 'register'])->name('api.mobile.member.device-token.register');
        Route::post('/device-token/unregister', [\App\Http\Controllers\Mobile\Member\DeviceTokenController::class, 'unregister'])->name('api.mobile.member.device-token.unregister');
        Route::get('/device-token', [\App\Http\Controllers\Mobile\Member\DeviceTokenController::class, 'index'])->name('api.mobile.member.device-token.index');
        
        // Notifications
        Route::get('/notifications', [\App\Http\Controllers\Mobile\Member\NotificationController::class, 'index'])->name('api.mobile.member.notifications.index');
        Route::get('/notifications/unread-count', [\App\Http\Controllers\Mobile\Member\NotificationController::class, 'unreadCount'])->name('api.mobile.member.notifications.unread-count');
        Route::post('/notifications/{id}/read', [\App\Http\Controllers\Mobile\Member\NotificationController::class, 'markAsRead'])->name('api.mobile.member.notifications.mark-as-read');
        Route::post('/notifications/read-all', [\App\Http\Controllers\Mobile\Member\NotificationController::class, 'markAllAsRead'])->name('api.mobile.member.notifications.mark-all-as-read');
        
        // Member Spending
        Route::get('/spending/rolling-12-month', [\App\Http\Controllers\Mobile\Member\MemberSpendingController::class, 'getRolling12MonthSpending'])->name('api.mobile.member.spending.rolling-12-month');
        Route::get('/spending/monthly-history', [\App\Http\Controllers\Mobile\Member\MemberSpendingController::class, 'getMonthlyHistory'])->name('api.mobile.member.spending.monthly-history');
                Route::get('/vouchers', [\App\Http\Controllers\Mobile\Member\VoucherController::class, 'index'])->name('api.mobile.member.vouchers.index');
                Route::get('/vouchers/store', [\App\Http\Controllers\Mobile\Member\VoucherController::class, 'store'])->name('api.mobile.member.vouchers.store');
                Route::post('/vouchers/purchase', [\App\Http\Controllers\Mobile\Member\VoucherController::class, 'purchase'])->name('api.mobile.member.vouchers.purchase');
                
                // Feedback
                Route::get('/feedback', [\App\Http\Controllers\Mobile\Member\FeedbackController::class, 'index'])->name('api.mobile.member.feedback.index');
                Route::get('/feedback/outlets', [\App\Http\Controllers\Mobile\Member\FeedbackController::class, 'getOutlets'])->name('api.mobile.member.feedback.outlets');
                Route::post('/feedback', [\App\Http\Controllers\Mobile\Member\FeedbackController::class, 'store'])->name('api.mobile.member.feedback.store');
                Route::post('/feedback/{id}/reply', [\App\Http\Controllers\Mobile\Member\FeedbackController::class, 'reply'])->name('api.mobile.member.feedback.reply');
                
                // Outlets
                Route::get('/outlets/nearest', [OutletController::class, 'getNearestOutlets'])->name('api.mobile.member.outlets.nearest');
        
        // TODO: Add more mobile member API routes here
        // - Profile
        // - Points
        // - Vouchers
        // - etc.
    });
});


// POS Order Sync Routes
Route::prefix('pos')->group(function () {
    Route::post('/orders/sync', [PosOrderController::class, 'syncOrder'])->name('api.pos.orders.sync');
    Route::get('/orders/check-exists', [PosOrderController::class, 'checkOrderExists'])->name('api.pos.orders.check-exists');
    Route::post('/orders/rollback-member', [PosOrderController::class, 'rollbackMemberTransaction'])->name('api.pos.orders.rollback-member');
    Route::post('/orders/void', [PosOrderController::class, 'voidOrder'])->name('api.pos.orders.void');
});

// Closing Shift Routes
Route::prefix('closing-shift')->group(function () {
    Route::post('/sync-order', [ClosingShiftController::class, 'syncOrderToPusat'])->name('api.closing-shift.sync-order');
    Route::get('/today-mtd', [ClosingShiftController::class, 'getTodayAndMTDData'])->name('api.closing-shift.today-mtd');
    Route::get('/investors', [ClosingShiftController::class, 'getInvestorsData'])->name('api.closing-shift.investors');
    Route::get('/summary-report', [ClosingShiftController::class, 'getSummaryReport'])->name('api.closing-shift.summary-report');
    Route::get('/retail-food', [ClosingShiftController::class, 'getRetailFoodByShift'])->name('api.closing-shift.retail-food');
    Route::get('/retail-non-food', [ClosingShiftController::class, 'getRetailNonFoodByShift'])->name('api.closing-shift.retail-non-food');
    Route::get('/unsynced-orders-count', [ClosingShiftController::class, 'checkUnsyncedOrdersCount'])->name('api.closing-shift.unsynced-orders-count');
});

// POS Sync Routes
Route::prefix('pos/sync')->group(function () {
    Route::post('/check-changes', [PosSyncController::class, 'checkChanges'])->name('api.pos.sync.check-changes');
    Route::get('/users', [PosSyncController::class, 'syncUsers'])->name('api.pos.sync.users');
    Route::get('/categories', [PosSyncController::class, 'syncCategories'])->name('api.pos.sync.categories');
    Route::get('/sub-categories', [PosSyncController::class, 'syncSubCategories'])->name('api.pos.sync.sub-categories');
    Route::get('/units', [PosSyncController::class, 'syncUnits'])->name('api.pos.sync.units');
    Route::get('/items', [PosSyncController::class, 'syncItems'])->name('api.pos.sync.items');
    Route::get('/item-images', [PosSyncController::class, 'syncItemImages'])->name('api.pos.sync.item-images');
    Route::get('/item-prices', [PosSyncController::class, 'syncItemPrices'])->name('api.pos.sync.item-prices');
    Route::get('/modifiers', [PosSyncController::class, 'syncModifiers'])->name('api.pos.sync.modifiers');
    Route::get('/modifier-options', [PosSyncController::class, 'syncModifierOptions'])->name('api.pos.sync.modifier-options');
    Route::get('/item-modifier-options', [PosSyncController::class, 'syncItemModifierOptions'])->name('api.pos.sync.item-modifier-options');
    Route::get('/promos', [PosSyncController::class, 'syncPromos'])->name('api.pos.sync.promos');
    Route::get('/payment-types', [PosSyncController::class, 'syncPaymentTypes'])->name('api.pos.sync.payment-types');
    Route::get('/reservations', [PosSyncController::class, 'syncReservations'])->name('api.pos.sync.reservations');
    Route::get('/investors', [PosSyncController::class, 'syncInvestors'])->name('api.pos.sync.investors');
    Route::get('/officer-checks', [PosSyncController::class, 'syncOfficerChecks'])->name('api.pos.sync.officer-checks');
    Route::get('/retail-food', [PosSyncController::class, 'syncRetailFood'])->name('api.pos.sync.retail-food');
});

// PR Foods routes - moved to web.php for web app usage

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

// AI Analytics Routes - Moved to web.php for session-based auth
// Route::middleware(['auth:web'])->prefix('ai')->group(function () {
//     Route::get('/insight', [\App\Http\Controllers\AIAnalyticsController::class, 'getAutoInsight'])->name('api.ai.insight');
// });

// Web Profile API Routes (Public - for frontend website)
Route::prefix('web-profile')->group(function () {
    Route::get('/pages', [\App\Http\Controllers\WebProfileController::class, 'apiPages'])->name('api.web-profile.pages');
    Route::get('/pages/{slug}', [\App\Http\Controllers\WebProfileController::class, 'apiPage'])->name('api.web-profile.page');
    Route::get('/pages/{id}/sections', [\App\Http\Controllers\WebProfileController::class, 'apiPageSections'])->name('api.web-profile.page-sections');
    Route::get('/menu', [\App\Http\Controllers\WebProfileController::class, 'apiMenu'])->name('api.web-profile.menu');
    Route::get('/gallery', [\App\Http\Controllers\WebProfileController::class, 'apiGallery'])->name('api.web-profile.gallery');
    Route::get('/banners', [\App\Http\Controllers\WebProfileController::class, 'apiBanners'])->name('api.web-profile.banners');
    Route::get('/brands', [\App\Http\Controllers\WebProfileController::class, 'apiBrands'])->name('api.web-profile.brands');
    Route::get('/settings', [\App\Http\Controllers\WebProfileController::class, 'apiSettings'])->name('api.web-profile.settings');
    Route::post('/contact', [\App\Http\Controllers\WebProfileController::class, 'apiContact'])->name('api.web-profile.contact');
});

