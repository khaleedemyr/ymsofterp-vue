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
use App\Http\Controllers\DataLevelController;
use App\Http\Controllers\JabatanController;
use App\Http\Controllers\DivisiController;
use App\Http\Controllers\ManPowerOutletController;
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
use App\Http\Controllers\OpsKitchen\ActionPlanGuestReviewController;
use App\Http\Controllers\CostControlController;
use App\Http\Controllers\WarehouseSaleController;
use App\Http\Controllers\OutletFoodInventoryAdjustmentController;
use App\Http\Controllers\OutletInventoryReportController;
use App\Http\Controllers\OutletStockBalanceController;
use App\Http\Controllers\OutletInternalUseWasteController;
use App\Http\Controllers\RetailFoodController;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\OutletFoodGoodReceiveController;
use App\Http\Controllers\GoodReceiveOutletSupplierController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\OutletPaymentController;
use App\Http\Controllers\NonFoodPaymentController;
use App\Http\Controllers\OutletPaymentSupplierController;
use App\Http\Controllers\PromoController;
use App\Http\Controllers\InvestorController;
use App\Http\Controllers\InvestorPageController;
use App\Http\Controllers\OfficerCheckController;
use App\Http\Controllers\WarehouseOutletController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\PaymentTypeController;
use App\Http\Controllers\RetailWarehouseSaleController;
use App\Http\Controllers\StockCutController;
use App\Http\Controllers\OutletDashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPinController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\UserShiftController;
use App\Http\Controllers\ScheduleAttendanceCorrectionController;
use App\Http\Controllers\AttendanceReportController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\VideoTutorialController;
use App\Http\Controllers\VideoTutorialGroupController;
use App\Http\Controllers\LmsController;
use App\Http\Controllers\LmsCategoryController;
// use App\Http\Controllers\LmsLessonController; // REMOVED - using sessions instead
use App\Http\Controllers\LmsEnrollmentController;
use App\Http\Controllers\LmsQuizController;
use App\Http\Controllers\LmsAssignmentController;
use App\Http\Controllers\LmsCertificateController;
use App\Http\Controllers\LmsDiscussionController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberReportsController;
use App\Http\Controllers\PointManagementController;
use App\Http\Controllers\SharedDocumentController;
use App\Http\Controllers\FoodGoodReceiveReportController;
use App\Http\Controllers\TrainingScheduleController;
use App\Http\Controllers\LmsCurriculumController;
use App\Http\Controllers\JabatanTrainingController;
use App\Http\Controllers\TrainingComplianceController;


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
    Route::patch('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.update-avatar');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar']);
    Route::patch('/profile/documents', [ProfileController::class, 'updateDocuments'])->name('profile.update-documents');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/home', [\App\Http\Controllers\HomeController::class, 'show'])->name('home');
    
    // API route for user announcements
    Route::get('/api/user-announcements', [AnnouncementController::class, 'getUserAnnouncements'])->name('api.user-announcements');
    
    // API routes for user pins
    Route::get('/api/user-pins', [\App\Http\Controllers\UserPinController::class, 'index'])->name('api.user-pins');
    Route::post('/api/user-pins', [\App\Http\Controllers\UserPinController::class, 'store'])->name('api.user-pins.store');
    Route::put('/api/user-pins/{id}', [\App\Http\Controllers\UserPinController::class, 'update'])->name('api.user-pins.update');
    Route::delete('/api/user-pins/{id}', [\App\Http\Controllers\UserPinController::class, 'destroy'])->name('api.user-pins.destroy');
    Route::get('/api/outlets', [\App\Http\Controllers\UserPinController::class, 'getOutlets'])->name('api.outlets');
    
    // Quiz API routes
    Route::post('/api/quiz/start-attempt', [\App\Http\Controllers\QuizController::class, 'startAttempt'])->name('api.quiz.start-attempt');
    Route::post('/api/quiz/submit-attempt', [\App\Http\Controllers\QuizController::class, 'submitAttempt'])->name('api.quiz.submit-attempt');
    Route::get('/api/quiz/results/{attemptId}', [\App\Http\Controllers\QuizController::class, 'getResults'])->name('api.quiz.results');
    
    // Quiz attempt view routes
    Route::get('/lms/quiz/{quizId}/attempt/{attemptId}', [\App\Http\Controllers\QuizController::class, 'showAttempt'])->name('lms.quiz.attempt');
    Route::get('/lms/quiz/results/{attemptId}', [\App\Http\Controllers\QuizController::class, 'showResults'])->name('lms.quiz.results');
    
    // Material file viewer routes
    Route::get('/lms/material/{materialId}/view/{fileId}', [\App\Http\Controllers\TrainingScheduleController::class, 'viewMaterialFile'])->name('lms.material.view');
    
    // Training review routes
    Route::post('/lms/training/review', [\App\Http\Controllers\TrainingScheduleController::class, 'submitReview'])->name('lms.training.review');
    Route::get('/lms/training/{trainingScheduleId}/reviews', [\App\Http\Controllers\TrainingScheduleController::class, 'getTrainingReviews'])->name('lms.training.reviews');
    
    // Questionnaire API routes
    Route::post('/api/questionnaire/start-response', [\App\Http\Controllers\QuestionnaireController::class, 'startResponse'])->name('api.questionnaire.start-response');
    Route::post('/api/questionnaire/submit-response', [\App\Http\Controllers\QuestionnaireController::class, 'submitResponse'])->name('api.questionnaire.submit-response');
    Route::get('/api/questionnaire/results/{responseId}', [\App\Http\Controllers\QuestionnaireController::class, 'getResults'])->name('api.questionnaire.results');
    
    // Training Feedback API routes
    Route::post('/api/training/feedback', [\App\Http\Controllers\FeedbackController::class, 'submitFeedback'])->name('api.training.feedback');
    Route::get('/api/training/feedback/{scheduleId}', [\App\Http\Controllers\FeedbackController::class, 'getFeedback'])->name('api.training.feedback.get');
    Route::get('/api/training/feedback/{scheduleId}/stats', [\App\Http\Controllers\FeedbackController::class, 'getFeedbackStats'])->name('api.training.feedback.stats');
    
    // Training History API routes
    Route::post('/api/training/checkout', [\App\Http\Controllers\TrainingScheduleController::class, 'checkoutTraining'])->name('api.training.checkout');
    Route::get('/api/training/history', [\App\Http\Controllers\TrainingScheduleController::class, 'getUserTrainingHistory'])->name('api.training.history');
    Route::get('/api/training/history/{historyId}', [\App\Http\Controllers\TrainingScheduleController::class, 'getTrainingHistoryDetails'])->name('api.training.history.details');
    
    // Training Material Completion API routes
    Route::post('/api/training/material/complete', [\App\Http\Controllers\TrainingScheduleController::class, 'markMaterialCompleted'])->name('api.training.material.complete');
    
    // Tambahkan route untuk Maintenance Order
    Route::get('/maintenance-order', function () {
        return Inertia::render('MaintenanceOrder/index');
    })->name('maintenance-order');

    // Route untuk Maintenance Order List View
    Route::get('/maintenance-order/list', function () {
        return Inertia::render('MaintenanceOrder/List');
    })->name('maintenance-order.list');

    // Route untuk Maintenance Order Detail
    Route::get('/maintenance-order/{id}', function ($id) {
        return Inertia::render('MaintenanceOrder/Detail', ['id' => $id]);
    })->name('maintenance-order.detail');

    // Route untuk Maintenance Order Edit
    Route::get('/maintenance-order/{id}/edit', function ($id) {
        return Inertia::render('MaintenanceOrder/Edit', ['id' => $id]);
    })->name('maintenance-order.edit');

    Route::post('/signature', [SignatureController::class, 'store'])->name('signature.store');

    Route::get('/dashboard-maintenance', function () {
        return Inertia::render('Dashboard/index');
    })->name('dashboard.maintenance');

    Route::post('/announcement/{id}/publish', [AnnouncementController::class, 'publish'])->name('announcement.publish');

    Route::resource('marketing-visit-checklist', \App\Http\Controllers\MarketingVisitChecklistController::class);
    Route::get('marketing-visit-checklist/{id}/export', [\App\Http\Controllers\MarketingVisitChecklistController::class, 'export'])->name('marketing-visit-checklist.export');
    
    // Coaching routes
    Route::resource('coaching', \App\Http\Controllers\CoachingController::class);
    Route::get('coaching/search-users', [\App\Http\Controllers\CoachingController::class, 'searchUsers'])->name('coaching.search-users');
    Route::post('coaching/{coaching}/approve', [\App\Http\Controllers\CoachingController::class, 'approve'])->name('coaching.approve');
    Route::post('coaching/{coaching}/reject', [\App\Http\Controllers\CoachingController::class, 'reject'])->name('coaching.reject');
});

