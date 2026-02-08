<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Controller untuk menggabungkan semua pending approvals dalam 1 endpoint
 * OPTIMASI: Mengurangi jumlah API calls dari Home.vue dari 15+ menjadi 1
 */
class PendingApprovalController extends Controller
{
    /**
     * Get all pending approvals dalam 1 response
     * OPTIMASI: Cache hasil untuk 10 detik untuk mengurangi beban database
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllPendingApprovals(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: User not authenticated',
                    'data' => []
                ], 401);
            }

            // OPTIMASI: Cache hasil untuk 10 detik per user
            // Cache key berdasarkan user ID untuk personalisasi
            $cacheKey = 'all_pending_approvals_' . $user->id;
            $cacheTTL = 10; // 10 detik
            
            // Check if data is cached (before calling Cache::remember)
            $isCached = Cache::has($cacheKey);
            
            $result = Cache::remember($cacheKey, $cacheTTL, function() use ($user, $request) {
                // Limit per type (default 50, bisa di-override via request)
                $limit = $request->input('limit', 50);
            
            $data = [
                'purchase_requisitions' => [],
                'purchase_order_ops' => [],
                'contra_bons' => [],
                'approval' => [],
                'outlet_internal_use_waste' => [],
                'outlet_food_inventory_adjustment' => [],
                'food_inventory_adjustment' => [],
                'stock_opnames' => [],
                'outlet_transfer' => [],
                'warehouse_stock_opnames' => [],
                'employee_movements' => [],
                'coaching' => [],
                'schedule_attendance_correction' => [],
                'food_payment' => [],
                'non_food_payment' => [],
                'pr_food' => [],
                'po_food' => [],
                'ro_khusus' => [],
                'employee_resignation' => [],
            ];

            // Panggil method yang sudah ada dari controller lain
            // Gunakan try-catch untuk setiap call agar jika 1 error, yang lain tetap jalan
            
            // 1. Purchase Requisitions
            try {
                // OPTIMASI: Gunakan Laravel service container untuk resolve controller (mendukung dependency injection)
                $prController = app(\App\Http\Controllers\PurchaseRequisitionController::class);
                $prResponse = $prController->getPendingApprovals();
                if ($prResponse->getStatusCode() === 200) {
                    $prData = json_decode($prResponse->getContent(), true);
                    $data['purchase_requisitions'] = $prData['purchase_requisitions'] ?? [];
                    // Limit jika perlu
                    if ($limit > 0 && count($data['purchase_requisitions']) > $limit) {
                        $data['purchase_requisitions'] = array_slice($data['purchase_requisitions'], 0, $limit);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error loading Purchase Requisition approvals: ' . $e->getMessage());
            }

            // 2. Purchase Order Ops
            try {
                $poOpsController = app(\App\Http\Controllers\PurchaseOrderOpsController::class);
                $poOpsResponse = $poOpsController->getPendingApprovals();
                if ($poOpsResponse->getStatusCode() === 200) {
                    $poOpsData = json_decode($poOpsResponse->getContent(), true);
                    $data['purchase_order_ops'] = $poOpsData['data'] ?? [];
                    if ($limit > 0 && count($data['purchase_order_ops']) > $limit) {
                        $data['purchase_order_ops'] = array_slice($data['purchase_order_ops'], 0, $limit);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error loading PO Ops approvals: ' . $e->getMessage());
            }

            // 3. Contra Bon
            try {
                $cbController = app(\App\Http\Controllers\ContraBonController::class);
                $cbResponse = $cbController->getPendingApprovals($request);
                if ($cbResponse->getStatusCode() === 200) {
                    $cbData = json_decode($cbResponse->getContent(), true);
                    $data['contra_bons'] = $cbData['contra_bons'] ?? [];
                    if ($limit > 0 && count($data['contra_bons']) > $limit) {
                        $data['contra_bons'] = array_slice($data['contra_bons'], 0, $limit);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error loading Contra Bon approvals: ' . $e->getMessage());
            }

            // 4. Approval (General)
            try {
                $approvalController = app(\App\Http\Controllers\ApprovalController::class);
                $approvalResponse = $approvalController->getPendingApprovals($request);
                if ($approvalResponse->getStatusCode() === 200) {
                    $approvalData = json_decode($approvalResponse->getContent(), true);
                    $data['approval'] = $approvalData['data'] ?? $approvalData['approvals'] ?? [];
                    if ($limit > 0 && count($data['approval']) > $limit) {
                        $data['approval'] = array_slice($data['approval'], 0, $limit);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error loading Approval approvals: ' . $e->getMessage());
            }

            // 5. Outlet Internal Use Waste
            try {
                $oiuwController = app(\App\Http\Controllers\OutletInternalUseWasteController::class);
                $oiuwResponse = $oiuwController->getPendingApprovals($request);
                if ($oiuwResponse->getStatusCode() === 200) {
                    $oiuwData = json_decode($oiuwResponse->getContent(), true);
                    $data['outlet_internal_use_waste'] = $oiuwData['headers'] ?? $oiuwData['data'] ?? [];
                    if ($limit > 0 && count($data['outlet_internal_use_waste']) > $limit) {
                        $data['outlet_internal_use_waste'] = array_slice($data['outlet_internal_use_waste'], 0, $limit);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error loading Outlet Internal Use Waste approvals: ' . $e->getMessage());
            }

            // 6. Outlet Food Inventory Adjustment
            try {
                $ofiaController = app(\App\Http\Controllers\OutletFoodInventoryAdjustmentController::class);
                $ofiaResponse = $ofiaController->getPendingApprovals();
                if ($ofiaResponse->getStatusCode() === 200) {
                    $ofiaData = json_decode($ofiaResponse->getContent(), true);
                    $data['outlet_food_inventory_adjustment'] = $ofiaData['data'] ?? $ofiaData['adjustments'] ?? [];
                    if ($limit > 0 && count($data['outlet_food_inventory_adjustment']) > $limit) {
                        $data['outlet_food_inventory_adjustment'] = array_slice($data['outlet_food_inventory_adjustment'], 0, $limit);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error loading Outlet Food Inventory Adjustment approvals: ' . $e->getMessage());
            }

            // 6b. Warehouse Food Inventory Adjustment
            try {
                $fiaController = app(\App\Http\Controllers\FoodInventoryAdjustmentController::class);
                $fiaResponse = $fiaController->getPendingApprovals();
                if ($fiaResponse->getStatusCode() === 200) {
                    $fiaData = json_decode($fiaResponse->getContent(), true);
                    $data['food_inventory_adjustment'] = $fiaData['data'] ?? $fiaData['adjustments'] ?? [];
                    if ($limit > 0 && count($data['food_inventory_adjustment']) > $limit) {
                        $data['food_inventory_adjustment'] = array_slice($data['food_inventory_adjustment'], 0, $limit);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error loading Warehouse Food Inventory Adjustment approvals: ' . $e->getMessage());
            }

            // 7. Stock Opnames
            try {
                $soController = app(\App\Http\Controllers\StockOpnameController::class);
                $soResponse = $soController->getPendingApprovals();
                if ($soResponse->getStatusCode() === 200) {
                    $soData = json_decode($soResponse->getContent(), true);
                    $data['stock_opnames'] = $soData['data'] ?? $soData['stock_opnames'] ?? [];
                    if ($limit > 0 && count($data['stock_opnames']) > $limit) {
                        $data['stock_opnames'] = array_slice($data['stock_opnames'], 0, $limit);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error loading Stock Opname approvals: ' . $e->getMessage());
            }

            // 8. Outlet Transfer
            try {
                $otController = app(\App\Http\Controllers\OutletTransferController::class);
                $otResponse = $otController->getPendingApprovals();
                if ($otResponse->getStatusCode() === 200) {
                    $otData = json_decode($otResponse->getContent(), true);
                    $data['outlet_transfer'] = $otData['outlet_transfers'] ?? $otData['data'] ?? $otData['transfers'] ?? [];
                    if ($limit > 0 && count($data['outlet_transfer']) > $limit) {
                        $data['outlet_transfer'] = array_slice($data['outlet_transfer'], 0, $limit);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error loading Outlet Transfer approvals: ' . $e->getMessage());
            }

            // 9. Warehouse Stock Opnames
            try {
                $wsoController = app(\App\Http\Controllers\WarehouseStockOpnameController::class);
                $wsoResponse = $wsoController->getPendingApprovals();
                if ($wsoResponse->getStatusCode() === 200) {
                    $wsoData = json_decode($wsoResponse->getContent(), true);
                    $data['warehouse_stock_opnames'] = $wsoData['data'] ?? $wsoData['stock_opnames'] ?? [];
                    if ($limit > 0 && count($data['warehouse_stock_opnames']) > $limit) {
                        $data['warehouse_stock_opnames'] = array_slice($data['warehouse_stock_opnames'], 0, $limit);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error loading Warehouse Stock Opname approvals: ' . $e->getMessage());
            }

            // 10. Employee Movements
            try {
                $emController = app(\App\Http\Controllers\EmployeeMovementController::class);
                $emResponse = $emController->getPendingApprovals($request);
                if ($emResponse->getStatusCode() === 200) {
                    $emData = json_decode($emResponse->getContent(), true);
                    $data['employee_movements'] = $emData['data'] ?? $emData['movements'] ?? [];
                    if ($limit > 0 && count($data['employee_movements']) > $limit) {
                        $data['employee_movements'] = array_slice($data['employee_movements'], 0, $limit);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error loading Employee Movement approvals: ' . $e->getMessage());
            }

            // 11. Coaching
            try {
                $coachingController = app(\App\Http\Controllers\CoachingController::class);
                $coachingResponse = $coachingController->getPendingApprovals($request);
                if ($coachingResponse->getStatusCode() === 200) {
                    $coachingData = json_decode($coachingResponse->getContent(), true);
                    $data['coaching'] = $coachingData['data'] ?? $coachingData['coachings'] ?? [];
                    if ($limit > 0 && count($data['coaching']) > $limit) {
                        $data['coaching'] = array_slice($data['coaching'], 0, $limit);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error loading Coaching approvals: ' . $e->getMessage());
            }

            // 12. Schedule Attendance Correction (getPendingApprovals return key: 'approvals')
            try {
                $sacController = app(\App\Http\Controllers\ScheduleAttendanceCorrectionController::class);
                $sacResponse = $sacController->getPendingApprovals($request);
                if ($sacResponse->getStatusCode() === 200) {
                    $sacData = json_decode($sacResponse->getContent(), true);
                    $data['schedule_attendance_correction'] = $sacData['data'] ?? $sacData['corrections'] ?? $sacData['approvals'] ?? [];
                    if ($limit > 0 && count($data['schedule_attendance_correction']) > $limit) {
                        $data['schedule_attendance_correction'] = array_slice($data['schedule_attendance_correction'], 0, $limit);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error loading Schedule Attendance Correction approvals: ' . $e->getMessage());
            }

            // 13. Food Payment
            try {
                $fpController = app(\App\Http\Controllers\FoodPaymentController::class);
                $fpResponse = $fpController->getPendingApprovals($request);
                if ($fpResponse->getStatusCode() === 200) {
                    $fpData = json_decode($fpResponse->getContent(), true);
                    $data['food_payment'] = $fpData['data'] ?? $fpData['payments'] ?? [];
                    if ($limit > 0 && count($data['food_payment']) > $limit) {
                        $data['food_payment'] = array_slice($data['food_payment'], 0, $limit);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error loading Food Payment approvals: ' . $e->getMessage());
            }

            // 14. Non Food Payment
            try {
                $nfpController = app(\App\Http\Controllers\NonFoodPaymentController::class);
                $nfpResponse = $nfpController->getPendingApprovals($request);
                if ($nfpResponse->getStatusCode() === 200) {
                    $nfpData = json_decode($nfpResponse->getContent(), true);
                    $data['non_food_payment'] = $nfpData['data'] ?? $nfpData['payments'] ?? [];
                    if ($limit > 0 && count($data['non_food_payment']) > $limit) {
                        $data['non_food_payment'] = array_slice($data['non_food_payment'], 0, $limit);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error loading Non Food Payment approvals: ' . $e->getMessage());
            }

            // 15. PR Food
            try {
                $prfController = app(\App\Http\Controllers\PrFoodController::class);
                $prfResponse = $prfController->getPendingApprovals($request);
                if ($prfResponse->getStatusCode() === 200) {
                    $prfData = json_decode($prfResponse->getContent(), true);
                    $data['pr_food'] = $prfData['data'] ?? $prfData['pr_foods'] ?? [];
                    if ($limit > 0 && count($data['pr_food']) > $limit) {
                        $data['pr_food'] = array_slice($data['pr_food'], 0, $limit);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error loading PR Food approvals: ' . $e->getMessage());
            }

            // 16. PO Food
            try {
                $pofController = app(\App\Http\Controllers\PurchaseOrderFoodsController::class);
                $pofResponse = $pofController->getPendingApprovals($request);
                if ($pofResponse->getStatusCode() === 200) {
                    $pofData = json_decode($pofResponse->getContent(), true);
                    $data['po_food'] = $pofData['data'] ?? $pofData['po_foods'] ?? [];
                    if ($limit > 0 && count($data['po_food']) > $limit) {
                        $data['po_food'] = array_slice($data['po_food'], 0, $limit);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error loading PO Food approvals: ' . $e->getMessage());
            }

            // 17. RO Khusus
            try {
                // OPTIMASI: Gunakan Laravel service container untuk resolve controller dengan dependency injection
                $rokController = app(\App\Http\Controllers\FoodFloorOrderController::class);
                $rokResponse = $rokController->getPendingROKhususApprovals($request);
                if ($rokResponse->getStatusCode() === 200) {
                    $rokData = json_decode($rokResponse->getContent(), true);
                    $data['ro_khusus'] = $rokData['data'] ?? $rokData['ro_khusus'] ?? [];
                    if ($limit > 0 && count($data['ro_khusus']) > $limit) {
                        $data['ro_khusus'] = array_slice($data['ro_khusus'], 0, $limit);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error loading RO Khusus approvals: ' . $e->getMessage());
            }

            // 18. Employee Resignation
            try {
                $erController = app(\App\Http\Controllers\EmployeeResignationController::class);
                if (method_exists($erController, 'pendingApprovals')) {
                    $erResponse = $erController->pendingApprovals($request);
                    if ($erResponse->getStatusCode() === 200) {
                        $erData = json_decode($erResponse->getContent(), true);
                        $data['employee_resignation'] = $erData['data'] ?? $erData['resignations'] ?? [];
                        if ($limit > 0 && count($data['employee_resignation']) > $limit) {
                            $data['employee_resignation'] = array_slice($data['employee_resignation'], 0, $limit);
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error loading Employee Resignation approvals: ' . $e->getMessage());
            }

                return $data;
            });
            
            return response()->json([
                'success' => true,
                'data' => $result,
                'cached' => $isCached,
                'cache_ttl' => $cacheTTL
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in getAllPendingApprovals: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load pending approvals: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Clear cache for pending approvals (untuk testing atau manual refresh)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearCache(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $cacheKey = 'all_pending_approvals_' . $user->id;
            Cache::forget($cacheKey);
            
            return response()->json([
                'success' => true,
                'message' => 'Cache cleared successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error clearing cache: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache: ' . $e->getMessage()
            ], 500);
        }
    }
}
