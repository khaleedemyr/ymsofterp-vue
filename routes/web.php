<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\MaintenanceOrderController;
use App\Http\Controllers\ActionPlanController;
use App\Http\Controllers\MaintenanceTaskController;
use App\Http\Controllers\MaintenancePurchaseRequisitionController;
use App\Http\Controllers\MaintenanceBAController;
use App\Http\Controllers\SignatureController;
use App\Http\Controllers\MaintenancePurchaseOrderController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MTPOPaymentController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SubCategoryController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\OutletController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\WarehouseDivisionController;
use App\Http\Controllers\MenuTypeController;
use App\Http\Controllers\OutletMapDashboardController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ModifierController;
use App\Http\Controllers\ModifierOptionController;
use App\Http\Controllers\PrFoodController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\PurchaseOrderFoodsController;
use App\Http\Controllers\ItemBarcodeController;
use App\Http\Controllers\FoodGoodReceiveController;
use App\Http\Controllers\InventoryReportController;
use App\Http\Controllers\ContraBonController;
use App\Http\Controllers\FoodPaymentController;
use App\Http\Controllers\WarehouseTransferController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use App\Http\Controllers\FoodFloorOrderController;
use App\Http\Controllers\FoodStockBalanceController;
use App\Http\Controllers\RepackController;
use App\Http\Controllers\ButcherProcessController;
use App\Http\Controllers\MKProductionController;
use App\Http\Controllers\ScrapperGoogleReviewController;
use App\Http\Controllers\GoogleReviewController;
use App\Http\Controllers\ButcherReportController;
use App\Http\Controllers\StockCostReportController;
use App\Http\Controllers\ButcherAnalysisReportController;
use App\Http\Controllers\InternalUseWasteController;
use App\Http\Controllers\DeliveryOrderController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserRoleController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return redirect('/home');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', function() {
        return redirect()->route('dashboard');
    })->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile', [ProfileController::class, 'update']);
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/home', [\App\Http\Controllers\HomeController::class, 'show'])->name('home');
    
    // Tambahkan route untuk Maintenance Order
    Route::get('/maintenance-order', function () {
        return Inertia::render('MaintenanceOrder/index');
    })->name('maintenance-order');

    Route::post('/signature', [SignatureController::class, 'store'])->name('signature.store');

    Route::get('/dashboard-maintenance', function () {
        return Inertia::render('Dashboard/index');
    })->name('dashboard.maintenance');

    Route::post('/announcement/{id}/publish', [AnnouncementController::class, 'publish'])->name('announcement.publish');
});

// Action Plan Routes
Route::middleware(['auth'])->group(function () {
    Route::post('/api/action-plans', [ActionPlanController::class, 'store']);
    Route::get('/api/action-plans/task/{taskId}', [ActionPlanController::class, 'getByTask']);
    Route::delete('/api/action-plans/media/{mediaId}', [ActionPlanController::class, 'deleteMedia']);
});

Route::post('/maintenance-tasks/{task}/update-status', [MaintenanceTaskController::class, 'updateStatus'])->name('maintenance-tasks.update-status');

Route::get('/api/maintenance-tasks/{task}/timeline', [MaintenanceTaskController::class, 'timeline']);

Route::get('/api/maintenance-tasks/{task}/purchase-requisitions', [MaintenanceTaskController::class, 'purchaseRequisitions']);
Route::post('/api/maintenance-tasks/{task}/purchase-requisitions', [MaintenanceTaskController::class, 'storePurchaseRequisition']);

Route::get('/api/maintenance-purchase-requisitions/generate-number', [MaintenanceTaskController::class, 'generatePrNumber']);

Route::get('/api/units', [MaintenanceTaskController::class, 'getUnits']);

Route::delete('/api/maintenance-purchase-requisitions/{id}', [MaintenanceTaskController::class, 'deletePurchaseRequisition']);

// PR Approval Routes
Route::middleware(['auth'])->group(function () {
    Route::post('/api/maintenance-purchase-requisitions/{id}/approve', [MaintenancePurchaseRequisitionController::class, 'approve']);
    Route::get('/api/maintenance-purchase-requisitions/{id}/approval-status', [MaintenancePurchaseRequisitionController::class, 'getApprovalStatus']);
});