// API routes for coaching (outside middleware group)
Route::get('/api/coaching/user-sanctions', [\App\Http\Controllers\CoachingController::class, 'getUserActiveSanctions'])->name('coaching.user-sanctions');
Route::get('/api/coaching/pending-approvals', [\App\Http\Controllers\CoachingController::class, 'getPendingApprovals'])->name('coaching.pending-approvals');

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

    // Master Report routes
    Route::get('/master-report', [\App\Http\Controllers\MasterReportController::class, 'index'])->name('master-report.index');
    Route::post('/master-report', [\App\Http\Controllers\MasterReportController::class, 'store'])->name('master-report.store');
    Route::put('/master-report/{id}', [\App\Http\Controllers\MasterReportController::class, 'update'])->name('master-report.update');
    Route::delete('/master-report/{id}', [\App\Http\Controllers\MasterReportController::class, 'destroy'])->name('master-report.destroy');
    Route::patch('/master-report/{id}/toggle-status', [\App\Http\Controllers\MasterReportController::class, 'toggleStatus'])->name('master-report.toggle-status');
    Route::get('/master-report/next-area-code', [\App\Http\Controllers\MasterReportController::class, 'getNextAreaCode'])->name('master-report.next-area-code');

    // Daily Report routes
    Route::get('/daily-report', [\App\Http\Controllers\DailyReportController::class, 'index'])->name('daily-report.index');
    Route::get('/daily-report/create', [\App\Http\Controllers\DailyReportController::class, 'create'])->name('daily-report.create');
    Route::post('/daily-report', [\App\Http\Controllers\DailyReportController::class, 'store'])->name('daily-report.store');
    Route::get('/daily-report/areas', [\App\Http\Controllers\DailyReportController::class, 'getAreas'])->name('daily-report.areas');
    Route::post('/daily-report/upload-documentation', [\App\Http\Controllers\DailyReportController::class, 'uploadDocumentation'])->name('daily-report.upload-documentation');
    Route::get('/daily-report/{id}', [\App\Http\Controllers\DailyReportController::class, 'show'])->name('daily-report.show');
    Route::get('/daily-report/{id}/inspect', [\App\Http\Controllers\DailyReportController::class, 'inspect'])->name('daily-report.inspect');
    Route::post('/daily-report/{id}/auto-save', [\App\Http\Controllers\DailyReportController::class, 'autoSave'])->name('daily-report.auto-save');
    Route::post('/daily-report/{id}/save-area', [\App\Http\Controllers\DailyReportController::class, 'saveArea'])->name('daily-report.save-area');
    Route::post('/daily-report/{id}/skip-area', [\App\Http\Controllers\DailyReportController::class, 'skipArea'])->name('daily-report.skip-area');
    Route::post('/daily-report/{id}/complete', [\App\Http\Controllers\DailyReportController::class, 'completeReport'])->name('daily-report.complete');
    Route::post('/daily-report/{id}/force-complete', [\App\Http\Controllers\DailyReportController::class, 'forceCompleteReport'])->name('daily-report.force-complete');
    Route::get('/daily-report/{id}/post-inspection', [\App\Http\Controllers\DailyReportController::class, 'postInspection'])->name('daily-report.post-inspection');
    Route::post('/daily-report/{id}/save-briefing', [\App\Http\Controllers\DailyReportController::class, 'saveBriefing'])->name('daily-report.save-briefing');
    Route::post('/daily-report/{id}/save-productivity', [\App\Http\Controllers\DailyReportController::class, 'saveProductivity'])->name('daily-report.save-productivity');
    Route::post('/daily-report/{id}/save-visit-table', [\App\Http\Controllers\DailyReportController::class, 'saveVisitTable'])->name('daily-report.save-visit-table');
    Route::post('/daily-report/{id}/save-summary', [\App\Http\Controllers\DailyReportController::class, 'saveSummary'])->name('daily-report.save-summary');
    Route::delete('/daily-report/{id}', [\App\Http\Controllers\DailyReportController::class, 'destroy'])->name('daily-report.destroy');

    // Daily Report Comments routes
    Route::get('/daily-report/{id}/comments', [\App\Http\Controllers\DailyReportCommentController::class, 'index'])->name('daily-report.comments.index');
    Route::post('/daily-report/{id}/comments', [\App\Http\Controllers\DailyReportCommentController::class, 'store'])->name('daily-report.comments.store');
    Route::put('/daily-report/comments/{id}', [\App\Http\Controllers\DailyReportCommentController::class, 'update'])->name('daily-report.comments.update');
    Route::delete('/daily-report/comments/{id}', [\App\Http\Controllers\DailyReportCommentController::class, 'destroy'])->name('daily-report.comments.destroy');
    
    // Daily Report Ticket Integration
    Route::post('/daily-report/{id}/create-ticket', [\App\Http\Controllers\DailyReportController::class, 'createTicketFromConcern'])->name('daily-report.create-ticket');
    Route::post('/daily-report/{id}/convert-to-ticket', [\App\Http\Controllers\DailyReportController::class, 'convertToTicket'])->name('daily-report.convert-to-ticket');
    Route::get('/daily-report/ticket-options', [\App\Http\Controllers\DailyReportController::class, 'getTicketOptions'])->name('daily-report.ticket-options');

    // Ticketing System routes
    Route::get('/tickets', [\App\Http\Controllers\TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [\App\Http\Controllers\TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [\App\Http\Controllers\TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{id}', [\App\Http\Controllers\TicketController::class, 'show'])->name('tickets.show');
    Route::get('/tickets/{id}/edit', [\App\Http\Controllers\TicketController::class, 'edit'])->name('tickets.edit');
    Route::put('/tickets/{id}', [\App\Http\Controllers\TicketController::class, 'update'])->name('tickets.update');
    Route::delete('/tickets/{id}', [\App\Http\Controllers\TicketController::class, 'destroy'])->name('tickets.destroy');
    Route::post('/tickets/from-daily-report', [\App\Http\Controllers\TicketController::class, 'createFromDailyReport'])->name('tickets.from-daily-report');
    
    // Ticket Comments routes
    Route::post('/tickets/{id}/comments', [\App\Http\Controllers\TicketController::class, 'addComment'])->name('tickets.comments.store');
    Route::put('/tickets/comments/{id}', [\App\Http\Controllers\TicketController::class, 'updateComment'])->name('tickets.comments.update');
    Route::delete('/tickets/comments/{id}', [\App\Http\Controllers\TicketController::class, 'deleteComment'])->name('tickets.comments.destroy');
    
    // Ticket API endpoints
    Route::get('/tickets/categories', [\App\Http\Controllers\TicketController::class, 'getCategories'])->name('tickets.categories');
    Route::get('/tickets/priorities', [\App\Http\Controllers\TicketController::class, 'getPriorities'])->name('tickets.priorities');
    Route::get('/tickets/by-area/{areaId}', [\App\Http\Controllers\TicketController::class, 'getTicketsByArea'])->name('tickets.by-area');

    // Purchase Requisition API endpoints (must be before resource routes)
    Route::get('/purchase-requisitions/categories', [\App\Http\Controllers\PurchaseRequisitionController::class, 'getCategories'])->name('purchase-requisitions.categories')->middleware('auth');
    Route::get('/purchase-requisitions/tickets', [\App\Http\Controllers\PurchaseRequisitionController::class, 'getTickets'])->name('purchase-requisitions.tickets')->middleware('auth');
    Route::get('/purchase-requisitions/budget-info', [\App\Http\Controllers\PurchaseRequisitionController::class, 'getBudgetInfo'])->name('purchase-requisitions.budget-info')->middleware('auth');
    Route::get('/purchase-requisitions/approvers', [\App\Http\Controllers\PurchaseRequisitionController::class, 'getApprovers'])->name('purchase-requisitions.approvers')->middleware('auth');
    
    // Purchase Requisition Print (must be before resource routes)
    Route::get('/purchase-requisitions/print-preview', [\App\Http\Controllers\PurchaseRequisitionController::class, 'printPreview'])->name('purchase-requisitions.print-preview')->middleware('auth');
Route::get('/api/purchase-requisitions/pending-approvals', [\App\Http\Controllers\PurchaseRequisitionController::class, 'getPendingApprovals'])->name('purchase-requisitions.pending-approvals')->middleware('auth');
Route::get('/api/purchase-requisitions/{id}/approval-details', [\App\Http\Controllers\PurchaseRequisitionController::class, 'getApprovalDetails'])->name('purchase-requisitions.approval-details')->middleware('auth');

    // PR Tracking Report (must be before resource routes)
    Route::get('/purchase-requisitions/tracking-report', function() {
        return inertia('PurchaseRequisition/PRTrackingReport');
    })->name('purchase-requisitions.tracking-report');

    // Purchase Requisition Ops routes
    Route::get('/purchase-requisitions', [\App\Http\Controllers\PurchaseRequisitionController::class, 'index'])->name('purchase-requisitions.index');
    Route::get('/purchase-requisitions/create', [\App\Http\Controllers\PurchaseRequisitionController::class, 'create'])->name('purchase-requisitions.create');
    Route::post('/purchase-requisitions', [\App\Http\Controllers\PurchaseRequisitionController::class, 'store'])->name('purchase-requisitions.store');
    
    // Test route for debugging
    Route::get('/test-print', function() {
        return response()->json(['message' => 'Test route works']);
    });
    
    // Test print method
    Route::get('/purchase-requisitions/test-print', [\App\Http\Controllers\PurchaseRequisitionController::class, 'testPrint'])->name('purchase-requisitions.test-print');
    
    Route::get('/purchase-requisitions/{purchaseRequisition}', [\App\Http\Controllers\PurchaseRequisitionController::class, 'show'])->name('purchase-requisitions.show');
    Route::get('/purchase-requisitions/{purchaseRequisition}/edit', [\App\Http\Controllers\PurchaseRequisitionController::class, 'edit'])->name('purchase-requisitions.edit');
    Route::put('/purchase-requisitions/{purchaseRequisition}', [\App\Http\Controllers\PurchaseRequisitionController::class, 'update'])->name('purchase-requisitions.update');
    Route::delete('/purchase-requisitions/{purchaseRequisition}', [\App\Http\Controllers\PurchaseRequisitionController::class, 'destroy'])->name('purchase-requisitions.destroy');
    
    // Purchase Requisition Actions
    Route::post('/purchase-requisitions/{purchaseRequisition}/submit', [\App\Http\Controllers\PurchaseRequisitionController::class, 'submit'])->name('purchase-requisitions.submit');
    Route::post('/purchase-requisitions/{purchaseRequisition}/approve', [\App\Http\Controllers\PurchaseRequisitionController::class, 'approve'])->name('purchase-requisitions.approve');
    Route::post('/purchase-requisitions/{purchaseRequisition}/reject', [\App\Http\Controllers\PurchaseRequisitionController::class, 'reject'])->name('purchase-requisitions.reject');
    Route::post('/purchase-requisitions/{purchaseRequisition}/process', [\App\Http\Controllers\PurchaseRequisitionController::class, 'process'])->name('purchase-requisitions.process');
    Route::post('/purchase-requisitions/{purchaseRequisition}/complete', [\App\Http\Controllers\PurchaseRequisitionController::class, 'complete'])->name('purchase-requisitions.complete');
    Route::delete('/purchase-requisitions/{purchaseRequisition}', [\App\Http\Controllers\PurchaseRequisitionController::class, 'destroy'])->name('purchase-requisitions.destroy');
    
    // Purchase Requisition Comments
    Route::post('/purchase-requisitions/{purchaseRequisition}/comments', [\App\Http\Controllers\PurchaseRequisitionController::class, 'addComment'])->name('purchase-requisitions.comments.store');
    
    // Purchase Requisition Attachments
    Route::post('/purchase-requisitions/{purchaseRequisition}/attachments', [\App\Http\Controllers\PurchaseRequisitionController::class, 'uploadAttachment'])->name('purchase-requisitions.attachments.store');
    Route::delete('/purchase-requisitions/attachments/{attachment}', [\App\Http\Controllers\PurchaseRequisitionController::class, 'deleteAttachment'])->name('purchase-requisitions.attachments.destroy');
    Route::get('/purchase-requisitions/attachments/{attachment}/download', [\App\Http\Controllers\PurchaseRequisitionController::class, 'downloadAttachment'])->name('purchase-requisitions.attachments.download');
    Route::get('/purchase-requisitions/attachments/{attachment}/view', [\App\Http\Controllers\PurchaseRequisitionController::class, 'viewAttachment'])->name('purchase-requisitions.attachments.view');

    // Budget Management routes
    Route::prefix('budget-management')->name('budget-management.')->group(function () {
        Route::get('/', [\App\Http\Controllers\BudgetManagementController::class, 'index'])->name('index');
        Route::get('/category/create', [\App\Http\Controllers\BudgetManagementController::class, 'createCategory'])->name('create-category');
        Route::post('/category', [\App\Http\Controllers\BudgetManagementController::class, 'storeCategory'])->name('store-category');
        Route::get('/category/{id}/edit', [\App\Http\Controllers\BudgetManagementController::class, 'editCategory'])->name('edit-category');
        Route::put('/category/{id}', [\App\Http\Controllers\BudgetManagementController::class, 'updateCategory'])->name('update-category');
        Route::delete('/category/{id}', [\App\Http\Controllers\BudgetManagementController::class, 'deleteCategory'])->name('delete-category');
        Route::get('/category/delete', [\App\Http\Controllers\BudgetManagementController::class, 'deleteCategoryPage'])->name('delete-category-page');
        Route::get('/category/{categoryId}/outlet-budgets', [\App\Http\Controllers\BudgetManagementController::class, 'manageOutletBudgets'])->name('manage-outlet-budgets');
        Route::post('/category/{categoryId}/outlet-budgets', [\App\Http\Controllers\BudgetManagementController::class, 'storeOutletBudget'])->name('store-outlet-budget');
        Route::put('/category/{categoryId}/outlet-budgets/{budgetId}', [\App\Http\Controllers\BudgetManagementController::class, 'updateOutletBudget'])->name('update-outlet-budget');
        Route::delete('/category/{categoryId}/outlet-budgets/{budgetId}', [\App\Http\Controllers\BudgetManagementController::class, 'deleteOutletBudget'])->name('delete-outlet-budget');
        Route::post('/category/{categoryId}/bulk-create', [\App\Http\Controllers\BudgetManagementController::class, 'bulkCreateOutletBudgets'])->name('bulk-create-outlet-budgets');
        Route::get('/budget-summary', [\App\Http\Controllers\BudgetManagementController::class, 'getBudgetSummary'])->name('budget-summary');
    });

    // Payment routes
    Route::resource('payments', \App\Http\Controllers\PaymentController::class);
    
    // Payment Attachments
    Route::post('/payments/{payment}/attachments', [\App\Http\Controllers\PaymentController::class, 'uploadAttachment'])->name('payments.attachments.store');
    Route::delete('/payments/attachments/{attachment}', [\App\Http\Controllers\PaymentController::class, 'deleteAttachment'])->name('payments.attachments.destroy');
    Route::get('/payments/attachments/{attachment}/download', [\App\Http\Controllers\PaymentController::class, 'downloadAttachment'])->name('payments.attachments.download');
    Route::get('/payments/attachments/{attachment}/view', [\App\Http\Controllers\PaymentController::class, 'viewAttachment'])->name('payments.attachments.view');

    // Purchase Order Ops routes
    Route::get('/po-ops', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'index'])->name('po-ops.index');
      Route::get('/po-ops/approvers', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'getApprovers'])->name('po-ops.approvers');
    Route::get('/po-ops/create', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'create'])->name('po-ops.create');
    Route::post('/po-ops/generate', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'generatePO'])->name('po-ops.generate');
    Route::get('/po-ops/approvers', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'getApprovers'])->name('po-ops.approvers');
    Route::get('/po-ops/pending-approvals', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'getPendingApprovals'])->name('po-ops.pending-approvals');
    Route::get('/po-ops/pending-approvals/page', function() {
        return inertia('PurchaseOrderOps/PendingApprovals');
    })->name('po-ops.pending-approvals-page');
    
    // Purchase Order Ops Print (must be before {id} routes)
    Route::get('/po-ops/print-preview', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'printPreview'])->name('po-ops.print-preview')->middleware('auth');
    
    Route::get('/po-ops/{id}', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'show'])->name('po-ops.show');
    Route::get('/po-ops/{id}/edit', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'edit'])->name('po-ops.edit');
    Route::put('/po-ops/{id}', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'update'])->name('po-ops.update');
    Route::delete('/po-ops/{id}', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'destroy'])->name('po-ops.destroy');
    
    // Purchase Order Ops Actions
    Route::post('/po-ops/{id}/approve-pm', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'approvePurchasingManager'])->name('po-ops.approve-pm');
    Route::post('/po-ops/{id}/approve-gm', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'approveGMFinance'])->name('po-ops.approve-gm');
    
    // Purchase Order Ops Attachments
    Route::post('/po-ops/{purchaseOrderOps}/attachments', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'uploadAttachment'])->name('po-ops.attachments.store');
    Route::delete('/po-ops/attachments/{attachment}', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'deleteAttachment'])->name('po-ops.attachments.destroy');
    Route::get('/po-ops/attachments/{attachment}/download', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'downloadAttachment'])->name('po-ops.attachments.download');
    Route::get('/po-ops/attachments/{attachment}/view', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'viewAttachment'])->name('po-ops.attachments.view');
    Route::post('/po-ops/{id}/mark-printed', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'markPrinted'])->name('po-ops.mark-printed');
    
    // Purchase Order Ops Approval Flow
    Route::post('/po-ops/{id}/submit-approval', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'submitForApproval'])->name('po-ops.submit-approval');
    Route::post('/po-ops/{id}/approve', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'approve'])->name('po-ops.approve');

});

// Test route outside middleware
Route::get('/test-approvers', [\App\Http\Controllers\PurchaseOrderOpsController::class, 'getApprovers'])->name('test-approvers');

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

    // Data Level routes
    Route::resource('data-levels', DataLevelController::class);
    Route::put('/data-levels/{id}', [DataLevelController::class, 'update']);
    Route::patch('/data-levels/{id}', [DataLevelController::class, 'update']);
    Route::post('/data-levels/{id}', [DataLevelController::class, 'update'])->name('data-levels.update');
    Route::patch('/data-levels/{id}/toggle-status', [DataLevelController::class, 'toggleStatus'])->name('data-levels.toggle-status');

    // Jabatan dropdown and debug routes (PASTIKAN INI DI ATAS resource!)
    Route::get('/jabatans/dropdown-data', [JabatanController::class, 'getDropdownData'])->name('jabatans.dropdown-data');
    Route::get('/jabatans/test-dropdown', function() {
        try {
            $jabatans = \App\Models\Jabatan::where('status', 'A')->count();
            $divisis = \App\Models\Divisi::where('status', 'A')->count();
            $subDivisis = \App\Models\SubDivisi::where('status', 'A')->count();
            $levels = \App\Models\DataLevel::where('status', 'A')->count();
            
            return response()->json([
                'message' => 'Test successful',
                'counts' => [
                    'jabatans' => $jabatans,
                    'divisis' => $divisis,
                    'subDivisis' => $subDivisis,
                    'levels' => $levels
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    })->name('jabatans.test-dropdown');
    Route::get('/jabatans/debug-database', [JabatanController::class, 'debugDatabase'])->name('jabatans.debug-database');

    // Resource route harus SETELAH custom route!
    Route::resource('jabatans', JabatanController::class);
    Route::put('/jabatans/{id}', [JabatanController::class, 'update']);
    Route::patch('/jabatans/{id}', [JabatanController::class, 'update']);
    Route::post('/jabatans/{id}', [JabatanController::class, 'update'])->name('jabatans.update');
    Route::patch('/jabatans/{id}/toggle-status', [JabatanController::class, 'toggleStatus'])->name('jabatans.toggle-status');

    // Divisi routes
    Route::resource('divisis', DivisiController::class);
    Route::patch('/divisis/{id}/toggle-status', [DivisiController::class, 'toggleStatus'])->name('divisis.toggle-status');

    // Man Power Outlet Report
    Route::get('/man-power-outlet', [ManPowerOutletController::class, 'index'])->name('man-power-outlet.index');

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

    // Outlet dropdown and debug routes (PASTIKAN INI DI ATAS resource!)
    Route::get('/outlets/dropdown-data', [OutletController::class, 'getDropdownData'])->name('outlets.dropdown-data');
    Route::get('/outlets/debug-database', [OutletController::class, 'debugDatabase'])->name('outlets.debug-database');

    // Outlet routes
    Route::get('/outlets', [OutletController::class, 'index'])->name('outlets.index');
    Route::get('/api/outlets', [\App\Http\Controllers\OutletController::class, 'apiList'])->name('outlets.list');
    Route::get('/api/outlets/report', [App\Http\Controllers\ReportController::class, 'apiOutlets'])->middleware(['auth']);
Route::get('/api/regions', [App\Http\Controllers\ReportController::class, 'apiRegions'])->middleware(['auth']);
    Route::get('/api/outlets/investor', [\App\Http\Controllers\InvestorController::class, 'outlets'])->middleware(['auth:sanctum']);
    Route::get('/api/outlets/{id}', [\App\Http\Controllers\OutletController::class, 'apiShow'])->name('outlets.show');
    Route::post('/outlets', [OutletController::class, 'store'])->name('outlets.store');
    Route::put('/outlets/{id}', [OutletController::class, 'update'])->name('outlets.update');
    Route::delete('/outlets/{id}', [OutletController::class, 'destroy'])->name('outlets.destroy');
    Route::patch('/outlets/{id}/toggle-status', [OutletController::class, 'toggleStatus'])->name('outlets.toggle-status');
    Route::get('/outlets/{id}/download-qr', [OutletController::class, 'downloadQr'])->name('outlets.download-qr');
   

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

    // PO Report routes
    Route::get('/po-report', [\App\Http\Controllers\PurchaseOrderReportController::class, 'index'])->name('po-report.index');
    Route::get('/po-report/export', [\App\Http\Controllers\PurchaseOrderReportController::class, 'export'])->name('po-report.export');

    // Good Receive routes
    Route::get('/food-good-receive', [FoodGoodReceiveController::class, 'index'])->name('food-good-receive.index');
    Route::post('/food-good-receive/fetch-po', [FoodGoodReceiveController::class, 'fetchPO'])->name('food-good-receive.fetch-po');
    Route::post('/food-good-receive/store', [FoodGoodReceiveController::class, 'store'])->name('food-good-receive.store');
    Route::get('/food-good-receive/{id}', [FoodGoodReceiveController::class, 'show'])->name('food-good-receive.show');
    Route::put('/food-good-receive/{id}', [FoodGoodReceiveController::class, 'update'])->name('food-good-receive.update');
    Route::delete('/food-good-receive/{id}', [FoodGoodReceiveController::class, 'destroy'])->name('food-good-receive.destroy');

    // Food Good Receive Report routes
    Route::get('/food-good-receive-report', [\App\Http\Controllers\FoodGoodReceiveReportController::class, 'index'])->name('food-good-receive.report');
    Route::get('/food-good-receive-report/export', [\App\Http\Controllers\FoodGoodReceiveReportController::class, 'export'])->name('food-good-receive.report.export');

    // Food Payment
    Route::get('/food-payments', [\App\Http\Controllers\FoodPaymentController::class, 'index'])->name('food-payments.index');
    Route::get('/food-payments/create', [\App\Http\Controllers\FoodPaymentController::class, 'create'])->name('food-payments.create');
    Route::post('/food-payments', [\App\Http\Controllers\FoodPaymentController::class, 'store'])->name('food-payments.store');
    Route::get('/food-payments/{id}', [\App\Http\Controllers\FoodPaymentController::class, 'show'])->name('food-payments.show');
    Route::post('/food-payments/{id}/approve', [\App\Http\Controllers\FoodPaymentController::class, 'approve'])->name('food-payments.approve');
    Route::get('/api/food-payments/contra-bon-unpaid', [\App\Http\Controllers\FoodPaymentController::class, 'getContraBonUnpaid']);
    Route::delete('/food-payments/{id}', [\App\Http\Controllers\FoodPaymentController::class, 'destroy'])->name('food-payments.destroy');

Route::get('/items/import/template', [ItemController::class, 'downloadImportTemplate'])->name('items.import.template');
Route::get('/api/items/for-modifier-bom', [App\Http\Controllers\ItemController::class, 'apiForModifierBom']);
Route::get('/items/bom/import/template', [ItemController::class, 'downloadBomImportTemplate'])->name('items.bom.import.template');
Route::post('/items/import/preview', [ItemController::class, 'previewImport'])->name('items.import.preview');
Route::post('/items/bom/import/preview', [ItemController::class, 'previewBomImport'])->name('items.bom.import.preview');
Route::post('/items/import/excel', [ItemController::class, 'importExcel'])->name('items.import.excel');
Route::post('/items/bom/import/excel', [ItemController::class, 'importBom'])->name('items.bom.import.excel');
Route::get('/items/export/excel', [ItemController::class, 'exportExcel'])->name('items.export.excel');
Route::get('/items/export/pdf', [ItemController::class, 'exportPdf'])->name('items.export.pdf');
Route::get('/api/items/search', [ItemController::class, 'search']);
Route::get('/api/items/last-price', [\App\Http\Controllers\PurchaseOrderFoodsController::class, 'getLastPrice']);
Route::get('/api/inventory/stock', [\App\Http\Controllers\ItemController::class, 'getStock']);
Route::get('/api/items/by-fo-khusus', [App\Http\Controllers\ItemController::class, 'getByFOKhusus']);
Route::get('/api/items/autocomplete-pcs', [ItemController::class, 'autocompletePcs']);
Route::get('/api/items/by-supplier', [ItemController::class, 'bySupplier']);
Route::get('/api/items/search-for-pr', [ItemController::class, 'searchForPr']);
Route::post('/items/{id}/toggle-status', [ItemController::class, 'toggleStatus'])->name('items.toggleStatus');

Route::get('/api/items/{id}', [App\Http\Controllers\ItemController::class, 'show']);
Route::get('/items/{id}/detail', [App\Http\Controllers\ItemController::class, 'showDetail'])->name('items.detail');
Route::get('/api/items/{id}/detail', [App\Http\Controllers\ItemController::class, 'apiDetail']);
Route::get('/api/items/{id}/barcodes', [App\Http\Controllers\ItemController::class, 'getItemBarcodes']);

Route::get('/items/search-for-warehouse-transfer', [ItemController::class, 'searchForWarehouseTransfer']);
Route::get('/api/items/by-fo-schedule/{fo_schedule_id}', [App\Http\Controllers\ItemController::class, 'getByFOSchedule']);
Route::get('/items/search-for-outlet-transfer', [ItemController::class, 'searchForOutletTransfer']);
Route::get('/items/search-for-outlet-stock-adjustment', [ItemController::class, 'searchForOutletStockAdjustment']);
Route::get('/items/search-for-internal-warehouse-transfer', [ItemController::class, 'searchForInternalWarehouseTransfer']);

Route::resource('items', ItemController::class);
Route::get('/items/import/template', [ItemController::class, 'downloadImportTemplate'])->name('items.import.template');
Route::post('/items/import/preview', [ItemController::class, 'previewImport'])->name('items.import.preview');
Route::post('/items/import/excel', [ItemController::class, 'importExcel'])->name('items.import.excel');
Route::get('/items/bom/import/template', [ItemController::class, 'downloadBomImportTemplate'])->name('items.bom.import.template');
Route::post('/items/bom/import/preview', [ItemController::class, 'previewBomImport'])->name('items.bom.import.preview');
Route::post('/items/bom/import/excel', [ItemController::class, 'importBom'])->name('items.bom.import.excel');
Route::get('/items/price-update/template', [ItemController::class, 'downloadPriceUpdateTemplate'])->name('items.price-update.template');
Route::post('/items/price-update/preview', [ItemController::class, 'previewPriceUpdate'])->name('items.price-update.preview');
Route::post('/items/price-update/import', [ItemController::class, 'importPriceUpdate'])->name('items.price-update.import');
Route::resource('modifiers', ModifierController::class);
Route::resource('modifier-options', ModifierOptionController::class);
// PR Foods specific routes MUST come BEFORE resource route to avoid conflicts
Route::get('/pr-foods/available', [PurchaseOrderFoodsController::class, 'getAvailablePR']);
Route::post('/pr-foods/items', [PurchaseOrderFoodsController::class, 'getPRItems']);

Route::resource('pr-foods', PrFoodController::class);
Route::post('pr-foods/{id}/approve-assistant-ssd-manager', [PrFoodController::class, 'approveAssistantSsdManager'])->name('pr-foods.approve-assistant-ssd-manager');
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
    // PR Foods routes moved to api.php
    Route::post('/api/po-foods/generate', [PurchaseOrderFoodsController::class, 'generatePO'])->name('po-foods.generate');
    Route::get('/api/po-foods/pending-gm-finance', [PurchaseOrderFoodsController::class, 'getPendingGMFINANCEPOs'])->name('po-foods.pending-gm-finance');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/items/{item}/barcodes', [ItemBarcodeController::class, 'store'])->name('items.barcodes.store');
    Route::delete('/items/{item}/barcodes/{barcode}', [ItemBarcodeController::class, 'destroy'])->name('items.barcodes.destroy');
    Route::get('/items/{item}/barcodes', [ItemBarcodeController::class, 'index'])->name('items.barcodes.index');
});

// Laporan Stok Akhir
Route::get('/inventory/stock-position', [\App\Http\Controllers\InventoryReportController::class, 'stockPosition'])->name('inventory.stock-position');
Route::get('/inventory/stock-position/export', [\App\Http\Controllers\InventoryReportController::class, 'exportStockPosition'])->name('inventory.stock-position.export');

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
    Route::get('/api/contra-bon/retail-food-contra-bon', [\App\Http\Controllers\ContraBonController::class, 'getRetailFoodContraBon']);
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
    
    // Outlet Transfer Routes
    Route::get('/outlet-transfer', [\App\Http\Controllers\OutletTransferController::class, 'index'])->name('outlet-transfer.index');
    Route::get('/outlet-transfer/create', [\App\Http\Controllers\OutletTransferController::class, 'create'])->name('outlet-transfer.create');
    Route::post('/outlet-transfer', [\App\Http\Controllers\OutletTransferController::class, 'store'])->name('outlet-transfer.store');
    Route::get('/outlet-transfer/{id}', [\App\Http\Controllers\OutletTransferController::class, 'show'])->name('outlet-transfer.show');
    Route::delete('/outlet-transfer/{id}', [\App\Http\Controllers\OutletTransferController::class, 'destroy'])->name('outlet-transfer.destroy');
    Route::get('/outlet-transfer/{id}/edit', [\App\Http\Controllers\OutletTransferController::class, 'edit'])->name('outlet-transfer.edit');
    Route::put('/outlet-transfer/{id}', [\App\Http\Controllers\OutletTransferController::class, 'update'])->name('outlet-transfer.update');
    Route::post('/outlet-transfer/{id}/submit', [\App\Http\Controllers\OutletTransferController::class, 'submit'])->name('outlet-transfer.submit');
    Route::post('/outlet-transfer/{id}/approve', [\App\Http\Controllers\OutletTransferController::class, 'approve'])->name('outlet-transfer.approve');
    
    // Internal Warehouse Transfer Routes
    Route::get('/internal-warehouse-transfer', [\App\Http\Controllers\InternalWarehouseTransferController::class, 'index'])->name('internal-warehouse-transfer.index');
    Route::get('/internal-warehouse-transfer/create', [\App\Http\Controllers\InternalWarehouseTransferController::class, 'create'])->name('internal-warehouse-transfer.create');
    Route::post('/internal-warehouse-transfer', [\App\Http\Controllers\InternalWarehouseTransferController::class, 'store'])->name('internal-warehouse-transfer.store');
    Route::get('/internal-warehouse-transfer/{id}', [\App\Http\Controllers\InternalWarehouseTransferController::class, 'show'])->name('internal-warehouse-transfer.show');
    Route::delete('/internal-warehouse-transfer/{id}', [\App\Http\Controllers\InternalWarehouseTransferController::class, 'destroy'])->name('internal-warehouse-transfer.destroy');
    Route::get('/internal-warehouse-transfer/{id}/edit', [\App\Http\Controllers\InternalWarehouseTransferController::class, 'edit'])->name('internal-warehouse-transfer.edit');
    Route::put('/internal-warehouse-transfer/{id}', [\App\Http\Controllers\InternalWarehouseTransferController::class, 'update'])->name('internal-warehouse-transfer.update');
});

