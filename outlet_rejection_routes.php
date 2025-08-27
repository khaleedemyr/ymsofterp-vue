<?php

// =====================================================
// OUTLET REJECTION ROUTES
// =====================================================

// Add these routes to your routes/web.php file

Route::middleware(['auth', 'verified'])->group(function () {
    
    // Outlet Rejection Routes
    Route::prefix('outlet-rejections')->name('outlet-rejections.')->group(function () {
        
        // List all outlet rejections
        Route::get('/', [App\Http\Controllers\OutletRejectionController::class, 'index'])
            ->name('index');
        
        // Create new outlet rejection
        Route::get('/create', [App\Http\Controllers\OutletRejectionController::class, 'create'])
            ->name('create');
        
        Route::post('/', [App\Http\Controllers\OutletRejectionController::class, 'store'])
            ->name('store');
        
        // View outlet rejection details
        Route::get('/{id}', [App\Http\Controllers\OutletRejectionController::class, 'show'])
            ->name('show');
        
        // Edit outlet rejection (only draft status)
        Route::get('/{id}/edit', [App\Http\Controllers\OutletRejectionController::class, 'edit'])
            ->name('edit');
        
        Route::put('/{id}', [App\Http\Controllers\OutletRejectionController::class, 'update'])
            ->name('update');
        
        // Workflow actions
        Route::post('/{id}/submit', [App\Http\Controllers\OutletRejectionController::class, 'submit'])
            ->name('submit');
        
        Route::post('/{id}/approve', [App\Http\Controllers\OutletRejectionController::class, 'approve'])
            ->name('approve');
        
        Route::post('/{id}/complete', [App\Http\Controllers\OutletRejectionController::class, 'complete'])
            ->name('complete');
        
        Route::post('/{id}/cancel', [App\Http\Controllers\OutletRejectionController::class, 'cancel'])
            ->name('cancel');
        
        // Delete outlet rejection (only draft status)
        Route::delete('/{id}', [App\Http\Controllers\OutletRejectionController::class, 'destroy'])
            ->name('destroy');
        
        // API endpoints for AJAX requests
        Route::get('/api/items', [App\Http\Controllers\OutletRejectionController::class, 'getItems'])
            ->name('api.items');
        
        Route::get('/api/delivery-order-items', [App\Http\Controllers\OutletRejectionController::class, 'getDeliveryOrderItems'])
            ->name('api.delivery-order-items');
    });
});

// =====================================================
// MENU CONFIGURATION
// =====================================================

/*
Tambahkan menu "Outlet Rejection" ke dalam menu navigation.
Contoh untuk sidebar menu:

{
    "name": "Outlet Rejection",
    "icon": "fas fa-undo-alt", // atau icon yang sesuai
    "url": "/outlet-rejections",
    "permissions": ["view_outlet_rejection"],
    "children": [
        {
            "name": "List Rejection",
            "url": "/outlet-rejections",
            "permissions": ["view_outlet_rejection"]
        },
        {
            "name": "Create Rejection",
            "url": "/outlet-rejections/create",
            "permissions": ["create_outlet_rejection"]
        }
    ]
}

Atau jika menggunakan menu sederhana:

{
    "name": "Outlet Rejection",
    "icon": "fas fa-undo-alt",
    "url": "/outlet-rejections",
    "permissions": ["view_outlet_rejection"]
}
*/

// =====================================================
// PERMISSIONS (OPTIONAL)
// =====================================================

/*
Jika menggunakan sistem permission, tambahkan permissions berikut:

- view_outlet_rejection
- create_outlet_rejection
- edit_outlet_rejection
- delete_outlet_rejection
- submit_outlet_rejection
- approve_outlet_rejection
- complete_outlet_rejection
- cancel_outlet_rejection

Contoh seeder untuk permissions:

use Spatie\Permission\Models\Permission;

$permissions = [
    'view_outlet_rejection',
    'create_outlet_rejection',
    'edit_outlet_rejection',
    'delete_outlet_rejection',
    'submit_outlet_rejection',
    'approve_outlet_rejection',
    'complete_outlet_rejection',
    'cancel_outlet_rejection'
];

foreach ($permissions as $permission) {
    Permission::create(['name' => $permission]);
}
*/

// =====================================================
// NOTES
// =====================================================

/*
WORKFLOW PERMISSIONS:

1. Draft Status:
   - create_outlet_rejection (create, edit, delete)
   - view_outlet_rejection (view)

2. Submitted Status:
   - view_outlet_rejection (view)
   - approve_outlet_rejection (approve)
   - cancel_outlet_rejection (cancel)

3. Approved Status:
   - view_outlet_rejection (view)
   - complete_outlet_rejection (complete)
   - cancel_outlet_rejection (cancel)

4. Completed Status:
   - view_outlet_rejection (view only)

5. Cancelled Status:
   - view_outlet_rejection (view only)

ROLE SUGGESTIONS:

1. Outlet Staff:
   - view_outlet_rejection
   - create_outlet_rejection
   - submit_outlet_rejection

2. Warehouse Staff:
   - view_outlet_rejection
   - complete_outlet_rejection

3. Manager:
   - view_outlet_rejection
   - approve_outlet_rejection
   - cancel_outlet_rejection

4. Admin:
   - All permissions
*/