Route::get('/maintenance-ba/{id}/preview', [MaintenanceBAController::class, 'preview'])
    ->middleware(['auth'])
    ->name('maintenance.ba.preview');

Route::get('/maintenance-pr/{id}/preview', [MaintenancePurchaseRequisitionController::class, 'preview'])
    ->middleware(['auth'])
    ->name('maintenance.pr.preview');

Route::get('/maintenance-po/{id}/preview', [MaintenancePurchaseOrderController::class, 'preview'])
    ->middleware(['auth'])
    ->name('maintenance.po.preview');

Route::post('/api/maintenance-tasks/{task}/bidding-items-pdf', [\App\Http\Controllers\MaintenanceTaskController::class, 'exportBiddingItemsPdf']);

Route::post('/api/maintenance-tasks/bidding-offers', [\App\Http\Controllers\MaintenanceTaskController::class, 'storeBiddingOffer']);

Route::get('/api/maintenance-tasks/bidding-offers', [\App\Http\Controllers\MaintenanceTaskController::class, 'getBiddingOffers']);

Route::post('/api/maintenance-tasks/create-po-from-bidding', [\App\Http\Controllers\MaintenanceTaskController::class, 'createPOFromBidding']);

Route::get('/api/maintenance-tasks/bidding-history', [\App\Http\Controllers\MaintenanceTaskController::class, 'getBiddingHistory']);

// MT PO Payment Routes
Route::middleware(['auth', 'verified'])->prefix('mt-po-payment')->name('mt-po-payment.')->group(function () {
    Route::get('/', [MTPOPaymentController::class, 'index'])->name('index');
    Route::get('/history', [MTPOPaymentController::class, 'history'])->name('history');
    // Route lain jika perlu
});

Route::get('/maintenance-schedule', [MaintenanceTaskController::class, 'schedule']);

Route::get('/maintenance-order/schedule-calendar', fn() => inertia('MaintenanceOrder/ScheduleCalendar'));