Route::resource('item-schedules', App\Http\Controllers\ItemScheduleController::class);

// FO Schedule routes
Route::resource('fo-schedules', App\Http\Controllers\FOScheduleController::class);

Route::get('/floor-order', [\App\Http\Controllers\FoodFloorOrderController::class, 'index'])->name('floor-order.index');

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
Route::get('/floor-order/{id}', [\App\Http\Controllers\FoodFloorOrderController::class, 'show'])->name('floor-order.show');
Route::post('/floor-order', [FoodFloorOrderController::class, 'store'])->name('floor-order.store');
Route::put('/floor-order/{id}', [FoodFloorOrderController::class, 'update'])->name('floor-order.update');
Route::delete('/floor-order/{id}', [FoodFloorOrderController::class, 'destroy'])->name('floor-order.destroy');
Route::post('/floor-order/{id}/submit', [FoodFloorOrderController::class, 'submit'])->name('floor-order.submit');
Route::post('/floor-order/{id}/approve', [FoodFloorOrderController::class, 'approve'])->name('floor-order.approve');
Route::post('/api/floor-order/check-exists', [\App\Http\Controllers\FoodFloorOrderController::class, 'checkExists']);
Route::get('/api/floor-order/supplier-available', [\App\Http\Controllers\FoodFloorOrderController::class, 'supplierAvailable']);

Route::resource('packing-list', App\Http\Controllers\PackingListController::class);

Route::get('/api/packing-list/available-items', [\App\Http\Controllers\PackingListController::class, 'availableItems']);
Route::post('/api/packing-list/item-stocks', [\App\Http\Controllers\PackingListController::class, 'itemStocks']);
Route::get('/api/packing-list/summary', [\App\Http\Controllers\PackingListController::class, 'summary']);
Route::get('/api/packing-list/export-summary', [\App\Http\Controllers\PackingListController::class, 'exportSummary']);
Route::get('/api/packing-list/matrix', [\App\Http\Controllers\PackingListController::class, 'matrix']);
Route::get('/api/packing-list/export-matrix', [\App\Http\Controllers\PackingListController::class, 'exportMatrix']);
Route::get('/api/packing-list/warehouse-divisions', [\App\Http\Controllers\PackingListController::class, 'getWarehouseDivisions']);
Route::get('/api/packing-list/test-matrix-data', [\App\Http\Controllers\PackingListController::class, 'testMatrixData']);
Route::get('/api/packing-list/unpicked-floor-orders', [\App\Http\Controllers\PackingListController::class, 'unpickedFloorOrders']);
Route::get('/api/packing-list/export-unpicked-floor-orders', [\App\Http\Controllers\PackingListController::class, 'exportUnpickedFloorOrders']);

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

// MK Production & Outlet WIP Production
Route::middleware(['auth'])->group(function () {
    // MK Production
    Route::get('/mk-production', [\App\Http\Controllers\MKProductionController::class, 'index'])->name('mk-production.index');
    Route::post('/mk-production/bom', [\App\Http\Controllers\MKProductionController::class, 'getBomAndStock'])->name('mk-production.bom');
    Route::post('/mk-production', [\App\Http\Controllers\MKProductionController::class, 'store'])->name('mk-production.store');
    Route::get('/mk-production/create', [\App\Http\Controllers\MKProductionController::class, 'create'])->name('mk-production.create');
    Route::get('/mk-production/report', [\App\Http\Controllers\MKProductionController::class, 'report'])->name('mk-production.report');
    Route::get('/mk-production/{id}', [\App\Http\Controllers\MKProductionController::class, 'show'])->name('mk-production.show');
    Route::delete('/mk-production/{id}', [\App\Http\Controllers\MKProductionController::class, 'destroy'])->name('mk-production.destroy');
    Route::get('/mk-production/test/bom-data', [\App\Http\Controllers\MKProductionController::class, 'testBomData'])->name('mk-production.test-bom-data');
    
    // Outlet WIP Production
    Route::get('/outlet-wip', [\App\Http\Controllers\OutletWIPController::class, 'index'])->name('outlet-wip.index');
    Route::post('/outlet-wip/bom', [\App\Http\Controllers\OutletWIPController::class, 'getBomAndStock'])->name('outlet-wip.bom');
    Route::post('/outlet-wip', [\App\Http\Controllers\OutletWIPController::class, 'store'])->name('outlet-wip.store');
    Route::get('/outlet-wip/create', [\App\Http\Controllers\OutletWIPController::class, 'create'])->name('outlet-wip.create');
    Route::get('/outlet-wip/report', [\App\Http\Controllers\OutletWIPController::class, 'report'])->name('outlet-wip.report');
    Route::get('/outlet-wip/{id}', [\App\Http\Controllers\OutletWIPController::class, 'show'])->name('outlet-wip.show');
    Route::delete('/outlet-wip/{id}', [\App\Http\Controllers\OutletWIPController::class, 'destroy'])->name('outlet-wip.destroy');
});

// Scrapper Google Review

Route::get('/google-review', [GoogleReviewController::class, 'index'])->name('google-review.index');
Route::post('/google-review/fetch', [GoogleReviewController::class, 'scrapeReviews'])->name('google-review.fetch');
Route::get('/scraped-reviews', [GoogleReviewController::class, 'getScrapedReviews']);

Route::get('/internal-use-waste', [InternalUseWasteController::class, 'index'])->name('internal-use-waste.index');
Route::get('/internal-use-waste/report', [App\Http\Controllers\InternalUseWasteController::class, 'report'])->name('internal-use-waste.report');
Route::get('/internal-use-waste/report-waste-spoil', [App\Http\Controllers\InternalUseWasteController::class, 'reportWasteSpoil'])->name('internal-use-waste.report-waste-spoil');
Route::get('/internal-use-waste/create', [InternalUseWasteController::class, 'create'])->name('internal-use-waste.create');
Route::post('/internal-use-waste', [InternalUseWasteController::class, 'store'])->name('internal-use-waste.store');

Route::get('/internal-use-waste/{id}', [InternalUseWasteController::class, 'show'])->name('internal-use-waste.show');
Route::delete('/internal-use-waste/{id}', [InternalUseWasteController::class, 'destroy'])->name('internal-use-waste.destroy');
Route::get('/internal-use-waste/item/{id}/units', [App\Http\Controllers\InternalUseWasteController::class, 'getItemUnits']);


Route::get('/delivery-order', [DeliveryOrderController::class, 'index'])->name('delivery-order.index');
Route::get('/delivery-order/export', [DeliveryOrderController::class, 'export'])->name('delivery-order.export');
Route::get('/delivery-order/export-summary', [DeliveryOrderController::class, 'exportSummary'])->name('delivery-order.export-summary');
Route::get('/delivery-order/export-detail', [DeliveryOrderController::class, 'exportDetail'])->name('delivery-order.export-detail');
Route::get('/delivery-order/create', [DeliveryOrderController::class, 'create'])->name('delivery-order.create');
Route::post('/delivery-order', [DeliveryOrderController::class, 'store'])->name('delivery-order.store');
Route::get('/delivery-order/{id}', [DeliveryOrderController::class, 'show'])->name('delivery-order.show');
Route::delete('/delivery-order/{id}', [DeliveryOrderController::class, 'destroy'])->name('delivery-order.destroy');

// API untuk fetch item packing list
Route::get('/api/packing-list/{id}/items', [DeliveryOrderController::class, 'getPackingListItems']);
Route::get('/api/ro-supplier-gr/{id}/items', [DeliveryOrderController::class, 'getROSupplierGRItems']);

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
Route::post('/user-roles/bulk-assign', [\App\Http\Controllers\UserRoleController::class, 'bulkAssign']);

Route::resource('food-inventory-adjustment', \App\Http\Controllers\FoodInventoryAdjustmentController::class);
Route::post('/food-inventory-adjustment/{id}/approve', [\App\Http\Controllers\FoodInventoryAdjustmentController::class, 'approve'])->name('food-inventory-adjustment.approve');
Route::post('/food-inventory-adjustment/{id}/reject', [\App\Http\Controllers\FoodInventoryAdjustmentController::class, 'reject'])->name('food-inventory-adjustment.reject');

// Dynamic Inspection Routes
Route::resource('dynamic-inspections', \App\Http\Controllers\DynamicInspectionController::class);
Route::post('dynamic-inspections/store-subject', [\App\Http\Controllers\DynamicInspectionController::class, 'storeSubject'])->name('dynamic-inspections.store-subject');
Route::post('dynamic-inspections/{dynamicInspection}/update-subject', [\App\Http\Controllers\DynamicInspectionController::class, 'updateSubject'])->name('dynamic-inspections.update-subject');
Route::post('dynamic-inspections/{dynamicInspection}/complete', [\App\Http\Controllers\DynamicInspectionController::class, 'complete'])->name('dynamic-inspections.complete');

Route::post('/outlet-food-good-receives/scan', [\App\Http\Controllers\OutletFoodGoodReceiveController::class, 'storeScan'])->name('outlet-food-good-receives.scan');
Route::get('/outlet-food-good-receives/available-dos', [\App\Http\Controllers\OutletFoodGoodReceiveController::class, 'availableDOs']);
Route::resource('outlet-food-good-receives', \App\Http\Controllers\OutletFoodGoodReceiveController::class);
Route::get('/outlet-food-good-receives/scan-do', [\App\Http\Controllers\OutletFoodGoodReceiveController::class, 'scanDO'])->name('outlet-food-good-receives.scan-do');
Route::get('/api/delivery-orders/validate', [\App\Http\Controllers\OutletFoodGoodReceiveController::class, 'validateDO']);
Route::get('/outlet-food-good-receives/create-from-do/{delivery_order_id}', [\App\Http\Controllers\OutletFoodGoodReceiveController::class, 'createFromDO'])->name('outlet-food-good-receives.create-from-do');
Route::get('/outlet-food-good-receives/do-detail/{do_id}', [OutletFoodGoodReceiveController::class, 'doDetail']);
Route::post('/outlet-food-good-receives/{outlet_food_good_receive}/submit', [OutletFoodGoodReceiveController::class, 'submit']);
Route::post('/outlet-food-good-receives/{outlet_food_good_receive}/process-stock', [OutletFoodGoodReceiveController::class, 'processStock']);

Route::prefix('ops-kitchen')->group(function () {
    Route::get('/action-plan-guest-review', [ActionPlanGuestReviewController::class, 'index'])->name('ops-kitchen.action-plan-guest-review.index');
    Route::get('/action-plan-guest-review/create', [ActionPlanGuestReviewController::class, 'create'])->name('ops-kitchen.action-plan-guest-review.create');
    Route::post('/action-plan-guest-review', [ActionPlanGuestReviewController::class, 'store'])->name('ops-kitchen.action-plan-guest-review.store');
    Route::get('/action-plan-guest-review/{id}', [ActionPlanGuestReviewController::class, 'show'])->name('ops-kitchen.action-plan-guest-review.show');
    Route::get('/action-plan-guest-review/{id}/edit', [ActionPlanGuestReviewController::class, 'edit'])->name('ops-kitchen.action-plan-guest-review.edit');
    Route::put('/action-plan-guest-review/{id}', [ActionPlanGuestReviewController::class, 'update'])->name('ops-kitchen.action-plan-guest-review.update');
    Route::delete('/action-plan-guest-review/{id}', [ActionPlanGuestReviewController::class, 'destroy'])->name('ops-kitchen.action-plan-guest-review.destroy');
});

