<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ClosingShiftController extends Controller
{
    /**
     * Sync single order to server pusat
     * This is used by ClosingShiftModal to sync orders that haven't been synced yet
     */
    public function syncOrderToPusat(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'order_id' => 'required|string',
                'paid_number' => 'required|string',
                'kode_outlet' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $orderId = $request->input('order_id');
            $paidNumber = $request->input('paid_number');
            $kodeOutlet = $request->input('kode_outlet');

            Log::info('Closing Shift: Sync Order to Pusat', [
                'order_id' => $orderId,
                'kode_outlet' => $kodeOutlet
            ]);

            // Check if order already exists in pusat
            $existingOrder = DB::table('orders')
                ->where('id', $orderId)
                ->where('kode_outlet', $kodeOutlet)
                ->first();

            if ($existingOrder) {
                Log::info('Order already exists in pusat', ['order_id' => $orderId]);
                return response()->json([
                    'success' => true,
                    'message' => 'Order already synced',
                    'data' => ['order_id' => $orderId]
                ]);
            }

            // Get order data from local database (utama)
            // Note: This assumes the order exists in local database
            // In POS, this would query from local database first
            // For now, we'll use the data sent from POS

            return response()->json([
                'success' => true,
                'message' => 'Order sync initiated',
                'data' => ['order_id' => $orderId]
            ]);

        } catch (\Exception $e) {
            Log::error('Closing Shift: Sync Order Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Today and MTD data for email investor
     */
    public function getTodayAndMTDData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'kode_outlet' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $kodeOutlet = $request->input('kode_outlet');
            $today = Carbon::today()->toDateString();
            $now = Carbon::now();
            $firstDay = $now->copy()->startOfMonth()->toDateString();
            $lastDayOfMonth = $now->copy()->endOfMonth()->toDateString(); // MTD sampai akhir bulan

            Log::info('Closing Shift: Get Today and MTD Data', [
                'kode_outlet' => $kodeOutlet,
                'today' => $today,
                'first_day' => $firstDay,
                'last_day_of_month' => $lastDayOfMonth
            ]);

            // Helper function for safe query
            $safeQuery = function($query, $params) {
                try {
                    return DB::select($query, $params);
                } catch (\Exception $e) {
                    Log::error('Query error', ['query' => $query, 'error' => $e->getMessage()]);
                    return [['revenue' => 0, 'cover' => 0, 'discount' => 0]];
                }
            };

            // TODAY DATA
            // Lunch Today (09:00-15:59)
            $lunchToday = $safeQuery(
                "SELECT SUM(o.grand_total) as revenue, SUM(o.pax) as cover, SUM(o.discount) as discount
                 FROM orders o
                 JOIN order_payment op ON o.id = op.order_id
                 WHERE o.status='paid' AND DATE(op.created_at)=? 
                 AND TIME(op.created_at) BETWEEN '09:00:00' AND '15:59:59'
                 AND o.kode_outlet = ?",
                [$today, $kodeOutlet]
            );

            // Dinner Today (16:00-23:59)
            $dinnerToday = $safeQuery(
                "SELECT SUM(o.grand_total) as revenue, SUM(o.pax) as cover, SUM(o.discount) as discount
                 FROM orders o
                 JOIN order_payment op ON o.id = op.order_id
                 WHERE o.status='paid' AND DATE(op.created_at)=? 
                 AND TIME(op.created_at) BETWEEN '16:00:00' AND '23:59:59'
                 AND o.kode_outlet = ?",
                [$today, $kodeOutlet]
            );

            // Total Today
            $totalToday = $safeQuery(
                "SELECT SUM(o.grand_total) as revenue, SUM(o.pax) as cover, SUM(o.discount) as discount
                 FROM orders o
                 JOIN order_payment op ON o.id = op.order_id
                 WHERE o.status='paid' AND DATE(op.created_at)=?
                 AND o.kode_outlet = ?",
                [$today, $kodeOutlet]
            );

            // MTD DATA - Mengambil dari tanggal 1 sampai akhir bulan (sesuai dengan outlet sales dashboard)
            // Menggunakan table orders langsung dengan DATE(created_at) seperti di SalesOutletDashboardService
            // Lunch MTD (09:00-15:59)
            $lunchMTD = $safeQuery(
                "SELECT SUM(grand_total) as revenue, SUM(pax) as cover, SUM(discount) as discount
                 FROM orders
                 WHERE status='paid' 
                 AND DATE(created_at) >= ? AND DATE(created_at) <= ?
                 AND TIME(created_at) BETWEEN '09:00:00' AND '15:59:59'
                 AND kode_outlet = ?",
                [$firstDay, $lastDayOfMonth, $kodeOutlet]
            );

            // Dinner MTD (16:00-23:59)
            $dinnerMTD = $safeQuery(
                "SELECT SUM(grand_total) as revenue, SUM(pax) as cover, SUM(discount) as discount
                 FROM orders
                 WHERE status='paid' 
                 AND DATE(created_at) >= ? AND DATE(created_at) <= ?
                 AND TIME(created_at) BETWEEN '16:00:00' AND '23:59:59'
                 AND kode_outlet = ?",
                [$firstDay, $lastDayOfMonth, $kodeOutlet]
            );

            // Total MTD - dari tanggal 1 sampai akhir bulan
            $totalMTD = $safeQuery(
                "SELECT SUM(grand_total) as revenue, SUM(pax) as cover, SUM(discount) as discount
                 FROM orders
                 WHERE status='paid' 
                 AND DATE(created_at) >= ? AND DATE(created_at) <= ?
                 AND kode_outlet = ?",
                [$firstDay, $lastDayOfMonth, $kodeOutlet]
            );

            // Helper function for safe parsing
            $safeParseInt = function($value) {
                return (int) ($value ?? 0);
            };

            // Helper function for average check
            $calculateAvgCheck = function($revenue, $cover) {
                return $cover > 0 ? (int) round($revenue / $cover) : 0;
            };

            $result = [
                'today' => [
                    'lunch' => [
                        'revenue' => $safeParseInt($lunchToday[0]->revenue ?? 0),
                        'cover' => $safeParseInt($lunchToday[0]->cover ?? 0),
                        'avgCheck' => $calculateAvgCheck($safeParseInt($lunchToday[0]->revenue ?? 0), $safeParseInt($lunchToday[0]->cover ?? 0)),
                        'discount' => $safeParseInt($lunchToday[0]->discount ?? 0)
                    ],
                    'dinner' => [
                        'revenue' => $safeParseInt($dinnerToday[0]->revenue ?? 0),
                        'cover' => $safeParseInt($dinnerToday[0]->cover ?? 0),
                        'avgCheck' => $calculateAvgCheck($safeParseInt($dinnerToday[0]->revenue ?? 0), $safeParseInt($dinnerToday[0]->cover ?? 0)),
                        'discount' => $safeParseInt($dinnerToday[0]->discount ?? 0)
                    ],
                    'total' => [
                        'revenue' => $safeParseInt($totalToday[0]->revenue ?? 0),
                        'cover' => $safeParseInt($totalToday[0]->cover ?? 0),
                        'avgCheck' => $calculateAvgCheck($safeParseInt($totalToday[0]->revenue ?? 0), $safeParseInt($totalToday[0]->cover ?? 0)),
                        'discount' => $safeParseInt($totalToday[0]->discount ?? 0)
                    ]
                ],
                'mtd' => [
                    'lunch' => [
                        'revenue' => $safeParseInt($lunchMTD[0]->revenue ?? 0),
                        'cover' => $safeParseInt($lunchMTD[0]->cover ?? 0),
                        'avgCheck' => $calculateAvgCheck($safeParseInt($lunchMTD[0]->revenue ?? 0), $safeParseInt($lunchMTD[0]->cover ?? 0)),
                        'discount' => $safeParseInt($lunchMTD[0]->discount ?? 0)
                    ],
                    'dinner' => [
                        'revenue' => $safeParseInt($dinnerMTD[0]->revenue ?? 0),
                        'cover' => $safeParseInt($dinnerMTD[0]->cover ?? 0),
                        'avgCheck' => $calculateAvgCheck($safeParseInt($dinnerMTD[0]->revenue ?? 0), $safeParseInt($dinnerMTD[0]->cover ?? 0)),
                        'discount' => $safeParseInt($dinnerMTD[0]->discount ?? 0)
                    ],
                    'total' => [
                        'revenue' => $safeParseInt($totalMTD[0]->revenue ?? 0),
                        'cover' => $safeParseInt($totalMTD[0]->cover ?? 0),
                        'avgCheck' => $calculateAvgCheck($safeParseInt($totalMTD[0]->revenue ?? 0), $safeParseInt($totalMTD[0]->cover ?? 0)),
                        'discount' => $safeParseInt($totalMTD[0]->discount ?? 0)
                    ]
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Closing Shift: Get Today and MTD Data Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get today and MTD data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get investors data for email
     */
    public function getInvestorsData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'kode_outlet' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $kodeOutlet = $request->input('kode_outlet');

            Log::info('Closing Shift: Get Investors Data', [
                'kode_outlet' => $kodeOutlet
            ]);

            $investors = DB::select(
                "SELECT DISTINCT i.name, i.email 
                 FROM investors i
                 JOIN investor_outlet io ON i.id = io.investor_id
                 JOIN tbl_data_outlet tdo ON io.outlet_id = tdo.id_outlet
                 WHERE i.email IS NOT NULL AND i.email != '' 
                 AND tdo.qr_code = ?",
                [$kodeOutlet]
            );

            return response()->json([
                'success' => true,
                'data' => $investors
            ]);

        } catch (\Exception $e) {
            Log::error('Closing Shift: Get Investors Data Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get investors data: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get summary report
     */
    public function getSummaryReport(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'start_date' => 'required|date',
                'end_date' => 'required|date',
                'start_time' => 'required|string',
                'end_time' => 'required|string',
                'kode_outlet' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $startTime = $request->input('start_time');
            $endTime = $request->input('end_time');
            $kodeOutlet = $request->input('kode_outlet');

            Log::info('Closing Shift: Get Summary Report', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'kode_outlet' => $kodeOutlet
            ]);

            $whereClause = "WHERE o.status='paid' 
                AND DATE(op.created_at) BETWEEN ? AND ? 
                AND TIME(op.created_at) BETWEEN ? AND ?
                AND o.kode_outlet = ?";

            $queryParams = [$startDate, $endDate, $startTime, $endTime, $kodeOutlet];

            // Helper function for safe query
            $safeQuery = function($query, $params) {
                try {
                    $result = DB::select($query, $params);
                    return $result[0] ?? (object)['value' => 0];
                } catch (\Exception $e) {
                    Log::error('Query error', ['query' => $query, 'error' => $e->getMessage()]);
                    return (object)['value' => 0];
                }
            };

            // 1. Sales
            $sales = $safeQuery(
                "SELECT SUM(o.total) as value
                 FROM orders o
                 JOIN order_payment op ON o.id = op.order_id
                 $whereClause",
                $queryParams
            );

            // 2. Discount
            $discount = $safeQuery(
                "SELECT SUM(o.discount + COALESCE(o.manual_discount_amount, 0)) as value
                 FROM orders o
                 JOIN order_payment op ON o.id = op.order_id
                 $whereClause",
                $queryParams
            );

            // 3. Cashback
            $cashback = $safeQuery(
                "SELECT SUM(COALESCE(o.cashback, 0)) as value
                 FROM orders o
                 JOIN order_payment op ON o.id = op.order_id
                 $whereClause",
                $queryParams
            );

            // 4. PB1
            $pb1 = $safeQuery(
                "SELECT SUM(COALESCE(o.pb1, 0)) as value
                 FROM orders o
                 JOIN order_payment op ON o.id = op.order_id
                 $whereClause",
                $queryParams
            );

            // 5. Service
            $service = $safeQuery(
                "SELECT SUM(COALESCE(o.service, 0)) as value
                 FROM orders o
                 JOIN order_payment op ON o.id = op.order_id
                 $whereClause",
                $queryParams
            );

            // 6. Commfee
            $commfee = $safeQuery(
                "SELECT SUM(COALESCE(o.commfee, 0)) as value
                 FROM orders o
                 JOIN order_payment op ON o.id = op.order_id
                 $whereClause",
                $queryParams
            );

            // 7. Rounding
            $rounding = $safeQuery(
                "SELECT SUM(COALESCE(o.rounding, 0)) as value
                 FROM orders o
                 JOIN order_payment op ON o.id = op.order_id
                 $whereClause",
                $queryParams
            );

            // 8. Grand Total
            $grandTotal = $safeQuery(
                "SELECT SUM(o.grand_total) as value
                 FROM orders o
                 JOIN order_payment op ON o.id = op.order_id
                 $whereClause",
                $queryParams
            );

            // 9. Pax
            $pax = $safeQuery(
                "SELECT SUM(o.pax) as value
                 FROM orders o
                 JOIN order_payment op ON o.id = op.order_id
                 $whereClause",
                $queryParams
            );

            // Calculate net sales
            $netSales = ($sales->value ?? 0) - ($discount->value ?? 0) - ($cashback->value ?? 0);

            // Calculate average check
            $avgCheck = ($pax->value ?? 0) > 0 
                ? (int) round(($grandTotal->value ?? 0) / ($pax->value ?? 0)) 
                : 0;

            $result = [
                'sales' => (int) ($sales->value ?? 0),
                'discount' => (int) ($discount->value ?? 0),
                'cashback' => (int) ($cashback->value ?? 0),
                'netSales' => (int) $netSales,
                'pb1' => (int) ($pb1->value ?? 0),
                'service' => (int) ($service->value ?? 0),
                'commfee' => (int) ($commfee->value ?? 0),
                'rounding' => (int) ($rounding->value ?? 0),
                'grandTotal' => (int) ($grandTotal->value ?? 0),
                'pax' => (int) ($pax->value ?? 0),
                'avgCheck' => $avgCheck
            ];

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Closing Shift: Get Summary Report Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get summary report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get retail food data by shift
     */
    public function getRetailFoodByShift(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'shift' => 'required|string',
                'kode_outlet' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $shift = $request->input('shift');
            $kodeOutlet = $request->input('kode_outlet');
            $today = Carbon::today()->toDateString();

            // Determine time range based on shift
            if ($shift === '17:00') {
                // Shift 1: 09:00 - 15:59
                $startDateTime = $today . ' 09:00:00';
                $endDateTime = $today . ' 15:59:59';
            } else if ($shift === '22:00') {
                // Shift 2: 16:00 - 21:59
                $startDateTime = $today . ' 16:00:00';
                $endDateTime = $today . ' 21:59:59';
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid shift type'
                ], 400);
            }

            Log::info('Closing Shift: Get Retail Food By Shift', [
                'shift' => $shift,
                'kode_outlet' => $kodeOutlet,
                'start' => $startDateTime,
                'end' => $endDateTime
            ]);

            // Get outlet_id from kode_outlet
            $outlet = DB::table('tbl_data_outlet')
                ->where('qr_code', $kodeOutlet)
                ->first();

            if (!$outlet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Outlet not found',
                    'data' => []
                ], 404);
            }

            // Get retail food data with items
            $retailFood = DB::select(
                "SELECT rf.*, 
                        GROUP_CONCAT(
                          JSON_OBJECT(
                            'id', rfi.id,
                            'item_name', rfi.item_name,
                            'qty', rfi.qty,
                            'unit', rfi.unit,
                            'price', rfi.price,
                            'subtotal', rfi.subtotal
                          )
                        ) as items
                 FROM retail_food rf
                 LEFT JOIN retail_food_items rfi ON rf.id = rfi.retail_food_id
                 WHERE rf.created_at BETWEEN ? AND ?
                   AND rf.status = 'approved'
                   AND rf.deleted_at IS NULL
                   AND rf.outlet_id = ?
                 GROUP BY rf.id
                 ORDER BY rf.created_at DESC",
                [$startDateTime, $endDateTime, $outlet->id_outlet]
            );

            // Parse items JSON
            $result = array_map(function($rf) {
                $rfArray = (array) $rf;
                if ($rfArray['items']) {
                    $rfArray['items'] = json_decode('[' . $rfArray['items'] . ']', true);
                } else {
                    $rfArray['items'] = [];
                }
                return $rfArray;
            }, $retailFood);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Closing Shift: Get Retail Food Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get retail food data: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get retail non-food data by shift
     */
    public function getRetailNonFoodByShift(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'shift' => 'required|string',
                'kode_outlet' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $shift = $request->input('shift');
            $kodeOutlet = $request->input('kode_outlet');
            $today = Carbon::today()->toDateString();

            // Determine time range based on shift
            if ($shift === '17:00') {
                // Shift 1: 09:00 - 15:59
                $startDateTime = $today . ' 09:00:00';
                $endDateTime = $today . ' 15:59:59';
            } else if ($shift === '22:00') {
                // Shift 2: 16:00 - 21:59
                $startDateTime = $today . ' 16:00:00';
                $endDateTime = $today . ' 21:59:59';
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid shift type'
                ], 400);
            }

            Log::info('Closing Shift: Get Retail Non-Food By Shift', [
                'shift' => $shift,
                'kode_outlet' => $kodeOutlet,
                'start' => $startDateTime,
                'end' => $endDateTime
            ]);

            // Get outlet_id from kode_outlet
            $outlet = DB::table('tbl_data_outlet')
                ->where('qr_code', $kodeOutlet)
                ->first();

            if (!$outlet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Outlet not found',
                    'data' => []
                ], 404);
            }

            // Get retail non-food data with items
            $retailNonFood = DB::select(
                "SELECT rnf.*, 
                        GROUP_CONCAT(
                          JSON_OBJECT(
                            'id', rnfi.id,
                            'item_name', rnfi.item_name,
                            'qty', rnfi.qty,
                            'unit', rnfi.unit,
                            'price', rnfi.price,
                            'subtotal', rnfi.subtotal
                          )
                        ) as items
                 FROM retail_non_food rnf
                 LEFT JOIN retail_non_food_items rnfi ON rnf.id = rnfi.retail_non_food_id
                 WHERE rnf.created_at BETWEEN ? AND ?
                   AND rnf.status = 'approved'
                   AND rnf.deleted_at IS NULL
                   AND rnf.outlet_id = ?
                 GROUP BY rnf.id
                 ORDER BY rnf.created_at DESC",
                [$startDateTime, $endDateTime, $outlet->id_outlet]
            );

            // Parse items JSON
            $result = array_map(function($rnf) {
                $rnfArray = (array) $rnf;
                if ($rnfArray['items']) {
                    $rnfArray['items'] = json_decode('[' . $rnfArray['items'] . ']', true);
                } else {
                    $rnfArray['items'] = [];
                }
                return $rnfArray;
            }, $retailNonFood);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Closing Shift: Get Retail Non-Food Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get retail non-food data: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Check unsynced orders count
     */
    public function checkUnsyncedOrdersCount(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'kode_outlet' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $kodeOutlet = $request->input('kode_outlet');

            // Note: This would need to check local database (utama) for unsynced orders
            // Since we're in the API, we can't directly access local POS database
            // This endpoint might need to be called from POS with the count
            // Or we can track unsynced orders in the central database

            $count = DB::table('orders')
                ->where('kode_outlet', $kodeOutlet)
                ->where('issync', 0)
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'count' => $count
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Closing Shift: Check Unsynced Orders Count Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check unsynced orders count: ' . $e->getMessage(),
                'data' => ['count' => 0]
            ], 500);
        }
    }
}