Route::middleware(['auth'])->group(function () {
    Route::get('/announcement', [AnnouncementController::class, 'index'])->name('announcement.index');
    Route::get('/announcement/create', [AnnouncementController::class, 'create'])->name('announcement.create');
    Route::post('/announcement', [AnnouncementController::class, 'store'])->name('announcement.store');
    Route::get('/announcement/{id}', [AnnouncementController::class, 'show'])->name('announcement.show');
    Route::get('/announcement/{id}/edit', [AnnouncementController::class, 'edit'])->name('announcement.edit');
    Route::put('/announcement/{id}', [AnnouncementController::class, 'update'])->name('announcement.update');
    Route::delete('/announcement/{id}', [AnnouncementController::class, 'destroy'])->name('announcement.destroy');
    Route::resource('categories', CategoryController::class);
    Route::put('/categories/{id}', [App\Http\Controllers\CategoryController::class, 'update']);
    Route::patch('/categories/{id}', [App\Http\Controllers\CategoryController::class, 'update']);
    Route::post('/categories/{id}', [App\Http\Controllers\CategoryController::class, 'update'])->name('categories.update');
    Route::patch('/categories/{id}/toggle-status', [CategoryController::class, 'toggleStatus'])->name('categories.toggle-status');

    // Sub Category resource route
    Route::resource('sub-categories', SubCategoryController::class);
    Route::put('/sub-categories/{id}', [SubCategoryController::class, 'update']);
    Route::patch('/sub-categories/{id}', [SubCategoryController::class, 'update']);
    Route::post('/sub-categories/{id}', [SubCategoryController::class, 'update'])->name('sub-categories.update');
    Route::patch('/sub-categories/{id}/toggle-status', [SubCategoryController::class, 'toggleStatus'])->name('sub-categories.toggle-status');

    Route::resource('units', UnitController::class);
    Route::put('/units/{id}', [UnitController::class, 'update']);
    Route::patch('/units/{id}', [UnitController::class, 'update']);
    Route::post('/units/{id}', [UnitController::class, 'update'])->name('units.update');
    Route::patch('/units/{id}/toggle-status', [UnitController::class, 'toggleStatus'])->name('units.toggle-status');

    // Region routes
    Route::get('/regions', [RegionController::class, 'index'])->name('regions.index');
    Route::post('/regions', [RegionController::class, 'store'])->name('regions.store');
    Route::put('/regions/{id}', [RegionController::class, 'update'])->name('regions.update');
    Route::post('/regions/{id}', [RegionController::class, 'update'])->name('regions.update');
    Route::delete('/regions/{region}', [RegionController::class, 'destroy'])->name('regions.destroy');
    Route::patch('/regions/{region}/toggle-status', [RegionController::class, 'toggleStatus'])->name('regions.toggle-status');

    // Warehouse routes
    Route::get('/warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
    Route::post('/warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
    Route::put('/warehouses/{id}', [WarehouseController::class, 'update'])->name('warehouses.update');
    Route::post('/warehouses/{id}', [WarehouseController::class, 'update'])->name('warehouses.update');
    Route::delete('/warehouses/{id}', [WarehouseController::class, 'destroy'])->name('warehouses.destroy');
    Route::patch('/warehouses/{id}/toggle-status', [WarehouseController::class, 'toggleStatus'])->name('warehouses.toggle-status');

    // Outlet routes
    Route::get('/outlets', [OutletController::class, 'index'])->name('outlets.index');
    Route::post('/outlets', [OutletController::class, 'store'])->name('outlets.store');
    Route::put('/outlets/{id}', [OutletController::class, 'update'])->name('outlets.update');
    Route::post('/outlets/{id}', [OutletController::class, 'update'])->name('outlets.update');
    Route::delete('/outlets/{id}', [OutletController::class, 'destroy'])->name('outlets.destroy');
    Route::patch('/outlets/{id}/toggle-status', [OutletController::class, 'toggleStatus'])->name('outlets.toggle-status');
    Route::get('/outlets/{id}/download-qr', [OutletController::class, 'downloadQr'])->name('outlets.download-qr');
    Route::get('/api/outlets', [\App\Http\Controllers\OutletController::class, 'apiList'])->name('outlets.list');

    // Customer routes
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::put('/customers/{id}', [CustomerController::class, 'update'])->name('customers.update');
    Route::post('/customers/{id}', [CustomerController::class, 'update'])->name('customers.update');
    Route::delete('/customers/{id}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    Route::patch('/customers/{id}/toggle-status', [CustomerController::class, 'toggleStatus'])->name('customers.toggle-status');

    // Warehouse Division routes
    Route::get('/warehouse-divisions', [WarehouseDivisionController::class, 'index'])->name('warehouse-divisions.index');
    Route::post('/warehouse-divisions', [WarehouseDivisionController::class, 'store'])->name('warehouse-divisions.store');
    Route::put('/warehouse-divisions/{id}', [WarehouseDivisionController::class, 'update'])->name('warehouse-divisions.update');
    Route::post('/warehouse-divisions/{id}', [WarehouseDivisionController::class, 'update'])->name('warehouse-divisions.update');
    Route::delete('/warehouse-divisions/{id}', [WarehouseDivisionController::class, 'destroy'])->name('warehouse-divisions.destroy');
    Route::patch('/warehouse-divisions/{id}/toggle-status', [WarehouseDivisionController::class, 'toggleStatus'])->name('warehouse-divisions.toggle-status');

    // Menu Type routes
    Route::get('/menu-types', [MenuTypeController::class, 'index'])->name('menu-types.index');
    Route::post('/menu-types', [MenuTypeController::class, 'store'])->name('menu-types.store');
    Route::put('/menu-types/{id}', [MenuTypeController::class, 'update'])->name('menu-types.update');
    Route::post('/menu-types/{id}', [MenuTypeController::class, 'update'])->name('menu-types.update');
    Route::delete('/menu-types/{id}', [MenuTypeController::class, 'destroy'])->name('menu-types.destroy');
    Route::patch('/menu-types/{id}/toggle-status', [MenuTypeController::class, 'toggleStatus'])->name('menu-types.toggle-status');

    Route::get('/dashboard-outlet', [OutletMapDashboardController::class, 'index'])->name('dashboard.outlet');
    Route::get('/api/outlets/active', [OutletMapDashboardController::class, 'activeOutlets'])->name('api.outlets.active');

    // Purchase Order Foods routes
    Route::get('/po-foods', [PurchaseOrderFoodsController::class, 'index'])->name('po-foods.index');
    Route::get('/po-foods/create', [PurchaseOrderFoodsController::class, 'create'])->name('po-foods.create');
    Route::get('/po-foods/{id}', [PurchaseOrderFoodsController::class, 'show'])->name('po-foods.show');
    Route::get('/po-foods/{id}/edit', [PurchaseOrderFoodsController::class, 'edit'])->name('po-foods.edit');
    Route::put('/po-foods/{id}', [PurchaseOrderFoodsController::class, 'update'])->name('po-foods.update');
    Route::post('/po-foods/{id}/approve', [PurchaseOrderFoodsController::class, 'approvePurchasingManager'])->name('po-foods.approve');
    Route::post('/po-foods/{id}/approve-gm-finance', [PurchaseOrderFoodsController::class, 'approveGMFinance'])->name('po-foods.approve-gm-finance');
    Route::delete('/po-foods/{id}', [PurchaseOrderFoodsController::class, 'destroy'])->name('po-foods.destroy');
    Route::post('/po-foods/{id}/mark-printed', [\App\Http\Controllers\PurchaseOrderFoodsController::class, 'markPrinted'])->name('po-foods.mark-printed');

    // Good Receive routes
    Route::get('/food-good-receive', [FoodGoodReceiveController::class, 'index'])->name('food-good-receive.index');
    Route::post('/food-good-receive/fetch-po', [FoodGoodReceiveController::class, 'fetchPO'])->name('food-good-receive.fetch-po');
    Route::post('/food-good-receive/store', [FoodGoodReceiveController::class, 'store'])->name('food-good-receive.store');
    Route::get('/food-good-receive/{id}', [FoodGoodReceiveController::class, 'show'])->name('food-good-receive.show');
    Route::put('/food-good-receive/{id}', [FoodGoodReceiveController::class, 'update'])->name('food-good-receive.update');

    // Food Payment
    Route::get('/food-payments', [\App\Http\Controllers\FoodPaymentController::class, 'index'])->name('food-payments.index');
    Route::get('/food-payments/create', [\App\Http\Controllers\FoodPaymentController::class, 'create'])->name('food-payments.create');
    Route::post('/food-payments', [\App\Http\Controllers\FoodPaymentController::class, 'store'])->name('food-payments.store');
    Route::get('/food-payments/{id}', [\App\Http\Controllers\FoodPaymentController::class, 'show'])->name('food-payments.show');
    Route::post('/food-payments/{id}/approve', [\App\Http\Controllers\FoodPaymentController::class, 'approve'])->name('food-payments.approve');
    Route::get('/api/food-payments/contra-bon-unpaid', [\App\Http\Controllers\FoodPaymentController::class, 'getContraBonUnpaid']);
    Route::delete('/food-payments/{id}', [\App\Http\Controllers\FoodPaymentController::class, 'destroy'])->name('food-payments.destroy');
});

Route::get('/items/import/template', [ItemController::class, 'downloadImportTemplate'])->name('items.import.template');
Route::get('/items/bom/import/template', [ItemController::class, 'downloadBomImportTemplate'])->name('items.bom.import.template');
Route::post('/items/import/preview', [ItemController::class, 'previewImport'])->name('items.import.preview');
Route::post('/items/bom/import/preview', [ItemController::class, 'previewBomImport'])->name('items.bom.import.preview');
Route::post('/items/import/excel', [ItemController::class, 'importExcel'])->name('items.import.excel');
Route::post('/items/bom/import/excel', [ItemController::class, 'importBom'])->name('items.bom.import.excel');
Route::get('/items/export/excel', [ItemController::class, 'exportExcel'])->name('items.export.excel');
Route::get('/items/export/pdf', [ItemController::class, 'exportPdf'])->name('items.export.pdf');
Route::post('/items/{id}/toggle-status', [ItemController::class, 'toggleStatus'])->name('items.toggleStatus');
Route::get('/api/items/search-for-pr', [ItemController::class, 'searchForPr']);
Route::get('/api/items/last-price', [\App\Http\Controllers\PurchaseOrderFoodsController::class, 'getLastPrice']);
Route::get('/api/inventory/stock', [\App\Http\Controllers\ItemController::class, 'getStock']);
Route::get('/api/items/by-fo-khusus', [App\Http\Controllers\ItemController::class, 'getByFOKhusus']);
Route::get('/api/items/autocomplete-pcs', [ItemController::class, 'autocompletePcs']);
Route::get('/api/items/{id}', [App\Http\Controllers\ItemController::class, 'show']);
Route::get('/api/items/{id}/detail', [App\Http\Controllers\ItemController::class, 'apiDetail']);
Route::get('/items/search-for-warehouse-transfer', [ItemController::class, 'searchForWarehouseTransfer']);
Route::get('/api/items/by-fo-schedule/{fo_schedule_id}', [App\Http\Controllers\ItemController::class, 'getByFOSchedule']);

Route::resource('items', ItemController::class);
Route::resource('modifiers', ModifierController::class);
Route::resource('modifier-options', ModifierOptionController::class);
Route::resource('pr-foods', PrFoodController::class);
Route::post('pr-foods/{id}/approve-ssd-manager', [PrFoodController::class, 'approveSsdManager'])->name('pr-foods.approve-ssd-manager');
Route::post('pr-foods/{id}/approve-vice-coo', [PrFoodController::class, 'approveViceCoo'])->name('pr-foods.approve-vice-coo');

Route::resource('suppliers', SupplierController::class);
Route::patch('suppliers/{id}/toggle-status', [SupplierController::class, 'toggleStatus'])->name('suppliers.toggle-status');
Route::get('/suppliers/import/template', [SupplierController::class, 'downloadImportTemplate'])->name('suppliers.import.template');
Route::post('/suppliers/import/preview', [SupplierController::class, 'previewImport'])->name('suppliers.import.preview');
Route::post('/suppliers/import/excel', [SupplierController::class, 'importExcel'])->name('suppliers.import.excel');

Route::get('/api/suppliers/{id}', [\App\Http\Controllers\Api\SupplierController::class, 'show']);

// PO Foods Routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/po-foods', [PurchaseOrderFoodsController::class, 'index'])->name('po-foods.index');
    Route::get('/po-foods/create', [PurchaseOrderFoodsController::class, 'create'])->name('po-foods.create');
    Route::get('/api/po-foods', [PurchaseOrderFoodsController::class, 'getPOList'])->name('po-foods.list');
    Route::get('/api/pr-foods/available', [PurchaseOrderFoodsController::class, 'getAvailablePR'])->name('pr-foods.available');
    Route::post('/api/pr-foods/items', [PurchaseOrderFoodsController::class, 'getPRItems'])->name('pr-foods.items');
    Route::post('/api/po-foods/generate', [PurchaseOrderFoodsController::class, 'generatePO'])->name('po-foods.generate');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/items/{item}/barcodes', [ItemBarcodeController::class, 'store'])->name('items.barcodes.store');
    Route::delete('/items/{item}/barcodes/{barcode}', [ItemBarcodeController::class, 'destroy'])->name('items.barcodes.destroy');
    Route::get('/items/{item}/barcodes', [ItemBarcodeController::class, 'index'])->name('items.barcodes.index');
});

// Laporan Stok Akhir
Route::get('/inventory/stock-position', [\App\Http\Controllers\InventoryReportController::class, 'stockPosition'])->name('inventory.stock-position');

// Laporan Kartu Stok
Route::get('/inventory/stock-card', [\App\Http\Controllers\InventoryReportController::class, 'stockCard'])->name('inventory.stock-card');

// Laporan Penerimaan Barang
Route::get('/inventory/goods-received-report', [\App\Http\Controllers\InventoryReportController::class, 'goodsReceivedReport'])->name('inventory.goods-received-report');

// Laporan Nilai Persediaan
Route::get('/inventory/inventory-value-report', [\App\Http\Controllers\InventoryReportController::class, 'inventoryValueReport'])->name('inventory.inventory-value-report');

// Laporan Riwayat Perubahan Harga Pokok
Route::get('/inventory/cost-history-report', [\App\Http\Controllers\InventoryReportController::class, 'costHistoryReport'])->name('inventory.cost-history-report');

// Laporan Stok Minimum
Route::get('/inventory/minimum-stock-report', [\App\Http\Controllers\InventoryReportController::class, 'minimumStockReport'])->name('inventory.minimum-stock-report');

// Laporan Rekap Persediaan per Kategori
Route::get('/inventory/category-recap-report', [\App\Http\Controllers\InventoryReportController::class, 'categoryRecapReport'])->name('inventory.category-recap-report');

// Laporan Aging Report
Route::get('/inventory/aging-report', [InventoryReportController::class, 'agingReport'])->name('inventory.aging-report');

// Laporan Stok dan Cost
Route::get('/inventory/stock-cost-report', [\App\Http\Controllers\StockCostReportController::class, 'index'])->name('inventory.stock-cost-report');

// Contra Bon routes
Route::middleware(['auth'])->group(function () {
    Route::get('/contra-bons', [ContraBonController::class, 'index'])->name('contra-bons.index');
    Route::get('/contra-bons/create', [ContraBonController::class, 'create'])->name('contra-bons.create');
    Route::post('/contra-bons', [ContraBonController::class, 'store'])->name('contra-bons.store');
    Route::get('/contra-bons/{id}', [ContraBonController::class, 'show'])->name('contra-bons.show');
    Route::get('/contra-bons/{id}/edit', [ContraBonController::class, 'edit'])->name('contra-bons.edit');
    Route::post('/contra-bons/{id}/approve', [ContraBonController::class, 'approve'])->name('contra-bons.approve');
    Route::get('/api/contra-bon/approved-good-receives', [\App\Http\Controllers\ContraBonController::class, 'getApprovedGoodReceives']);
    Route::get('/api/contra-bon/po-with-approved-gr', [\App\Http\Controllers\ContraBonController::class, 'getPOWithApprovedGR']);
});

// Warehouse Transfer
Route::middleware(['auth'])->group(function () {
    Route::get('/warehouse-transfer', [\App\Http\Controllers\WarehouseTransferController::class, 'index'])->name('warehouse-transfer.index');
    Route::get('/warehouse-transfer/create', [\App\Http\Controllers\WarehouseTransferController::class, 'create'])->name('warehouse-transfer.create');
    Route::post('/warehouse-transfer', [\App\Http\Controllers\WarehouseTransferController::class, 'store'])->name('warehouse-transfer.store');
    Route::get('/warehouse-transfer/{id}', [\App\Http\Controllers\WarehouseTransferController::class, 'show'])->name('warehouse-transfer.show');
    Route::delete('/warehouse-transfer/{id}', [\App\Http\Controllers\WarehouseTransferController::class, 'destroy'])->name('warehouse-transfer.destroy');
    Route::get('/warehouse-transfer/{id}/edit', [\App\Http\Controllers\WarehouseTransferController::class, 'edit'])->name('warehouse-transfer.edit');
    Route::put('/warehouse-transfer/{id}', [\App\Http\Controllers\WarehouseTransferController::class, 'update'])->name('warehouse-transfer.update');
});

Route::resource('item-schedules', App\Http\Controllers\ItemScheduleController::class);

// FO Schedule routes
Route::resource('fo-schedules', App\Http\Controllers\FOScheduleController::class);

Route::get('/floor-order', function () {
    $user = Auth::user()->load('outlet');
    $query = \App\Models\FoodFloorOrder::with(['items', 'outlet', 'requester', 'foSchedule']);
    
    // Jika user bukan dari outlet 1, filter berdasarkan id_outlet user
    if ($user->id_outlet != 1) {
        $query->where('id_outlet', $user->id_outlet);
    }
    
    // Filter tanggal
    $start = Request::get('start_date');
    $end = Request::get('end_date');
    if ($start && $end) {
        $query->whereBetween('created_at', [
            $start . ' 00:00:00',
            $end . ' 23:59:59'
        ]);
    }
    
    $orders = $query->orderByDesc('created_at')->paginate(10);
    
    return Inertia::render('FloorOrder/Index', [
        'user' => $user,
        'floorOrders' => $orders,
        'filters' => [
            'start_date' => $start,
            'end_date' => $end,
        ],
    ]);
})->name('floor-order.index');

Route::get('/floor-order/create', function () {
    $fo_mode = Request::get('fo_mode');
    $input_mode = Request::get('input_mode');
    $user = Auth::user()->load('outlet');
    return Inertia::render('FloorOrder/Form', [
        'fo_mode' => $fo_mode,
        'input_mode' => $input_mode,
        'user' => $user,
    ]);
})->name('floor-order.create');

Route::get('/floor-order/edit/{id}', [FoodFloorOrderController::class, 'edit'])->name('floor-order.edit');
Route::post('/floor-order', [FoodFloorOrderController::class, 'store'])->name('floor-order.store');
Route::put('/floor-order/{id}', [FoodFloorOrderController::class, 'update'])->name('floor-order.update');
Route::delete('/floor-order/{id}', [FoodFloorOrderController::class, 'destroy'])->name('floor-order.destroy');
Route::post('/floor-order/{id}/submit', [FoodFloorOrderController::class, 'submit'])->name('floor-order.submit');
Route::post('/floor-order/{id}/approve', [FoodFloorOrderController::class, 'approve'])->name('floor-order.approve');
Route::post('/api/floor-order/check-exists', [\App\Http\Controllers\FoodFloorOrderController::class, 'checkExists']);
Route::get('/floor-order/{id}', [\App\Http\Controllers\FoodFloorOrderController::class, 'show'])->name('floor-order.show');

Route::resource('packing-list', App\Http\Controllers\PackingListController::class);

Route::get('/api/packing-list/available-items', [\App\Http\Controllers\PackingListController::class, 'availableItems']);
Route::post('/api/packing-list/item-stocks', [\App\Http\Controllers\PackingListController::class, 'itemStocks']);
Route::get('/api/packing-list/summary', [\App\Http\Controllers\PackingListController::class, 'summary']);

// Food Stock Balance Routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/food-stock-balances', [FoodStockBalanceController::class, 'index'])->name('food-stock-balances.index');
    Route::post('/food-stock-balances', [FoodStockBalanceController::class, 'store'])->name('food-stock-balances.store');
    Route::put('/food-stock-balances/{stockBalance}', [FoodStockBalanceController::class, 'update'])->name('food-stock-balances.update');
    Route::delete('/food-stock-balances/{stockBalance}', [FoodStockBalanceController::class, 'destroy'])->name('food-stock-balances.destroy');
    Route::get('/food-stock-balances/download-template', [FoodStockBalanceController::class, 'downloadTemplate'])->name('food-stock-balances.download-template');
    Route::post('/food-stock-balances/preview-import', [FoodStockBalanceController::class, 'previewImport'])->name('food-stock-balances.preview-import');
    Route::post('/food-stock-balances/import', [FoodStockBalanceController::class, 'import'])->name('food-stock-balances.import');
});