Route::get('/inventory/po-price-change-report', [CostControlController::class, 'poPriceChangeReport'])->name('po_price_change_report.index');

// Warehouse Sales Routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/warehouse-sales', [WarehouseSaleController::class, 'index'])->name('warehouse-sales.index');
    Route::get('/warehouse-sales/create', [WarehouseSaleController::class, 'create'])->name('warehouse-sales.create');
    Route::post('/warehouse-sales', [WarehouseSaleController::class, 'store'])->name('warehouse-sales.store');
    Route::get('/warehouse-sales/{warehouseSale}', [WarehouseSaleController::class, 'show'])->name('warehouse-sales.show');
    Route::delete('/warehouse-sales/{warehouseSale}', [WarehouseSaleController::class, 'destroy'])->name('warehouse-sales.destroy');
    Route::get('/api/warehouse-sales/item-price', [WarehouseSaleController::class, 'getItemPrice']);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/butcher-summary-report', [\App\Http\Controllers\ButcherReportController::class, 'summaryReport'])->name('butcher-summary-report.index');
});

// Outlet Food Inventory Adjustment Routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/outlet-food-inventory-adjustment', [OutletFoodInventoryAdjustmentController::class, 'index'])->name('outlet-food-inventory-adjustment.index');
    Route::get('/outlet-food-inventory-adjustment/create', [OutletFoodInventoryAdjustmentController::class, 'create'])->name('outlet-food-inventory-adjustment.create');
    Route::post('/outlet-food-inventory-adjustment', [OutletFoodInventoryAdjustmentController::class, 'store'])->name('outlet-food-inventory-adjustment.store');
    Route::get('/outlet-food-inventory-adjustment/{id}', [OutletFoodInventoryAdjustmentController::class, 'show'])->name('outlet-food-inventory-adjustment.show');
    Route::post('/outlet-food-inventory-adjustment/{id}/approve', [OutletFoodInventoryAdjustmentController::class, 'approve'])->name('outlet-food-inventory-adjustment.approve');
    Route::post('/outlet-food-inventory-adjustment/{id}/reject', [OutletFoodInventoryAdjustmentController::class, 'reject'])->name('outlet-food-inventory-adjustment.reject');
    Route::delete('/outlet-food-inventory-adjustment/{id}', [OutletFoodInventoryAdjustmentController::class, 'destroy'])->name('outlet-food-inventory-adjustment.destroy');
Route::get('/api/outlet-food-inventory-adjustment/warehouse-outlets', [OutletFoodInventoryAdjustmentController::class, 'getWarehouseOutlets'])->name('outlet-food-inventory-adjustment.warehouse-outlets');
});

// Laporan Stok Akhir Outlet
Route::get('/outlet-inventory/stock-position', [\App\Http\Controllers\OutletInventoryReportController::class, 'stockPosition'])->name('outlet-inventory.stock-position');

// Saldo Awal Stok Outlet
Route::get('/outlet-stock-balances', [\App\Http\Controllers\OutletStockBalanceController::class, 'index'])->name('outlet-stock-balances.index');
Route::post('/outlet-stock-balances', [\App\Http\Controllers\OutletStockBalanceController::class, 'store'])->name('outlet-stock-balances.store');
Route::put('/outlet-stock-balances/{id}', [\App\Http\Controllers\OutletStockBalanceController::class, 'update'])->name('outlet-stock-balances.update');
Route::delete('/outlet-stock-balances/{id}', [\App\Http\Controllers\OutletStockBalanceController::class, 'destroy'])->name('outlet-stock-balances.destroy');
Route::post('/outlet-stock-balances/import', [\App\Http\Controllers\OutletStockBalanceController::class, 'import'])->name('outlet-stock-balances.import');
Route::post('/outlet-stock-balances/preview-import', [\App\Http\Controllers\OutletStockBalanceController::class, 'previewImport'])->name('outlet-stock-balances.preview-import');
Route::get('/outlet-stock-balances/download-template', [\App\Http\Controllers\OutletStockBalanceController::class, 'downloadTemplate'])->name('outlet-stock-balances.download-template');

Route::get('/outlet-inventory/stock-card', [\App\Http\Controllers\OutletInventoryReportController::class, 'stockCard'])->name('outlet-inventory.stock-card');
Route::get('/outlet-inventory/inventory-value-report', [\App\Http\Controllers\OutletInventoryReportController::class, 'inventoryValueReport'])->name('outlet-inventory.inventory-value-report');
Route::get('/outlet-inventory/category-recap-report', [\App\Http\Controllers\OutletInventoryReportController::class, 'categoryRecapReport'])->name('outlet-inventory.category-recap-report');

Route::get('/outlet-internal-use-waste', [\App\Http\Controllers\OutletInternalUseWasteController::class, 'index'])->name('outlet-internal-use-waste.index');
Route::get('/outlet-internal-use-waste/create', [\App\Http\Controllers\OutletInternalUseWasteController::class, 'create'])->name('outlet-internal-use-waste.create');
Route::post('/outlet-internal-use-waste', [\App\Http\Controllers\OutletInternalUseWasteController::class, 'store'])->name('outlet-internal-use-waste.store');
Route::get('/outlet-internal-use-waste/report', [\App\Http\Controllers\OutletInternalUseWasteController::class, 'report'])->name('outlet-internal-use-waste.report');
Route::get('/outlet-internal-use-waste/report-waste-spoil', [\App\Http\Controllers\OutletInternalUseWasteController::class, 'reportWasteSpoil'])->name('outlet-internal-use-waste.report-waste-spoil');
Route::get('/outlet-internal-use-waste/report-universal', [\App\Http\Controllers\OutletInternalUseWasteController::class, 'reportUniversal'])->name('outlet-internal-use-waste.report-universal');
Route::get('/outlet-internal-use-waste/{id}/details', [\App\Http\Controllers\OutletInternalUseWasteController::class, 'details']);
Route::get('/outlet-internal-use-waste/{id}', [\App\Http\Controllers\OutletInternalUseWasteController::class, 'show'])->name('outlet-internal-use-waste.show');
Route::delete('/outlet-internal-use-waste/{id}', [\App\Http\Controllers\OutletInternalUseWasteController::class, 'destroy'])->name('outlet-internal-use-waste.destroy');
Route::get('/outlet-internal-use-waste/get-item-units/{id}', [\App\Http\Controllers\OutletInternalUseWasteController::class, 'getItemUnits'])->name('outlet-internal-use-waste.get-item-units');

// Regional Routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/regional', [\App\Http\Controllers\RegionalController::class, 'index'])->name('regional.index');
    Route::get('/regional/create', [\App\Http\Controllers\RegionalController::class, 'create'])->name('regional.create');
    Route::post('/regional', [\App\Http\Controllers\RegionalController::class, 'store'])->name('regional.store');
    Route::get('/regional/{id}/edit', [\App\Http\Controllers\RegionalController::class, 'edit'])->name('regional.edit');
    Route::put('/regional/{id}', [\App\Http\Controllers\RegionalController::class, 'update'])->name('regional.update');
    Route::delete('/regional/{id}', [\App\Http\Controllers\RegionalController::class, 'destroy'])->name('regional.destroy');
        Route::get('/api/regional/user-outlets/{userId}', [\App\Http\Controllers\RegionalController::class, 'getUserOutlets'])->name('regional.user-outlets');
        Route::get('/api/regional/search-users', [\App\Http\Controllers\RegionalController::class, 'searchUsers'])->name('regional.search-users');
});

// Outlet Rejection Routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/outlet-rejections', [\App\Http\Controllers\OutletRejectionController::class, 'index'])->name('outlet-rejections.index');
    Route::get('/outlet-rejections/create', [\App\Http\Controllers\OutletRejectionController::class, 'create'])->name('outlet-rejections.create');
    Route::post('/outlet-rejections', [\App\Http\Controllers\OutletRejectionController::class, 'store'])->name('outlet-rejections.store');
    Route::get('/outlet-rejections/{outletRejection}', [\App\Http\Controllers\OutletRejectionController::class, 'show'])->name('outlet-rejections.show');
    Route::get('/outlet-rejections/{outletRejection}/edit', [\App\Http\Controllers\OutletRejectionController::class, 'edit'])->name('outlet-rejections.edit');
    Route::put('/outlet-rejections/{outletRejection}', [\App\Http\Controllers\OutletRejectionController::class, 'update'])->name('outlet-rejections.update');
    Route::delete('/outlet-rejections/{outletRejection}', [\App\Http\Controllers\OutletRejectionController::class, 'destroy'])->name('outlet-rejections.destroy');
    
    // Workflow actions
    Route::post('/outlet-rejections/{outletRejection}/approve-assistant-ssd-manager', [\App\Http\Controllers\OutletRejectionController::class, 'approveAssistantSsdManager'])->name('outlet-rejections.approve-assistant-ssd-manager');
Route::post('/outlet-rejections/{outletRejection}/approve-ssd-manager', [\App\Http\Controllers\OutletRejectionController::class, 'approveSsdManager'])->name('outlet-rejections.approve-ssd-manager');
Route::post('/outlet-rejections/{outletRejection}/cancel', [\App\Http\Controllers\OutletRejectionController::class, 'cancel'])->name('outlet-rejections.cancel');
    
    // API endpoints
    Route::get('/outlet-rejections/api/items', [\App\Http\Controllers\OutletRejectionController::class, 'getItems'])->name('outlet-rejections.api.items');
    Route::get('/outlet-rejections/api/delivery-order-items', [\App\Http\Controllers\OutletRejectionController::class, 'getDeliveryOrderItems'])->name('outlet-rejections.api.delivery-order-items');
    Route::get('/outlet-rejections/api/filtered-delivery-orders', [\App\Http\Controllers\OutletRejectionController::class, 'getFilteredDeliveryOrders'])->name('outlet-rejections.api.filtered-delivery-orders');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('retail-food', RetailFoodController::class);
    Route::get('retail-food/get-item-units/{itemId}', [\App\Http\Controllers\RetailFoodController::class, 'getItemUnits']);
    Route::post('retail-food/get-budget-info', [\App\Http\Controllers\RetailFoodController::class, 'getBudgetInfo']);
    Route::post('retail-food/debug-budget-query', [\App\Http\Controllers\RetailFoodController::class, 'debugBudgetQuery']);

    Route::resource('retail-non-food', \App\Http\Controllers\RetailNonFoodController::class);
    Route::get('retail-non-food/daily-total', [\App\Http\Controllers\RetailNonFoodController::class, 'dailyTotal']);
    
    // Outlet Food Return
    Route::resource('outlet-food-return', \App\Http\Controllers\OutletFoodReturnController::class);
    Route::get('outlet-food-return/get-warehouse-outlets', [\App\Http\Controllers\OutletFoodReturnController::class, 'getWarehouseOutlets'])->name('outlet-food-return.get-warehouse-outlets');
    Route::get('outlet-food-return/get-good-receives', [\App\Http\Controllers\OutletFoodReturnController::class, 'getGoodReceives'])->name('outlet-food-return.get-good-receives');
    Route::get('outlet-food-return/get-good-receive-items', [\App\Http\Controllers\OutletFoodReturnController::class, 'getGoodReceiveItems'])->name('outlet-food-return.get-good-receive-items');
    Route::post('outlet-food-return/{id}/approve', [\App\Http\Controllers\OutletFoodReturnController::class, 'approve'])->name('outlet-food-return.approve');
    
    // Head Office Return Management
    Route::resource('head-office-return', \App\Http\Controllers\HeadOfficeReturnController::class);
    Route::post('head-office-return/{id}/approve', [\App\Http\Controllers\HeadOfficeReturnController::class, 'approve'])->name('head-office-return.approve');
    Route::post('head-office-return/{id}/reject', [\App\Http\Controllers\HeadOfficeReturnController::class, 'reject'])->name('head-office-return.reject');
});

Route::resource('item-supplier', \App\Http\Controllers\ItemSupplierController::class);

// API autocomplete untuk Item Supplier
Route::get('/api/suppliers', [\App\Http\Controllers\Api\SupplierController::class, 'index']);
Route::get('/api/items', [\App\Http\Controllers\Api\ItemController::class, 'index']);

Route::get('/test-email', function() {
    try {
        Mail::raw('Test email dari YM Soft ERP', function($message) {
            $message->to('ymsofterp@gmail.com')
                   ->subject('Test Email');
        });
        return 'Email berhasil dikirim!';
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});



// Index, create, store, show, delete, fetch RO, dsb
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/good-receive-outlet-supplier', [GoodReceiveOutletSupplierController::class, 'index'])->name('good-receive-outlet-supplier.index');
Route::get('/good-receive-outlet-supplier/create', [GoodReceiveOutletSupplierController::class, 'create'])->name('good-receive-outlet-supplier.create');

// MAC Report Routes
Route::get('/mac-report', [App\Http\Controllers\MacReportController::class, 'index'])->name('mac-report.index');
Route::post('/mac-report/export', [App\Http\Controllers\MacReportController::class, 'export'])->name('mac-report.export');
    Route::get('/good-receive-outlet-supplier/{id}', [GoodReceiveOutletSupplierController::class, 'show'])->name('good-receive-outlet-supplier.show');
    Route::post('/good-receive-outlet-supplier', [GoodReceiveOutletSupplierController::class, 'store'])->name('good-receive-outlet-supplier.store');
    Route::delete('/good-receive-outlet-supplier/{id}', [GoodReceiveOutletSupplierController::class, 'destroy'])->name('good-receive-outlet-supplier.destroy');
    Route::post('/good-receive-outlet-supplier/fetch-ro', [GoodReceiveOutletSupplierController::class, 'fetchRO'])->name('good-receive-outlet-supplier.fetch-ro');
Route::get('/good-receive-outlet-supplier/available-delivery-orders', [GoodReceiveOutletSupplierController::class, 'getAvailableDeliveryOrders'])->name('good-receive-outlet-supplier.available-delivery-orders');
Route::get('/good-receive-outlet-supplier/create-from-do/{delivery_order_id}', [GoodReceiveOutletSupplierController::class, 'createFromDeliveryOrder'])->name('good-receive-outlet-supplier.create-from-do');
Route::post('/good-receive-outlet-supplier/store-from-do', [GoodReceiveOutletSupplierController::class, 'storeFromDeliveryOrder'])->name('good-receive-outlet-supplier.store-from-do');
});

// API untuk dropdown RO Supplier (jika perlu)
Route::get('/api/ro-suppliers', [GoodReceiveOutletSupplierController::class, 'getROSuppliers']);
Route::get('/api/delivery-orders-ro-supplier', [GoodReceiveOutletSupplierController::class, 'getAvailableDeliveryOrders']);

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/report-sales-per-category', [ReportController::class, 'reportSalesPerCategory'])->name('report.sales-per-category');
    Route::get('/report-sales-per-tanggal', [\App\Http\Controllers\ReportController::class, 'reportSalesPerTanggal'])->name('report.sales-per-tanggal');
    Route::get('/report-sales-all-item-all-outlet', [\App\Http\Controllers\ReportController::class, 'reportSalesAllItemAllOutlet'])->name('report.sales-all-item-all-outlet');
    Route::get('/report-sales-all-item-all-outlet/export', [\App\Http\Controllers\ReportController::class, 'exportSalesAllItemAllOutlet'])->name('report.sales-all-item-all-outlet.export');
    Route::get('/report-sales-pivot-per-outlet-sub-category', [\App\Http\Controllers\ReportController::class, 'reportSalesPivotPerOutletSubCategory'])->name('report.sales-pivot-per-outlet-sub-category');
Route::get('/report-sales-pivot-per-outlet-sub-category/export', [\App\Http\Controllers\ReportController::class, 'exportSalesPivotPerOutletSubCategory'])->name('report.sales-pivot-per-outlet-sub-category.export');
    Route::get('/report-sales-pivot-special', [\App\Http\Controllers\ReportController::class, 'reportSalesPivotSpecial'])->name('report.sales-pivot-special');
Route::get('/report-sales-pivot-special/export', [\App\Http\Controllers\ReportController::class, 'exportSalesPivotSpecial'])->name('report.sales-pivot-special.export');
Route::get('/report-rekap-fj', [\App\Http\Controllers\ReportController::class, 'reportSalesPivotSpecial'])->name('report.rekap-fj');
Route::get('/report-rekap-fj/export', [\App\Http\Controllers\ReportController::class, 'exportSalesPivotSpecial'])->name('report.rekap-fj.export');
    Route::get('/report-good-receive-outlet', [\App\Http\Controllers\ReportController::class, 'reportGoodReceiveOutlet'])->name('report.good-receive-outlet');
    Route::get('/report-good-receive-outlet/export', [\App\Http\Controllers\ReportController::class, 'exportGoodReceiveOutlet'])->name('report.good-receive-outlet.export');
    Route::get('/report-receiving-sheet', [\App\Http\Controllers\ReportController::class, 'reportReceivingSheet'])->name('report.receiving-sheet');
    Route::post('/report/sales-pivot-outlet-detail', [\App\Http\Controllers\ReportController::class, 'salesPivotOutletDetail'])->name('report.sales-pivot-outlet-detail');
    Route::post('/report/retail-sales-detail', [\App\Http\Controllers\ReportController::class, 'retailSalesDetail'])->name('report.retail-sales-detail');
Route::post('/report/warehouse-sales-detail', [\App\Http\Controllers\ReportController::class, 'warehouseSalesDetail'])->name('report.warehouse-sales-detail');
    Route::post('/api/report/fj-detail', [\App\Http\Controllers\ReportController::class, 'fjDetail'])->name('report.fj-detail');
    Route::post('/api/report/fj-detail-pdf', [\App\Http\Controllers\ReportController::class, 'fjDetailPdf'])->name('report.fj-detail-pdf');
    Route::post('/api/report/retail-detail-pdf', [\App\Http\Controllers\ReportController::class, 'retailDetailPdf'])->name('report.retail-detail-pdf');
    Route::post('/api/report/warehouse-detail-pdf', [\App\Http\Controllers\ReportController::class, 'warehouseDetailPdf'])->name('report.warehouse-detail-pdf');
});

// Outlet Payments
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/outlet-payments', [OutletPaymentController::class, 'index'])->name('outlet-payments.index');
    Route::get('/outlet-payments/create', [\App\Http\Controllers\OutletPaymentController::class, 'create'])->name('outlet-payments.create');
    Route::post('/outlet-payments', [OutletPaymentController::class, 'store'])->name('outlet-payments.store');
    Route::get('/outlet-payments/unpaid-gr', [\App\Http\Controllers\OutletPaymentController::class, 'unpaidGR'])->name('outlet-payments.unpaid-gr');
    Route::get('/outlet-payments/gr-items/{grId}', [\App\Http\Controllers\OutletPaymentController::class, 'getGrItems'])->name('outlet-payments.gr-items');
    Route::get('/outlet-payments/gr-list', [\App\Http\Controllers\OutletPaymentController::class, 'getGrList'])->name('outlet-payments.gr-list');
    Route::get('/outlet-payments/retail-sales-list', [\App\Http\Controllers\OutletPaymentController::class, 'getRetailSalesList'])->name('outlet-payments.retail-sales-list');
    Route::get('/outlet-payments/retail-sales-items/{retailId}', [\App\Http\Controllers\OutletPaymentController::class, 'getRetailSalesItems'])->name('outlet-payments.retail-sales-items');
    Route::get('/outlet-payments/debug', [\App\Http\Controllers\OutletPaymentController::class, 'debug'])->name('outlet-payments.debug');
    Route::put('/outlet-payments/{outletPayment}', [OutletPaymentController::class, 'update'])->name('outlet-payments.update');
    Route::put('/outlet-payments/{outletPayment}/status', [OutletPaymentController::class, 'updateStatus'])->name('outlet-payments.status');
    Route::post('/outlet-payments/bulk-confirm', [OutletPaymentController::class, 'bulkConfirm'])->name('outlet-payments.bulk-confirm');
    Route::get('/outlet-payments/{outletPayment}', [OutletPaymentController::class, 'show'])->name('outlet-payments.show');
  
    Route::delete('/outlet-payments/{outletPayment}', [OutletPaymentController::class, 'destroy'])->name('outlet-payments.destroy');
});

// Non Food Payments
Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('non-food-payments', \App\Http\Controllers\NonFoodPaymentController::class);
    Route::get('non-food-payments/po-items/{poId}', [\App\Http\Controllers\NonFoodPaymentController::class, 'getPOItems'])->name('non-food-payments.po-items');
    Route::post('non-food-payments/{nonFoodPayment}/approve', [\App\Http\Controllers\NonFoodPaymentController::class, 'approve'])->name('non-food-payments.approve');
    Route::post('non-food-payments/{nonFoodPayment}/reject', [\App\Http\Controllers\NonFoodPaymentController::class, 'reject'])->name('non-food-payments.reject');
    Route::post('non-food-payments/{nonFoodPayment}/mark-as-paid', [\App\Http\Controllers\NonFoodPaymentController::class, 'markAsPaid'])->name('non-food-payments.mark-as-paid');
    Route::post('non-food-payments/{nonFoodPayment}/cancel', [\App\Http\Controllers\NonFoodPaymentController::class, 'cancel'])->name('non-food-payments.cancel');
    
    // OPEX Report
    Route::get('opex-report', [\App\Http\Controllers\OpexReportController::class, 'index'])->name('opex-report.index');
  
});

// Employee Upload Routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/employee-upload', [\App\Http\Controllers\EmployeeUploadController::class, 'upload'])->name('employee-upload.upload');
    Route::get('/employee-upload/template', [\App\Http\Controllers\EmployeeUploadController::class, 'downloadTemplate'])->name('employee-upload.template');
});
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/promos', [PromoController::class, 'index'])->name('promos.index');
    Route::get('/promos/create', [PromoController::class, 'create'])->name('promos.create');
    Route::post('/promos', [PromoController::class, 'store'])->name('promos.store');
    Route::get('/promos/{promo}', [PromoController::class, 'show'])->name('promos.show');
    Route::get('/promos/{promo}/edit', [PromoController::class, 'edit'])->name('promos.edit');
    Route::put('/promos/{promo}', [PromoController::class, 'update'])->name('promos.update');
    Route::delete('/promos/{promo}', [PromoController::class, 'destroy'])->name('promos.destroy');

    Route::get('/payment-types', [PaymentTypeController::class, 'index'])->name('payment-types.index');
    Route::get('/payment-types/create', [PaymentTypeController::class, 'create'])->name('payment-types.create');
    Route::post('/payment-types', [PaymentTypeController::class, 'store'])->name('payment-types.store');
    Route::get('/payment-types/{paymentType}', [PaymentTypeController::class, 'show'])->name('payment-types.show');
    Route::get('/payment-types/{paymentType}/edit', [PaymentTypeController::class, 'edit'])->name('payment-types.edit');
    Route::put('/payment-types/{paymentType}', [PaymentTypeController::class, 'update'])->name('payment-types.update');
    Route::delete('/payment-types/{paymentType}', [PaymentTypeController::class, 'destroy'])->name('payment-types.destroy');
});
// Outlet Payment Supplier
Route::get('/outlet-payment-suppliers', [\App\Http\Controllers\OutletPaymentSupplierController::class, 'index'])->name('outlet-payment-suppliers.index');
Route::get('/outlet-payment-suppliers/create', [\App\Http\Controllers\OutletPaymentSupplierController::class, 'create'])->name('outlet-payment-suppliers.create');
Route::post('/outlet-payment-suppliers', [\App\Http\Controllers\OutletPaymentSupplierController::class, 'store'])->name('outlet-payment-suppliers.store');
Route::get('/outlet-payment-suppliers/unpaid-gr', [\App\Http\Controllers\OutletPaymentSupplierController::class, 'unpaidGR'])->name('outlet-payment-suppliers.unpaid-gr');
Route::get('/outlet-payment-suppliers/{outletPaymentSupplier}', [\App\Http\Controllers\OutletPaymentSupplierController::class, 'show'])->name('outlet-payment-suppliers.show');
Route::put('/outlet-payment-suppliers/{outletPaymentSupplier}', [\App\Http\Controllers\OutletPaymentSupplierController::class, 'update'])->name('outlet-payment-suppliers.update');
Route::put('/outlet-payment-suppliers/{outletPaymentSupplier}/status', [\App\Http\Controllers\OutletPaymentSupplierController::class, 'updateStatus'])->name('outlet-payment-suppliers.status');
Route::delete('/outlet-payment-suppliers/{outletPaymentSupplier}', [\App\Http\Controllers\OutletPaymentSupplierController::class, 'destroy'])->name('outlet-payment-suppliers.destroy');

Route::middleware(['auth'])->group(function () {
    // Halaman utama Data Investor Outlet (Inertia)
    Route::get('/investors', [InvestorPageController::class, 'index']);
});

Route::get('/officer-check', [\App\Http\Controllers\OfficerCheckController::class, 'index']);
Route::get('/api/officer-check', [\App\Http\Controllers\OfficerCheckController::class, 'getOCs']);
Route::post('/api/officer-check', [\App\Http\Controllers\OfficerCheckController::class, 'store']);
Route::put('/api/officer-check/{id}', [\App\Http\Controllers\OfficerCheckController::class, 'update']);
Route::delete('/api/officer-check/{id}', [\App\Http\Controllers\OfficerCheckController::class, 'destroy']);
Route::get('/api/officer-check/users', [\App\Http\Controllers\OfficerCheckController::class, 'users']);

Route::middleware(['auth'])->group(function () {
    Route::resource('warehouse-outlets', WarehouseOutletController::class);
    Route::post('/warehouse-outlets/{warehouseOutlet}', [WarehouseOutletController::class, 'update'])->name('warehouse-outlets.update');
    Route::patch('warehouse-outlets/{warehouseOutlet}/toggle-status', [WarehouseOutletController::class, 'toggleStatus'])->name('warehouse-outlets.toggle-status');
});

// Route API untuk fetch warehouse outlet (dropdown, dsb)
Route::get('/api/warehouse-outlets', [\App\Http\Controllers\WarehouseOutletController::class, 'apiListByOutlet']);
Route::get('/api/warehouse-outlets/by-outlet/{outletId}', [\App\Http\Controllers\WarehouseOutletController::class, 'getByOutletId']);

// Route API untuk outlet food return (auth only, no verified middleware)
Route::middleware(['auth'])->group(function () {
    Route::get('/api/outlet-food-return/get-good-receives', [\App\Http\Controllers\OutletFoodReturnController::class, 'getGoodReceives'])->name('api.outlet-food-return.get-good-receives');
    Route::get('/api/outlet-food-return/get-good-receive-items', [\App\Http\Controllers\OutletFoodReturnController::class, 'getGoodReceiveItems'])->name('api.outlet-food-return.get-good-receive-items');
});

Route::post('promos/api-item-prices', [App\Http\Controllers\PromoController::class, 'apiItemPrices'])->name('promos.apiItemPrices');

// Reservation Routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
    Route::get('/reservations/create', [ReservationController::class, 'create'])->name('reservations.create');
    Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
    Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
    Route::get('/reservations/{reservation}/edit', [ReservationController::class, 'edit'])->name('reservations.edit');
    Route::put('/reservations/{reservation}', [ReservationController::class, 'update'])->name('reservations.update');
    Route::delete('/reservations/{reservation}', [ReservationController::class, 'destroy'])->name('reservations.destroy');
});

// Retail Warehouse Sales
Route::get('/retail-warehouse-sale', [App\Http\Controllers\RetailWarehouseSaleController::class, 'index'])->name('retail-warehouse-sale.index');
Route::get('/retail-warehouse-sale/create', [App\Http\Controllers\RetailWarehouseSaleController::class, 'create'])->name('retail-warehouse-sale.create');
Route::post('/retail-warehouse-sale', [App\Http\Controllers\RetailWarehouseSaleController::class, 'store'])->name('retail-warehouse-sale.store');
Route::get('/retail-warehouse-sale/{id}', [App\Http\Controllers\RetailWarehouseSaleController::class, 'show'])->name('retail-warehouse-sale.show');
Route::get('/retail-warehouse-sale/{id}/print', [App\Http\Controllers\RetailWarehouseSaleController::class, 'print'])->name('retail-warehouse-sale.print');

// Sales Outlet Dashboard Routes
Route::get('/sales-outlet-dashboard', [App\Http\Controllers\SalesOutletDashboardController::class, 'index'])->name('sales-outlet-dashboard.index');
Route::get('/sales-outlet-dashboard/menu-region', [App\Http\Controllers\SalesOutletDashboardController::class, 'getMenuRegionData'])->name('sales-outlet-dashboard.menu-region');
Route::get('/sales-outlet-dashboard/outlet-details', [App\Http\Controllers\SalesOutletDashboardController::class, 'getOutletDetailsByDate'])->name('sales-outlet-dashboard.outlet-details');

// Master Soal Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/master-soal', [App\Http\Controllers\MasterSoalController::class, 'index'])->name('master-soal.index');
    Route::get('/master-soal/create', [App\Http\Controllers\MasterSoalController::class, 'create'])->name('master-soal.create');
    Route::post('/master-soal', [App\Http\Controllers\MasterSoalController::class, 'store'])->name('master-soal.store');
    Route::get('/master-soal/{masterSoal}', [App\Http\Controllers\MasterSoalController::class, 'show'])->name('master-soal.show');
    Route::get('/master-soal/{masterSoal}/edit', [App\Http\Controllers\MasterSoalController::class, 'edit'])->name('master-soal.edit');
    Route::put('/master-soal/{masterSoal}', [App\Http\Controllers\MasterSoalController::class, 'update'])->name('master-soal.update');
    Route::delete('/master-soal/{masterSoal}', [App\Http\Controllers\MasterSoalController::class, 'destroy'])->name('master-soal.destroy');
    Route::patch('/master-soal/{masterSoal}/toggle-status', [App\Http\Controllers\MasterSoalController::class, 'toggleStatus'])->name('master-soal.toggle-status');
    
    // Test route
    Route::get('/master-soal-test', function () {
        return inertia('MasterSoal/Test');
    })->name('master-soal.test');
});

// Master Soal New Routes (Struktur yang benar)
Route::middleware(['auth'])->group(function () {
    Route::get('/master-soal-new', [App\Http\Controllers\MasterSoalNewController::class, 'index'])->name('master-soal-new.index');
    Route::get('/master-soal-new/create', [App\Http\Controllers\MasterSoalNewController::class, 'create'])->name('master-soal-new.create');
    Route::post('/master-soal-new', [App\Http\Controllers\MasterSoalNewController::class, 'store'])->name('master-soal-new.store');
    Route::get('/master-soal-new/{masterSoal}', [App\Http\Controllers\MasterSoalNewController::class, 'show'])->name('master-soal-new.show');
    Route::get('/master-soal-new/{masterSoal}/edit', [App\Http\Controllers\MasterSoalNewController::class, 'edit'])->name('master-soal-new.edit');
    Route::put('/master-soal-new/{masterSoal}', [App\Http\Controllers\MasterSoalNewController::class, 'update'])->name('master-soal-new.update');
    Route::delete('/master-soal-new/{masterSoal}', [App\Http\Controllers\MasterSoalNewController::class, 'destroy'])->name('master-soal-new.destroy');
    Route::patch('/master-soal-new/{masterSoal}/toggle-status', [App\Http\Controllers\MasterSoalNewController::class, 'toggleStatus'])->name('master-soal-new.toggle-status');
    Route::get('/master-soal-new/{masterSoal}/duplicate', [App\Http\Controllers\MasterSoalNewController::class, 'duplicate'])->name('master-soal-new.duplicate');
});