Route::resource('repack', RepackController::class);

// Repack specific routes
Route::middleware(['auth'])->group(function () {
    // API routes untuk repack
    Route::get('/api/repack/available-items', [RepackController::class, 'availableItems'])->name('repack.available-items');
    Route::get('/api/repack/item-stocks', [RepackController::class, 'itemStocks'])->name('repack.item-stocks');
    Route::post('/api/repack/generate-number', [RepackController::class, 'generateRepackNumber'])->name('repack.generate-number');
    
    // Approval routes
    Route::post('/repack/{id}/approve', [RepackController::class, 'approve'])->name('repack.approve');
    Route::post('/repack/{id}/reject', [RepackController::class, 'reject'])->name('repack.reject');
    
    // Process routes
    Route::post('/repack/{id}/process', [RepackController::class, 'process'])->name('repack.process');
    Route::post('/repack/{id}/complete', [RepackController::class, 'complete'])->name('repack.complete');
    
    // Report routes
    Route::get('/repack/report', [RepackController::class, 'report'])->name('repack.report');
    Route::get('/repack/export/excel', [RepackController::class, 'exportExcel'])->name('repack.export.excel');
    Route::get('/repack/export/pdf', [RepackController::class, 'exportPdf'])->name('repack.export.pdf');
    Route::get('/repack/{repack}/print-barcodes', [RepackController::class, 'printBarcodes'])->name('repack.print-barcodes');
});

// Butcher Process Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/butcher-processes', [ButcherProcessController::class, 'index'])->name('butcher-processes.index');
    Route::get('/butcher-processes/create', [ButcherProcessController::class, 'create'])->name('butcher-processes.create');
    Route::post('/butcher-processes', [ButcherProcessController::class, 'store'])->name('butcher-processes.store');
    Route::get('/butcher-processes/report', [ButcherReportController::class, 'index'])->name('butcher-processes.report');
    Route::get('/butcher-processes/analysis-report', [\App\Http\Controllers\ButcherAnalysisReportController::class, 'index'])->name('butcher-processes.analysis-report');
    Route::get('/butcher-processes/stock-cost-report', [\App\Http\Controllers\StockCostReportController::class, 'index'])->name('butcher-processes.stock-cost-report');
    Route::get('/butcher-processes/{id}', [ButcherProcessController::class, 'show'])->name('butcher-processes.show');
    Route::delete('/butcher-processes/{id}', [\App\Http\Controllers\ButcherProcessController::class, 'destroy'])->name('butcher-processes.destroy');
});

// MK Production
Route::get('/mk-production', [\App\Http\Controllers\MKProductionController::class, 'index'])->name('mk-production.index');
Route::post('/mk-production/bom', [\App\Http\Controllers\MKProductionController::class, 'getBomAndStock'])->name('mk-production.bom');
Route::post('/mk-production', [\App\Http\Controllers\MKProductionController::class, 'store'])->name('mk-production.store');
Route::get('/mk-production/create', [\App\Http\Controllers\MKProductionController::class, 'create'])->name('mk-production.create');
Route::get('/mk-production/report', [\App\Http\Controllers\MKProductionController::class, 'report'])->name('mk-production.report');
Route::get('/mk-production/{id}', [\App\Http\Controllers\MKProductionController::class, 'show'])->name('mk-production.show');
Route::delete('/mk-production/{id}', [\App\Http\Controllers\MKProductionController::class, 'destroy'])->name('mk-production.destroy');