// Enroll Test Routes
Route::middleware(['auth'])->group(function () {
    // Admin routes
    Route::get('/enroll-test', [App\Http\Controllers\EnrollTestController::class, 'index'])->name('enroll-test.index');
    Route::get('/enroll-test/create', [App\Http\Controllers\EnrollTestController::class, 'create'])->name('enroll-test.create');
    Route::post('/enroll-test', [App\Http\Controllers\EnrollTestController::class, 'store'])->name('enroll-test.store');
    Route::get('/enroll-test/{enrollTest}', [App\Http\Controllers\EnrollTestController::class, 'show'])->name('enroll-test.show');
    Route::get('/enroll-test/{enrollTest}/edit', [App\Http\Controllers\EnrollTestController::class, 'edit'])->name('enroll-test.edit');
    Route::put('/enroll-test/{enrollTest}', [App\Http\Controllers\EnrollTestController::class, 'update'])->name('enroll-test.update');
    Route::delete('/enroll-test/{enrollTest}', [App\Http\Controllers\EnrollTestController::class, 'destroy'])->name('enroll-test.destroy');
    Route::post('/enroll-test/{enrollTest}/cancel', [App\Http\Controllers\EnrollTestController::class, 'cancel'])->name('enroll-test.cancel');
    Route::post('/enroll-test/{enrollTest}/expire', [App\Http\Controllers\EnrollTestController::class, 'expire'])->name('enroll-test.expire');
    
    // User routes
    Route::get('/my-tests', [App\Http\Controllers\EnrollTestController::class, 'myTests'])->name('enroll-test.my-tests');
    Route::post('/enroll-test/{enrollTest}/start', [App\Http\Controllers\EnrollTestController::class, 'startTest'])->name('enroll-test.start');
           Route::get('/enroll-test/{enrollTest}/take', [App\Http\Controllers\EnrollTestController::class, 'takeTest'])->name('enroll-test.take');
           Route::post('/enroll-test/{enrollTest}/next', [App\Http\Controllers\EnrollTestController::class, 'nextQuestion'])->name('enroll-test.next');
           Route::post('/enroll-test/{enrollTest}/submit', [App\Http\Controllers\EnrollTestController::class, 'submitTest'])->name('enroll-test.submit');
    Route::get('/test-result/{testResult}', [App\Http\Controllers\EnrollTestController::class, 'result'])->name('enroll-test.result');
    
    // Report routes
    Route::get('/enroll-test-report', [App\Http\Controllers\EnrollTestController::class, 'report'])->name('enroll-test.report');
    Route::post('/test-answer/{testAnswer}/update-essay-score', [App\Http\Controllers\EnrollTestController::class, 'updateEssayScore'])->name('enroll-test.update-essay-score');
    Route::post('/enroll-test/bulk-update-essay-scores', [App\Http\Controllers\EnrollTestController::class, 'bulkUpdateEssayScores'])->name('enroll-test.bulk-update-essay-scores');
    Route::post('/enroll-test/recalculate-all-scores', [App\Http\Controllers\EnrollTestController::class, 'recalculateAllScores'])->name('enroll-test.recalculate-all-scores');
});
Route::get('/sales-outlet-dashboard/outlet-daily-revenue', [App\Http\Controllers\SalesOutletDashboardController::class, 'getOutletDailyRevenue'])->name('sales-outlet-dashboard.outlet-daily-revenue');
Route::get('/sales-outlet-dashboard/outlet-lunch-dinner-detail', [App\Http\Controllers\SalesOutletDashboardController::class, 'getOutletLunchDinnerDetail'])->name('sales-outlet-dashboard.outlet-lunch-dinner-detail');
Route::get('/sales-outlet-dashboard/outlet-weekend-weekday-detail', [App\Http\Controllers\SalesOutletDashboardController::class, 'getOutletWeekendWeekdayDetail'])->name('sales-outlet-dashboard.outlet-weekend-weekday-detail');
Route::get('/sales-outlet-dashboard/holidays', [App\Http\Controllers\SalesOutletDashboardController::class, 'getHolidays'])->name('sales-outlet-dashboard.holidays');
Route::get('/sales-outlet-dashboard/outlet-orders', [App\Http\Controllers\SalesOutletDashboardController::class, 'getOutletOrders'])->name('sales-outlet-dashboard.outlet-orders');
Route::get('/sales-outlet-dashboard/bank-promo-discount-transactions', [App\Http\Controllers\SalesOutletDashboardController::class, 'getBankPromoDiscountTransactions'])->name('sales-outlet-dashboard.bank-promo-discount-transactions');
Route::get('/sales-outlet-dashboard/export-bank-promo-discount-transactions', [App\Http\Controllers\SalesOutletDashboardController::class, 'exportBankPromoDiscountTransactions'])->name('sales-outlet-dashboard.export-bank-promo-discount-transactions');
Route::get('/sales-outlet-dashboard/non-promo-bank-discount-transactions', [App\Http\Controllers\SalesOutletDashboardController::class, 'getNonPromoBankDiscountTransactions'])->name('sales-outlet-dashboard.non-promo-bank-discount-transactions');
Route::get('/sales-outlet-dashboard/export-non-promo-bank-discount-transactions', [App\Http\Controllers\SalesOutletDashboardController::class, 'exportNonPromoBankDiscountTransactions'])->name('sales-outlet-dashboard.export-non-promo-bank-discount-transactions');
Route::get('/sales-outlet-dashboard/promo-usage-by-outlet', [App\Http\Controllers\SalesOutletDashboardController::class, 'getPromoUsageByOutlet'])->name('sales-outlet-dashboard.promo-usage-by-outlet');
Route::delete('/retail-warehouse-sale/{id}', [App\Http\Controllers\RetailWarehouseSaleController::class, 'destroy'])->name('retail-warehouse-sale.destroy');
Route::post('/retail-warehouse-sale/search-items', [App\Http\Controllers\RetailWarehouseSaleController::class, 'searchItems'])->name('retail-warehouse-sale.search-items');
Route::post('/retail-warehouse-sale/search-items-by-name', [App\Http\Controllers\RetailWarehouseSaleController::class, 'searchItemsByName'])->name('retail-warehouse-sale.search-items-by-name');
Route::post('/retail-warehouse-sale/search-customers', [App\Http\Controllers\RetailWarehouseSaleController::class, 'searchCustomers'])->name('retail-warehouse-sale.search-customers');
Route::post('/retail-warehouse-sale/store-customer', [App\Http\Controllers\RetailWarehouseSaleController::class, 'storeCustomer'])->name('retail-warehouse-sale.store-customer');
Route::get('/api/retail-warehouse-sale/item-price', [App\Http\Controllers\RetailWarehouseSaleController::class, 'getItemPrice']);

Route::get('/report-invoice-outlet', [App\Http\Controllers\OutletPaymentController::class, 'reportInvoiceOutlet'])->name('report-invoice-outlet');

Route::post('/stock-cut/order-items', [\App\Http\Controllers\StockCutController::class, 'potongStockOrderItems']);
Route::post('/stock-cut/engineering', [\App\Http\Controllers\StockCutController::class, 'engineering']);
Route::post('/stock-cut/check-status', [\App\Http\Controllers\StockCutController::class, 'checkStockCutStatus']);
Route::post('/stock-cut/cek-kebutuhan', [\App\Http\Controllers\StockCutController::class, 'cekKebutuhanStockV2']);

Route::get('/stock-cut', function () {
    return Inertia::render('StockCut');
})->middleware(['auth', 'verified'])->name('stock-cut.index');

Route::get('/stock-cut/menu-cost', function () {
    return Inertia::render('StockCut/MenuCost');
})->middleware(['auth', 'verified'])->name('stock-cut.menu-cost');

Route::get('/api/stock-cut/logs', [\App\Http\Controllers\StockCutController::class, 'getLogs']);
Route::delete('/stock-cut/{id}', [\App\Http\Controllers\StockCutController::class, 'rollback']);
Route::get('/api/stock-cut/menu-cost', [\App\Http\Controllers\StockCutController::class, 'calculateMenuCost']);

Route::get('/stock-cut/form', function () {
    // Ambil data outlet untuk dropdown
    $outlets = \App\Models\Outlet::select('id_outlet', 'nama_outlet')->get()
        ->map(function($o) {
            return [
                'id' => $o->id_outlet,
                'name' => $o->nama_outlet,
            ];
        });
    return Inertia::render('StockCut/Form', [
        'outlets' => $outlets,
    ]);
})->middleware(['auth'])->name('stock-cut.form');

// Route API untuk data dashboard outlet
Route::get('/api/outlet-dashboard', [\App\Http\Controllers\OutletDashboardController::class, 'index']);
// Route page inertia untuk dashboard outlet
Route::get('/outlet-dashboard', function () {
    return Inertia::render('OutletDashboard');
});

Route::get('/report-sales-simple', function () {
    return Inertia::render('Report/ReportSalesSimple');
})->middleware(['auth']);

Route::get('/report-daily-outlet-revenue', function () {
    return Inertia::render('Report/ReportDailyOutletRevenue');
})->middleware(['auth']);

Route::get('/report-weekly-outlet-fb-revenue', function () {
    return Inertia::render('Report/ReportWeeklyOutletFbRevenue');
})->middleware(['auth']);

Route::get('/report-daily-revenue-forecast', function () {
    return Inertia::render('Report/ReportDailyRevenueForecast');
})->middleware(['auth']);

Route::get('/report-monthly-fb-revenue-performance', function () {
    return Inertia::render('Report/ReportMonthlyFbRevenuePerformance');
})->middleware(['auth']);

Route::get('/api/report/sales-simple', [App\Http\Controllers\ReportController::class, 'reportSalesSimple']);
Route::get('/api/outlet-expenses', [App\Http\Controllers\ReportController::class, 'apiOutletExpenses']);

Route::get('/api/my-outlet-qr', [App\Http\Controllers\ReportController::class, 'myOutletQr']);

// API untuk item engineering
Route::get('/api/report/item-engineering', [\App\Http\Controllers\ReportController::class, 'reportItemEngineering']);
// Web route untuk halaman
Route::get('/item-engineering', function () {
    return Inertia::render('Report/ItemEngineering');
})->middleware(['auth']);

    // Attendance Report per Outlet (summary)
    Route::get('/attendance-report/outlet-summary', [AttendanceReportController::class, 'outletSummary'])->name('attendance-report.outlet-summary');

Route::get('/users/dropdown-data', [UserController::class, 'getDropdownData'])->name('users.dropdown-data');

Route::resource('users', UserController::class);
Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
Route::post('users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');

// API routes for user saldo management
Route::middleware(['auth'])->group(function () {
    Route::post('/api/users/{userId}/update-saldo', [UserController::class, 'updateSaldo'])->name('api.users.update-saldo');
    Route::get('/api/users/{userId}/extra-off-balance', [UserController::class, 'getExtraOffBalance'])->name('api.users.extra-off-balance');
    Route::get('/api/users/{userId}/public-holiday-balance', [UserController::class, 'getPublicHolidayBalance'])->name('api.users.public-holiday-balance');
});

// QA Categories Routes
Route::resource('qa-categories', \App\Http\Controllers\QaCategoryController::class);
Route::patch('qa-categories/{qaCategory}/toggle-status', [\App\Http\Controllers\QaCategoryController::class, 'toggleStatus'])->name('qa-categories.toggle-status');

// QA Parameters Routes
Route::resource('qa-parameters', \App\Http\Controllers\QaParameterController::class);
Route::patch('qa-parameters/{qaParameter}/toggle-status', [\App\Http\Controllers\QaParameterController::class, 'toggleStatus'])->name('qa-parameters.toggle-status');

// QA Guidance Routes
Route::resource('qa-guidances', \App\Http\Controllers\QaGuidanceController::class);
Route::patch('qa-guidances/{qaGuidance}/toggle-status', [\App\Http\Controllers\QaGuidanceController::class, 'toggleStatus'])->name('qa-guidances.toggle-status');

// Inspection Routes
Route::resource('inspections', \App\Http\Controllers\InspectionController::class);
Route::get('inspections/{inspection}/add-finding', [\App\Http\Controllers\InspectionController::class, 'addFinding'])->name('inspections.add-finding');
Route::post('inspections/{inspection}/store-finding', [\App\Http\Controllers\InspectionController::class, 'storeFinding'])->name('inspections.store-finding');
Route::patch('inspections/{inspection}/complete', [\App\Http\Controllers\InspectionController::class, 'complete'])->name('inspections.complete');
Route::delete('inspections/{inspection}/delete-finding/{finding}', [\App\Http\Controllers\InspectionController::class, 'deleteFinding'])->name('inspections.delete-finding');
Route::get('inspections/{inspection}/cpa', [\App\Http\Controllers\InspectionController::class, 'cpa'])->name('inspections.cpa');
Route::post('inspections/{inspection}/cpa', [\App\Http\Controllers\InspectionController::class, 'storeCPA'])->name('inspections.cpa.store');