// Scrapper Google Review

Route::get('/google-review', [GoogleReviewController::class, 'index'])->name('google-review.index');
Route::post('/google-review/fetch', [GoogleReviewController::class, 'scrapeReviews'])->name('google-review.fetch');
Route::get('/scraped-reviews', [GoogleReviewController::class, 'getScrapedReviews']);

Route::get('/internal-use-waste', [InternalUseWasteController::class, 'index'])->name('internal-use-waste.index');
Route::get('/internal-use-waste/create', [InternalUseWasteController::class, 'create'])->name('internal-use-waste.create');
Route::post('/internal-use-waste', [InternalUseWasteController::class, 'store'])->name('internal-use-waste.store');
Route::get('/internal-use-waste/report', [App\Http\Controllers\InternalUseWasteController::class, 'report'])->name('internal-use-waste.report');
Route::get('/internal-use-waste/report-waste-spoil', [App\Http\Controllers\InternalUseWasteController::class, 'reportWasteSpoil'])->name('internal-use-waste.report-waste-spoil');
Route::get('/internal-use-waste/{id}', [InternalUseWasteController::class, 'show'])->name('internal-use-waste.show');
Route::delete('/internal-use-waste/{id}', [InternalUseWasteController::class, 'destroy'])->name('internal-use-waste.destroy');
Route::get('/internal-use-waste/item/{id}/units', [App\Http\Controllers\InternalUseWasteController::class, 'getItemUnits']);



Route::get('/delivery-order', [DeliveryOrderController::class, 'index'])->name('delivery-order.index');
Route::get('/delivery-order/create', [DeliveryOrderController::class, 'create'])->name('delivery-order.create');
Route::post('/delivery-order', [DeliveryOrderController::class, 'store'])->name('delivery-order.store');
Route::get('/delivery-order/{id}', [DeliveryOrderController::class, 'show'])->name('delivery-order.show');
Route::delete('/delivery-order/{id}', [DeliveryOrderController::class, 'destroy'])->name('delivery-order.destroy');

// API untuk fetch item packing list
Route::get('/api/packing-list/{id}/items', [DeliveryOrderController::class, 'getPackingListItems']);

Route::middleware(['auth'])->group(function () {
    Route::get('/menus', [MenuController::class, 'index'])->name('menus.index');
    Route::get('/menus/create', [MenuController::class, 'create'])->name('menus.create');
    Route::post('/menus', [MenuController::class, 'store'])->name('menus.store');
    Route::get('/menus/{menu}/edit', [MenuController::class, 'edit'])->name('menus.edit');
    Route::put('/menus/{menu}', [MenuController::class, 'update'])->name('menus.update');
    Route::delete('/menus/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy');
});

// Role Management
Route::middleware(['auth'])->group(function () {
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
});

Route::get('/user-roles', [\App\Http\Controllers\UserRoleController::class, 'index']);
Route::put('/user-roles/{id}', [\App\Http\Controllers\UserRoleController::class, 'update']);

require __DIR__.'/auth.php';