// Debug route
Route::get('debug-inspection', function() {
    try {
        $outlets = \App\Models\Outlet::select('id_outlet', 'nama_outlet')->get();
        $guidances = \App\Models\QaGuidance::where('status', 'A')->get();
        $inspections = \App\Models\Inspection::with(['outlet', 'guidance', 'createdByUser'])->get();
        return response()->json([
            'outlets' => $outlets,
            'guidances' => $guidances,
            'inspections' => $inspections,
            'message' => 'Database connection works'
        ]);
    } catch(\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// Debug route untuk cek inspections
Route::get('debug-inspections', function() {
    try {
        $inspections = \App\Models\Inspection::all();
        $inspectionsWithRelations = \App\Models\Inspection::with(['outlet', 'guidance', 'createdByUser'])->get();
        return response()->json([
            'total_inspections' => $inspections->count(),
            'inspections' => $inspections->toArray(),
            'inspections_with_relations' => $inspectionsWithRelations->toArray()
        ]);
    } catch(\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// Debug route untuk cek guidance points
Route::get('debug-guidance-points', function() {
    try {
        $inspection = \App\Models\Inspection::with('guidance.guidanceCategories.parameters.details')->first();
        
        if (!$inspection) {
            return response()->json(['error' => 'No inspections found'], 404);
        }
        
        $guidanceTotalPoints = 0;
        if ($inspection->guidance && $inspection->guidance->guidanceCategories) {
            foreach ($inspection->guidance->guidanceCategories as $guidanceCategory) {
                if ($guidanceCategory->parameters) {
                    foreach ($guidanceCategory->parameters as $parameter) {
                        if ($parameter->details) {
                            foreach ($parameter->details as $detail) {
                                $guidanceTotalPoints += $detail->point;
                            }
                        }
                    }
                }
            }
        }
        
        return response()->json([
            'inspection_id' => $inspection->id,
            'guidance_id' => $inspection->guidance_id,
            'guidance_title' => $inspection->guidance ? $inspection->guidance->title : 'No guidance',
            'guidance_categories_count' => $inspection->guidance ? $inspection->guidance->guidanceCategories->count() : 0,
            'calculated_total_points' => $guidanceTotalPoints,
            'accessor_total_points' => $inspection->guidance_total_points,
            'guidance_data' => $inspection->guidance ? $inspection->guidance->toArray() : null
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// Debug route untuk test index data
Route::get('test-index-data', function() {
    $guidances = \App\Models\QaGuidance::with([
        'guidanceCategories.category',
        'guidanceCategories.parameters.details.parameter'
    ])->get();
    
    $firstGuidance = $guidances->first();
    $firstGuidanceCategories = $firstGuidance ? $firstGuidance->guidanceCategories : collect();
    
    return response()->json([
        'guidances_count' => $guidances->count(),
        'first_guidance_id' => $firstGuidance ? $firstGuidance->id : null,
        'first_guidance_title' => $firstGuidance ? $firstGuidance->title : null,
        'guidance_categories_count' => $firstGuidanceCategories->count(),
        'first_category_name' => $firstGuidanceCategories->first() ? $firstGuidanceCategories->first()->category->categories : null,
        'parameters_count' => $firstGuidanceCategories->first() ? $firstGuidanceCategories->first()->parameters->count() : 0,
        'raw_data' => $firstGuidance ? $firstGuidance->toArray() : null
    ]);
});

// Debug route untuk test data langsung
Route::get('test-simple-data', function() {
    $guidance = \App\Models\QaGuidance::first();
    $guidanceCategories = \App\Models\QaGuidanceCategory::where('guidance_id', $guidance->id)->get();
    $categoryParameters = \App\Models\QaGuidanceCategoryParameter::whereIn('guidance_category_id', $guidanceCategories->pluck('id'))->get();
    
    return response()->json([
        'guidance' => $guidance,
        'guidanceCategories' => $guidanceCategories,
        'categoryParameters' => $categoryParameters,
        'counts' => [
            'guidance' => \App\Models\QaGuidance::count(),
            'guidanceCategories' => \App\Models\QaGuidanceCategory::count(),
            'categoryParameters' => \App\Models\QaGuidanceCategoryParameter::count(),
            'parameterDetails' => \App\Models\QaGuidanceParameterDetail::count()
        ]
    ]);
});




// Employee Movement Routes
Route::get('employee-movements/search/employee', [\App\Http\Controllers\EmployeeMovementController::class, 'searchEmployee'])->name('employee-movements.search-employee');
Route::get('employee-movements/employee/{id}', [\App\Http\Controllers\EmployeeMovementController::class, 'getEmployeeDetails'])->name('employee-movements.employee-details');
Route::get('employee-movements/dropdown-data', [\App\Http\Controllers\EmployeeMovementController::class, 'getDropdownData'])->name('employee-movements.dropdown-data');
Route::get('employee-movements/approvers', [\App\Http\Controllers\EmployeeMovementController::class, 'getApprovers'])->name('employee-movements.approvers');
Route::post('employee-movements/{id}/approve', [\App\Http\Controllers\EmployeeMovementController::class, 'approve'])->name('employee-movements.approve');
Route::put('employee-movements/{id}/salary', [\App\Http\Controllers\EmployeeMovementController::class, 'updateSalary'])->name('employee-movements.update-salary');
Route::post('employee-movements/{id}/execute', [\App\Http\Controllers\EmployeeMovementController::class, 'executeMovement'])->name('employee-movements.execute');
Route::resource('employee-movements', \App\Http\Controllers\EmployeeMovementController::class);

// Roulette download template (no auth required)
Route::get('/roulette/import/template', [\App\Http\Controllers\RouletteController::class, 'downloadTemplate'])->name('roulette.download-template');

// Roulette Game (no auth required) - harus di atas route resource
Route::get('roulette/game', [\App\Http\Controllers\RouletteController::class, 'game'])->name('roulette.game');
Route::get('roulette/grid', [\App\Http\Controllers\RouletteController::class, 'grid'])->name('roulette.grid');
Route::get('roulette/slot', [\App\Http\Controllers\RouletteController::class, 'slot'])->name('roulette.slot');
Route::get('roulette/lottery', [\App\Http\Controllers\RouletteController::class, 'lottery'])->name('roulette.lottery');

// Roulette Routes
Route::middleware(['auth'])->group(function () {
    Route::delete('roulette/destroy-all', [\App\Http\Controllers\RouletteController::class, 'destroyAll'])->name('roulette.destroy-all');
    Route::post('roulette/import', [\App\Http\Controllers\RouletteController::class, 'import'])->name('roulette.import');
    Route::resource('roulette', \App\Http\Controllers\RouletteController::class);
});



// Member Routes
Route::middleware(['auth'])->group(function () {
    Route::resource('members', MemberController::class);
    Route::patch('members/{member}/toggle-status', [MemberController::class, 'toggleStatus'])->name('members.toggle-status');
                    Route::patch('members/{member}/toggle-block', [MemberController::class, 'toggleBlock'])->name('members.toggle-block');
                Route::get('members/export', [MemberController::class, 'export'])->name('members.export');
                Route::get('api/members/{id}/transactions', [MemberController::class, 'getTransactions'])->name('members.transactions');
                Route::get('api/members/{id}/preferences', [MemberController::class, 'getPreferences'])->name('members.preferences');
});

// Shared Documents Routes
Route::middleware(['auth'])->group(function () {
    Route::resource('shared-documents', SharedDocumentController::class);
    Route::get('shared-documents/{id}/download', [SharedDocumentController::class, 'download'])->name('shared-documents.download');
    Route::post('shared-documents/{id}/callback', [SharedDocumentController::class, 'callback'])->name('shared-documents.callback');
    Route::post('shared-documents/{document}/share', [SharedDocumentController::class, 'share'])->name('shared-documents.share');
    Route::delete('shared-documents/{document}/remove-share', [SharedDocumentController::class, 'removeShare'])->name('shared-documents.remove-share');
    
    // Enhanced permission management
    Route::post('shared-documents/{document}/permissions', [SharedDocumentController::class, 'updatePermissions'])->name('shared-documents.permissions.update');
    Route::get('shared-documents/{document}/permissions', [SharedDocumentController::class, 'getPermissions'])->name('shared-documents.permissions.get');
    
    // User search endpoints
    Route::get('shared-documents/users/search', [SharedDocumentController::class, 'searchUsers'])->name('shared-documents.users.search');
    Route::get('shared-documents/users/dropdown', [SharedDocumentController::class, 'getDropdownData'])->name('shared-documents.users.dropdown');
});

// CRM Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/crm/dashboard', [App\Http\Controllers\CrmDashboardController::class, 'index'])->name('crm.dashboard');
    Route::get('/api/crm/chart-data', [App\Http\Controllers\CrmDashboardController::class, 'getChartData'])->name('crm.chart-data');
    Route::get('/api/crm/redeem-details', [App\Http\Controllers\CrmDashboardController::class, 'getRedeemDetails'])->name('crm.redeem-details');
    
    // Customer Analytics Routes
    Route::get('/crm/customer-analytics', [App\Http\Controllers\CustomerAnalyticsController::class, 'index'])->name('crm.customer-analytics');
    Route::get('/crm/customer-analytics/transactions', [App\Http\Controllers\CustomerAnalyticsController::class, 'getCustomerTransactions'])->name('crm.customer-transactions');
    
    // Member Reports Routes
    Route::get('/crm/member-reports', [App\Http\Controllers\MemberReportsController::class, 'index'])->name('crm.member-reports');
    
    // Point Management Routes
    Route::get('/crm/point-management', [App\Http\Controllers\PointManagementController::class, 'index'])->name('crm.point-management');
    Route::post('/crm/point-management', [App\Http\Controllers\PointManagementController::class, 'store'])->name('crm.point-management.store');
    Route::delete('/crm/point-management/{id}', [App\Http\Controllers\PointManagementController::class, 'destroy'])->name('crm.point-management.destroy');
    Route::get('/crm/point-management/search-customers', [App\Http\Controllers\PointManagementController::class, 'searchCustomers'])->name('crm.point-management.search-customers');
    Route::get('/crm/point-management/cabang-list', [App\Http\Controllers\PointManagementController::class, 'getCabangList'])->name('crm.point-management.cabang-list');
});

// Video Tutorial Routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('video-tutorials/gallery', [VideoTutorialController::class, 'gallery'])->name('video-tutorials.gallery');
    Route::resource('video-tutorials', VideoTutorialController::class);
    Route::patch('video-tutorials/{videoTutorial}/toggle-status', [VideoTutorialController::class, 'toggleStatus'])->name('video-tutorials.toggle-status');
    
    Route::resource('video-tutorial-groups', VideoTutorialGroupController::class);
    Route::patch('video-tutorial-groups/{videoTutorialGroup}/toggle-status', [VideoTutorialGroupController::class, 'toggleStatus'])->name('video-tutorial-groups.toggle-status');
});

// Test route for dropdown data
Route::get('/test/users-dropdown', function() {
    return app('App\Http\Controllers\UserController')->getDropdownData();
})->name('test.users-dropdown');

// Test route for user data
Route::get('/test/user-data', function () {
    $user = auth()->user();
    if (!$user) {
        return response()->json(['error' => 'Not authenticated'], 401);
    }
    
    return response()->json([
        'success' => true,
        'user' => $user->toArray(),
        'user_fields' => array_keys($user->getAttributes()),
    ]);
})->middleware('auth');

Route::middleware(['auth'])->group(function () {
    // Admin routes for managing user pins (must be before the user-specific routes)
    Route::get('admin/users/{userId}/pins', [UserPinController::class, 'getUserPins'])->name('users.pins.admin.index');
    Route::post('admin/users/{userId}/pins', [UserPinController::class, 'storeUserPin'])->name('users.pins.admin.store');
    Route::put('admin/user-pins/{id}', [UserPinController::class, 'updateUserPin'])->name('users.pins.admin.update');
    Route::delete('admin/user-pins/{id}', [UserPinController::class, 'destroyUserPin'])->name('users.pins.admin.destroy');
    
    // User-specific routes (for authenticated users managing their own pins)
    Route::prefix('users/{user}')->group(function () {
        Route::get('pins', [UserPinController::class, 'index'])->name('users.pins.index');
        Route::post('pins', [UserPinController::class, 'store'])->name('users.pins.store');
    });
    Route::put('user-pins/{id}', [UserPinController::class, 'update'])->name('users.pins.update');
    Route::delete('user-pins/{id}', [UserPinController::class, 'destroy'])->name('users.pins.destroy');
});

Route::middleware(['auth'])->group(function () {
    Route::resource('shifts', ShiftController::class);
    Route::get('api/divisions', [ShiftController::class, 'apiDivisions']);
});

Route::middleware(['auth'])->group(function () {
    Route::get('user-shifts/calendar', [UserShiftController::class, 'calendarView'])->name('user-shifts.calendar');
    Route::resource('user-shifts', UserShiftController::class)->only(['index', 'store']);
    
    // Organization Chart Routes
    Route::get('organization-chart', [App\Http\Controllers\OrganizationChartController::class, 'index'])->name('organization-chart.index');
    Route::get('api/organization-chart', [App\Http\Controllers\OrganizationChartController::class, 'getOrganizationData'])->name('organization-chart.data');
    Route::get('api/organization-chart/debug', [App\Http\Controllers\OrganizationChartController::class, 'debugData'])->name('organization-chart.debug');
    
    // Schedule/Attendance Correction Routes
    Route::get('schedule-attendance-correction', [ScheduleAttendanceCorrectionController::class, 'index'])->name('schedule-attendance-correction.index');
    Route::post('schedule-attendance-correction/schedule', [ScheduleAttendanceCorrectionController::class, 'updateSchedule'])->name('schedule-attendance-correction.schedule');
    Route::post('schedule-attendance-correction/attendance', [ScheduleAttendanceCorrectionController::class, 'updateAttendance'])->name('schedule-attendance-correction.attendance');
    Route::post('schedule-attendance-correction/manual-attendance', [ScheduleAttendanceCorrectionController::class, 'submitManualAttendance'])->name('schedule-attendance-correction.manual-attendance');
    Route::get('api/schedule-attendance-correction/check-manual-limit', [ScheduleAttendanceCorrectionController::class, 'checkManualAttendanceLimit'])->name('schedule-attendance-correction.check-manual-limit');
    Route::get('schedule-attendance-correction/history', [ScheduleAttendanceCorrectionController::class, 'getCorrectionHistory'])->name('schedule-attendance-correction.history');
    
    // Report Routes
    Route::get('schedule-attendance-correction/report', [ScheduleAttendanceCorrectionController::class, 'report'])->name('schedule-attendance-correction.report');
    Route::get('api/schedule-attendance-correction/report-data', [ScheduleAttendanceCorrectionController::class, 'getReportData'])->name('schedule-attendance-correction.report-data');
    Route::get('schedule-attendance-correction/export-report', [ScheduleAttendanceCorrectionController::class, 'exportReport'])->name('schedule-attendance-correction.export-report');
    
    // Approval Routes
    Route::get('api/schedule-attendance-correction/pending-approvals', [ScheduleAttendanceCorrectionController::class, 'getPendingApprovals'])->name('schedule-attendance-correction.pending-approvals');
    Route::post('api/schedule-attendance-correction/approve/{id}', [ScheduleAttendanceCorrectionController::class, 'approveCorrection'])->name('schedule-attendance-correction.approve');
    Route::post('api/schedule-attendance-correction/reject/{id}', [ScheduleAttendanceCorrectionController::class, 'rejectCorrection'])->name('schedule-attendance-correction.reject');
});

Route::get('/user-shifts/calendar/export-excel', [\App\Http\Controllers\UserShiftController::class, 'exportCalendarExcel'])->name('user-shifts.calendar.export-excel');

Route::resource('kalender-perusahaan', App\Http\Controllers\KalenderPerusahaanController::class);

Route::get('attendance-report', [App\Http\Controllers\AttendanceReportController::class, 'index']);
Route::get('attendance-report/detail', [App\Http\Controllers\AttendanceReportController::class, 'detail']);
Route::get('/attendance-report/shift-info', [\App\Http\Controllers\AttendanceReportController::class, 'shiftInfo']);
Route::get('/api/attendance-report/employees', [\App\Http\Controllers\AttendanceReportController::class, 'getEmployees']);
Route::get('attendance-report/export', [App\Http\Controllers\AttendanceReportController::class, 'exportExcel'])->name('attendance-report.export');
Route::get('attendance-report/employee-summary', [App\Http\Controllers\AttendanceReportController::class, 'employeeSummary'])->name('attendance-report.employee-summary');
Route::get('attendance-report/employee-summary/export', [App\Http\Controllers\AttendanceReportController::class, 'exportEmployeeSummary'])->name('attendance-report.employee-summary.export');
Route::get('attendance-report/employee-summary-attendance', [App\Http\Controllers\AttendanceReportController::class, 'employeeSummaryAttendance'])->name('attendance-report.employee-summary-attendance');
Route::post('attendance-report/employee-summary-attendance/export', [App\Http\Controllers\AttendanceReportController::class, 'exportEmployeeSummaryAttendance'])->name('attendance-report.employee-summary-attendance.export');

Route::get('/report/sales-simple/export-order-detail', [\App\Http\Controllers\ReportController::class, 'exportOrderDetail'])->name('report.sales-simple.export-order-detail');

Route::get('/report/item-engineering/export', [\App\Http\Controllers\ReportController::class, 'exportItemEngineering'])->name('report.item-engineering.export');

Route::get('/api/items/search-for-pr', [ItemController::class, 'searchForPr']);

// Job Vacancy Admin Panel
Route::prefix('admin/job-vacancy')->middleware(['auth'])->group(function () {
    Route::get('/', [\App\Http\Controllers\JobVacancyController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\JobVacancyController::class, 'store']);
    Route::put('/{id}', [\App\Http\Controllers\JobVacancyController::class, 'update']);
    Route::delete('/{id}', [\App\Http\Controllers\JobVacancyController::class, 'destroy']);
    Route::patch('/{id}/set-active', [\App\Http\Controllers\JobVacancyController::class, 'setActive']);
    Route::get('/{id}', [\App\Http\Controllers\JobVacancyController::class, 'show']);
});

// Job Vacancy Public List (Landing Page)
Route::get('/job-vacancies', fn() => inertia('Landing/JobVacancyList'));
Route::get('/api/job-vacancies', [\App\Http\Controllers\JobVacancyController::class, 'publicList']);

Route::get('/payroll/master', [PayrollController::class, 'index'])->name('payroll.master');
Route::post('/payroll/master', [PayrollController::class, 'store'])->name('payroll.master.store');
Route::get('/payroll/master/template', [PayrollController::class, 'downloadTemplate'])->name('payroll.master.template');
Route::post('/payroll/master/import', [PayrollController::class, 'importExcel'])->name('payroll.master.import');

// Payroll Report Routes
Route::get('/payroll/report', [App\Http\Controllers\PayrollReportController::class, 'index'])->name('payroll.report');
Route::get('/payroll/report/export', [App\Http\Controllers\PayrollReportController::class, 'export'])->name('payroll.report.export');
Route::get('/payroll/report/attendance-detail', [App\Http\Controllers\PayrollReportController::class, 'getAttendanceDetail'])->name('payroll.report.attendance-detail');

// Custom Payroll Items Routes
Route::post('/payroll/report/custom-item/add', [App\Http\Controllers\PayrollReportController::class, 'addCustomItem'])->name('payroll.report.custom-item.add');
Route::delete('/payroll/report/custom-item/delete', [App\Http\Controllers\PayrollReportController::class, 'deleteCustomItem'])->name('payroll.report.custom-item.delete');
Route::get('/payroll/report/custom-items', [App\Http\Controllers\PayrollReportController::class, 'getCustomItems'])->name('payroll.report.custom-items');

// Print Payroll Route
Route::get('/payroll/report/print', [App\Http\Controllers\PayrollReportController::class, 'printPayroll'])->name('payroll.report.print');
Route::get('/payroll/report/show', [App\Http\Controllers\PayrollReportController::class, 'showPayroll'])->name('payroll.report.show');


// LMS Routes
Route::middleware(['auth'])->prefix('lms')->name('lms.')->group(function () {
    // Dashboard
    Route::get('/dashboard', [App\Http\Controllers\LmsController::class, 'dashboard'])->name('dashboard');
    
    // Courses
    Route::get('/courses', [App\Http\Controllers\LmsController::class, 'courses'])->name('courses.index');
    Route::get('/courses/archived', [App\Http\Controllers\LmsController::class, 'archivedCourses'])->name('courses.archived');
    Route::post('/courses', [App\Http\Controllers\LmsController::class, 'storeCourse'])->name('courses.store');
    Route::get('/courses/{course}', [App\Http\Controllers\LmsController::class, 'showCourse'])->name('courses.show');
    Route::get('/courses/{course}/edit', [App\Http\Controllers\LmsController::class, 'editCourse'])->name('courses.edit');
    Route::put('/courses/{course}', [App\Http\Controllers\LmsController::class, 'updateCourse'])->name('courses.update');
    Route::put('/courses/{course}/archive', [App\Http\Controllers\LmsController::class, 'archiveCourse'])->name('courses.archive');
    Route::put('/courses/{course}/publish', [App\Http\Controllers\LmsController::class, 'publishCourse'])->name('courses.publish');
    Route::post('/courses/{course}/enroll', [App\Http\Controllers\LmsController::class, 'enroll'])->name('courses.enroll');
    Route::get('/courses/{course}/trainer-ratings', [App\Http\Controllers\LmsController::class, 'getCourseTrainerRatings'])->name('courses.trainer-ratings');
Route::get('/trainer-report', [App\Http\Controllers\LmsController::class, 'getTrainerReport'])->name('trainer-report');
Route::get('/trainer-report-page', function () {
    return Inertia::render('Lms/TrainerReport');
})->name('trainer-report-page');

// Training Report
Route::get('/training-report', [App\Http\Controllers\LmsController::class, 'getTrainingReport'])->name('training-report');
Route::get('/training-report-page', function () {
    return Inertia::render('Lms/TrainingReport');
})->name('training-report-page');


// Available trainings API
Route::get('/available-trainings', [App\Http\Controllers\LmsController::class, 'getAvailableTrainings'])->name('available-trainings');
Route::get('/employee-training-report', [App\Http\Controllers\LmsController::class, 'getEmployeeTrainingReport'])->name('employee-training-report');
Route::get('/employee-training-report-page', [App\Http\Controllers\LmsController::class, 'employeeTrainingReportPage'])->name('employee-training-report-page');

    
    // My Courses
    Route::get('/my-courses', [App\Http\Controllers\LmsController::class, 'myCourses'])->name('my-courses');
    
    // Reports
    Route::get('/reports', [App\Http\Controllers\LmsController::class, 'reports'])->name('reports');
    
    // File Management
    Route::post('/cleanup-files', [App\Http\Controllers\LmsController::class, 'cleanupInvalidFiles'])->name('cleanup-files');
    
    // Categories
    Route::resource('categories', App\Http\Controllers\LmsCategoryController::class);
    
    // Lessons - REMOVED - using sessions instead
    // Route::resource('lessons', App\Http\Controllers\LmsLessonController::class);
    
    // Enrollments
    Route::resource('enrollments', App\Http\Controllers\LmsEnrollmentController::class);
    
        // Quizzes
    Route::resource('quizzes', App\Http\Controllers\LmsQuizController::class);
    Route::resource('quizzes.questions', App\Http\Controllers\LmsQuizQuestionController::class)->except(['show']);
    
    // Questionnaire routes
    Route::resource('questionnaires', App\Http\Controllers\LmsQuestionnaireController::class);
    Route::resource('questionnaires.questions', App\Http\Controllers\LmsQuestionnaireQuestionController::class)->except(['show']);
    
    // Course Quiz and Questionnaire Management
    Route::post('/courses/{course}/quizzes/attach', [App\Http\Controllers\LmsController::class, 'attachQuiz'])->name('courses.quizzes.attach');
    Route::post('/courses/{course}/questionnaires/attach', [App\Http\Controllers\LmsController::class, 'attachQuestionnaire'])->name('courses.questionnaires.attach');
    
    // Course Curriculum
    Route::get('/courses/{course}/curriculum', [App\Http\Controllers\LmsController::class, 'showCourse'])->name('courses.curriculum');
    
    // Assignments
    Route::resource('assignments', App\Http\Controllers\LmsAssignmentController::class);
    
    // Certificates
    Route::resource('certificates', App\Http\Controllers\LmsCertificateController::class);
    
    // Certificate Templates
    Route::resource('certificate-templates', App\Http\Controllers\CertificateTemplateController::class);
    Route::get('/certificate-templates/{template}/preview', [App\Http\Controllers\CertificateTemplateController::class, 'preview'])
        ->name('certificate-templates.preview');
    
    // Certificate Download
    Route::get('/certificates/{certificate}/download', [App\Http\Controllers\LmsCertificateController::class, 'download'])
        ->name('certificates.download');
    
    // Certificate Preview
    Route::get('/certificates/{certificate}/preview', [App\Http\Controllers\LmsCertificateController::class, 'preview'])
        ->name('certificates.preview');
    
    // Issue certificates for a training schedule (attended participants)
    Route::post('/schedules/{schedule}/issue-certificates', [TrainingScheduleController::class, 'issueCertificates'])
        ->name('schedules.issue-certificates');
    
    // Discussions
    Route::resource('discussions', App\Http\Controllers\LmsDiscussionController::class);
    
    // Quiz Report
    Route::get('/quiz-report', [App\Http\Controllers\LmsController::class, 'getQuizReport'])->name('quiz-report');
    Route::get('/quiz-report-page', function () {
        return Inertia::render('Lms/QuizReport');
    })->name('quiz-report-page');
});

// Training Schedule Routes
Route::middleware(['auth', 'verified'])->prefix('lms')->name('lms.')->group(function () {
    // Training Schedules
    Route::get('/schedules', [TrainingScheduleController::class, 'index'])->name('schedules.index');
    Route::get('/schedules/create', [TrainingScheduleController::class, 'create'])->name('schedules.create');
    Route::post('/schedules', [TrainingScheduleController::class, 'store'])->name('schedules.store');
    Route::get('/schedules/{schedule}', [TrainingScheduleController::class, 'show'])->name('schedules.show');
    Route::get('/schedules/{schedule}/edit', [TrainingScheduleController::class, 'edit'])->name('schedules.edit');
    Route::put('/schedules/{schedule}', [TrainingScheduleController::class, 'update'])->name('schedules.update');
    Route::delete('/schedules/{schedule}', [TrainingScheduleController::class, 'destroy'])->name('schedules.destroy');
    
    // Invitations
    Route::post('/schedules/{schedule}/invite', [TrainingScheduleController::class, 'inviteParticipants'])->name('schedules.invite');
    Route::delete('/schedules/{schedule}/participants/{invitation}', [TrainingScheduleController::class, 'removeParticipant'])->name('schedules.remove-participant');
Route::put('/schedules/{schedule}/participants/{invitation}/mark-attended', [TrainingScheduleController::class, 'markAttended'])->name('schedules.mark-attended');
    
    // QR Code Check-in/out
    Route::post('/check-in', [TrainingScheduleController::class, 'checkIn'])->name('check-in');
    Route::post('/check-out', [TrainingScheduleController::class, 'checkOut'])->name('check-out');
    
    // Auto complete
    Route::post('/auto-complete', [TrainingScheduleController::class, 'autoComplete'])->name('auto-complete');
    
    // Export attendance
    Route::get('/schedules/{schedule}/export-attendance', [TrainingScheduleController::class, 'exportAttendance'])->name('schedules.export-attendance');
    
        // Flexible trainer management
        Route::post('/schedules/{schedule}/assign-trainer', [TrainingScheduleController::class, 'assignTrainer'])->name('schedules.assign-trainer');
        Route::delete('/schedules/{schedule}/trainers/{trainer}', [TrainingScheduleController::class, 'removeTrainer'])->name('schedules.remove-trainer');
        Route::put('/schedules/{schedule}/trainers/{trainer}/hours', [TrainingScheduleController::class, 'updateTrainerHours'])->name('schedules.update-trainer-hours');
        Route::get('/schedules/{schedule}/trainers', [TrainingScheduleController::class, 'getScheduleTrainers'])->name('schedules.trainers');
        Route::get('/schedules/{schedule}/relevant-participants', [TrainingScheduleController::class, 'getRelevantParticipants'])->name('schedules.relevant-participants');
        
        // Trainer invitation management
        Route::post('/schedules/{schedule}/invite-trainers', [TrainingScheduleController::class, 'inviteTrainers'])->name('schedules.invite-trainers');
        Route::put('/schedules/{schedule}/set-primary-trainer/{trainer}', [TrainingScheduleController::class, 'setPrimaryTrainer'])->name('schedules.set-primary-trainer');
        
        // Training notifications
        Route::get('/training-notifications', [TrainingScheduleController::class, 'getTrainingNotifications'])->name('training-notifications');
        Route::get('/training-history', [TrainingScheduleController::class, 'getTrainingHistory'])->name('training-history');
        Route::put('/schedules/{id}/status', [TrainingScheduleController::class, 'updateTrainingStatus'])->name('schedules.update-status');
        Route::post('/schedules/{id}/generate-certificates', [TrainingScheduleController::class, 'generateCertificatesForCompletedTraining'])->name('schedules.generate-certificates');
        Route::get('/schedules/{id}/reviews', [TrainingScheduleController::class, 'getTrainingReviews'])->name('schedules.reviews');
        Route::get('/schedules/{id}/trainer-ratings', [TrainingScheduleController::class, 'getTrainerRatings'])->name('schedules.trainer-ratings');
});

// LMS Curriculum Routes
Route::middleware(['auth', 'verified'])->prefix('lms/courses/{course}/curriculum')->name('lms.curriculum.')->group(function () {
    Route::get('/', [LmsCurriculumController::class, 'index'])->name('index');
    
    // Curriculum Sessions
    Route::post('/sessions', [LmsCurriculumController::class, 'storeSession'])->name('sessions.store');
    Route::put('/sessions/{item}', [LmsCurriculumController::class, 'updateSession'])->name('sessions.update');
    Route::delete('/sessions/{item}', [LmsCurriculumController::class, 'destroySession'])->name('sessions.destroy');
    Route::post('/reorder', [LmsCurriculumController::class, 'reorderItems'])->name('reorder');
    
    // Curriculum Materials
    Route::post('/sessions/{item}/materials', [LmsCurriculumController::class, 'storeMaterial'])->name('materials.store');
    Route::put('/sessions/{item}/materials/{material}', [LmsCurriculumController::class, 'updateMaterial'])->name('materials.update');
    Route::delete('/sessions/{item}/materials/{material}', [LmsCurriculumController::class, 'destroyMaterial'])->name('materials.destroy');
});

// LMS Curriculum Page Route
Route::middleware(['auth', 'verified'])->get('/lms/courses/{course}/curriculum-page', function ($course) {
    $courseData = \App\Models\LmsCourse::findOrFail($course);
    return Inertia::render('Lms/Courses/Curriculum/Index', ['course' => $courseData]);
})->name('lms.courses.curriculum');

// Training Compliance Routes
Route::middleware(['auth', 'verified'])->prefix('training')->name('training.')->group(function () {
    // Compliance Dashboard
    Route::get('/compliance/dashboard', [TrainingComplianceController::class, 'dashboard'])->name('compliance.dashboard');
    
    // Compliance Reports
    Route::get('/compliance/report', [TrainingComplianceController::class, 'complianceReport'])->name('compliance.report');
    Route::get('/compliance/user/{user}', [TrainingComplianceController::class, 'userCompliance'])->name('compliance.user');
    
    // Trainer Reports
    Route::get('/compliance/trainer-report', [TrainingComplianceController::class, 'trainerReport'])->name('compliance.trainer-report');
    Route::get('/compliance/trainer/{trainer}', [TrainingComplianceController::class, 'trainerDetail'])->name('compliance.trainer');
    
    // Course Reports
    Route::get('/compliance/course-report', [TrainingComplianceController::class, 'courseReport'])->name('compliance.course-report');
    
    // Export
    Route::get('/compliance/export', [TrainingComplianceController::class, 'exportComplianceReport'])->name('compliance.export');
});

// Jabatan Training Management Routes
Route::middleware(['auth', 'verified'])->prefix('jabatan-training')->name('jabatan-training.')->group(function () {
    Route::get('/', [JabatanTrainingController::class, 'index'])->name('index');
    Route::get('/create', [JabatanTrainingController::class, 'create'])->name('create');
    Route::post('/', [JabatanTrainingController::class, 'store'])->name('store');
    Route::get('/{jabatanTraining}', [JabatanTrainingController::class, 'show'])->name('show');
    Route::get('/{jabatanTraining}/edit', [JabatanTrainingController::class, 'edit'])->name('edit');
    Route::put('/{jabatanTraining}', [JabatanTrainingController::class, 'update'])->name('update');
    Route::delete('/{jabatanTraining}', [JabatanTrainingController::class, 'destroy'])->name('destroy');
    
    // Bulk operations
    Route::post('/bulk-assign', [JabatanTrainingController::class, 'bulkAssign'])->name('bulk-assign');
    
    // API endpoints
    Route::get('/api/jabatan/{jabatan}/trainings', [JabatanTrainingController::class, 'getTrainingsForJabatan'])->name('api.jabatan.trainings');
    Route::get('/api/jabatan/{jabatan}/users', [JabatanTrainingController::class, 'getUsersForJabatan'])->name('api.jabatan.users');
});

  // Attendance Routes
  Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/attendance', [\App\Http\Controllers\AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/api/attendance/calendar-data', [\App\Http\Controllers\AttendanceController::class, 'getCalendarData'])->name('api.attendance.calendar-data');
    Route::post('/api/attendance/absent-request', [\App\Http\Controllers\AttendanceController::class, 'submitAbsentRequest'])->name('api.attendance.absent-request');
    
    // Absent Report Routes
    Route::get('/attendance/report', [\App\Http\Controllers\AttendanceController::class, 'report'])->name('attendance.report');
    Route::get('/attendance/report/data', [\App\Http\Controllers\AttendanceController::class, 'getReportData'])->name('attendance.report.data');
    Route::get('/attendance/report/export', [\App\Http\Controllers\AttendanceController::class, 'exportReport'])->name('attendance.report.export');
  });

// Approval Routes
Route::middleware(['auth', 'verified'])->prefix('api/approval')->group(function () {
    Route::get('/pending', [\App\Http\Controllers\ApprovalController::class, 'getPendingApprovals'])->name('api.approval.pending');
    Route::get('/pending-hrd', [\App\Http\Controllers\ApprovalController::class, 'getPendingHrdApprovals'])->name('api.approval.pending-hrd');
    Route::get('/stats', [\App\Http\Controllers\ApprovalController::class, 'getApprovalStats'])->name('api.approval.stats');
    Route::get('/my-requests', [\App\Http\Controllers\ApprovalController::class, 'getMyRequests'])->name('api.approval.my-requests');
    Route::get('/notifications', [\App\Http\Controllers\ApprovalController::class, 'getLeaveNotifications'])->name('api.approval.notifications');
    Route::get('/{id}', [\App\Http\Controllers\ApprovalController::class, 'getApprovalDetails'])->name('api.approval.details');
    Route::post('/{id}/approve', [\App\Http\Controllers\ApprovalController::class, 'approve'])->name('api.approval.approve');
    Route::post('/{id}/reject', [\App\Http\Controllers\ApprovalController::class, 'reject'])->name('api.approval.reject');
    Route::post('/{id}/hrd-approve', [\App\Http\Controllers\ApprovalController::class, 'hrdApprove'])->name('api.approval.hrd-approve');
    Route::post('/{id}/hrd-reject', [\App\Http\Controllers\ApprovalController::class, 'hrdReject'])->name('api.approval.hrd-reject');
});

// Holiday Attendance Routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/holiday-attendance', [\App\Http\Controllers\HolidayAttendanceController::class, 'index'])->name('holiday-attendance.index');
    Route::post('/api/holiday-attendance/process', [\App\Http\Controllers\HolidayAttendanceController::class, 'processHoliday'])->name('api.holiday-attendance.process');
    Route::get('/api/holiday-attendance/workers', [\App\Http\Controllers\HolidayAttendanceController::class, 'getHolidayWorkers'])->name('api.holiday-attendance.workers');
    Route::get('/api/holiday-attendance/employee-history/{userId}', [\App\Http\Controllers\HolidayAttendanceController::class, 'getEmployeeHistory'])->name('api.holiday-attendance.employee-history');
    Route::post('/api/holiday-attendance/use-extra-off', [\App\Http\Controllers\HolidayAttendanceController::class, 'useExtraOffDay'])->name('api.holiday-attendance.use-extra-off');
    Route::get('/api/holiday-attendance/my-extra-off-days', [\App\Http\Controllers\HolidayAttendanceController::class, 'getMyExtraOffDays'])->name('api.holiday-attendance.my-extra-off-days');
    Route::get('/api/holiday-attendance/statistics', [\App\Http\Controllers\HolidayAttendanceController::class, 'getStatistics'])->name('api.holiday-attendance.statistics');
    Route::get('/holiday-attendance/export', [\App\Http\Controllers\HolidayAttendanceController::class, 'export'])->name('holiday-attendance.export');
    
    // Extra Off API Routes
    Route::prefix('api/extra-off')->group(function () {
        // User routes (authenticated users)
        Route::get('/balance', [\App\Http\Controllers\ExtraOffController::class, 'getBalance'])->name('api.extra-off.balance');
        Route::get('/transactions', [\App\Http\Controllers\ExtraOffController::class, 'getTransactions'])->name('api.extra-off.transactions');
        Route::post('/use', [\App\Http\Controllers\ExtraOffController::class, 'useExtraOff'])->name('api.extra-off.use');
        
        // Admin routes (require authentication only for now)
        Route::post('/adjust', [\App\Http\Controllers\ExtraOffController::class, 'adjustBalance'])->name('api.extra-off.adjust');
        Route::post('/detect', [\App\Http\Controllers\ExtraOffController::class, 'detect'])->name('api.extra-off.detect');
        Route::get('/detect', [\App\Http\Controllers\ExtraOffController::class, 'detectUnscheduledWork'])->name('api.extra-off.detect.get');
        Route::get('/statistics', [\App\Http\Controllers\ExtraOffController::class, 'getStatistics'])->name('api.extra-off.statistics');
        Route::post('/initialize', [\App\Http\Controllers\ExtraOffController::class, 'initializeBalances'])->name('api.extra-off.initialize');
        Route::get('/balances', [\App\Http\Controllers\ExtraOffController::class, 'getAllBalances'])->name('api.extra-off.balances');
        Route::get('/all-transactions', [\App\Http\Controllers\ExtraOffController::class, 'getAllTransactionsSimple'])->name('api.extra-off.all-transactions');
    });
});

// Calendar API routes
Route::middleware(['auth'])->group(function () {
    Route::get('/api/holidays', [App\Http\Controllers\CalendarController::class, 'getHolidays']);
    Route::get('/api/reminders', [App\Http\Controllers\CalendarController::class, 'getReminders']);
    Route::post('/api/reminders', [App\Http\Controllers\CalendarController::class, 'storeReminder']);
    Route::delete('/api/reminders/{id}', [App\Http\Controllers\CalendarController::class, 'deleteReminder']);
    Route::get('/api/users/data', [App\Http\Controllers\CalendarController::class, 'getUsersData']);
});

// Notes API routes
Route::middleware(['auth'])->group(function () {
    Route::get('/api/notes', [App\Http\Controllers\NotesController::class, 'index']);
    Route::post('/api/notes', [App\Http\Controllers\NotesController::class, 'store']);
    Route::delete('/api/notes/{id}', [App\Http\Controllers\NotesController::class, 'destroy']);
});

// Live Support API routes
Route::middleware(['auth'])->group(function () {
    // User routes
    Route::get('/api/support/conversations', [App\Http\Controllers\LiveSupportController::class, 'getUserConversations']);
    Route::post('/api/support/conversations', [App\Http\Controllers\LiveSupportController::class, 'createConversation']);
    Route::get('/api/support/conversations/{id}/messages', [App\Http\Controllers\LiveSupportController::class, 'getConversationMessages']);
    Route::post('/api/support/conversations/{id}/messages', [App\Http\Controllers\LiveSupportController::class, 'sendMessage']);
    
    // Admin routes
    Route::get('/api/support/admin/conversations', [App\Http\Controllers\LiveSupportController::class, 'getAllConversations']);
    Route::post('/api/support/admin/conversations/{id}/reply', [App\Http\Controllers\LiveSupportController::class, 'adminReply']);
    Route::put('/api/support/admin/conversations/{id}/status', [App\Http\Controllers\LiveSupportController::class, 'updateConversationStatus']);
});

// Support Admin Panel
Route::middleware(['auth'])->group(function () {
    Route::get('/support/admin', [App\Http\Controllers\SupportAdminController::class, 'index'])->name('support.admin');
});

// Birthday API routes
Route::middleware(['auth'])->group(function () {
    Route::get('/api/birthdays', [App\Http\Controllers\BirthdayController::class, 'getBirthdays']);
});

// Locked Budget Food Categories routes
Route::middleware(['auth'])->group(function () {
    Route::resource('locked-budget-food-categories', App\Http\Controllers\LockedBudgetFoodCategoryController::class);
});

// Employee Survey routes
Route::middleware(['auth'])->group(function () {
    Route::resource('employee-survey', App\Http\Controllers\EmployeeSurveyController::class);
    Route::get('/employee-survey-report', [App\Http\Controllers\EmployeeSurveyController::class, 'report'])->name('employee-survey.report');
});

require __DIR__.'/auth.php';
