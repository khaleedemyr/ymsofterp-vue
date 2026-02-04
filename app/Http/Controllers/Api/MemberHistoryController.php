<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MemberAppsMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MemberHistoryController extends Controller
{
    /**
     * Get member information by member_id or mobile phone
     */
    public function getMemberInfo(Request $request)
    {
        try {
            $request->validate([
                'search' => 'required|string',
            ]);

            $search = $request->search;

            // Find member by member_id or mobile_phone
            $member = MemberAppsMember::where('member_id', $search)
                ->orWhere('mobile_phone', $search)
                ->with('occupation')
                ->first();

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'member_id' => $member->member_id,
                    'nama_lengkap' => $member->nama_lengkap,
                    'email' => $member->email,
                    'mobile_phone' => $member->mobile_phone,
                    'photo' => $member->photo,
                    'tanggal_lahir' => $member->tanggal_lahir,
                    'jenis_kelamin' => $member->jenis_kelamin,
                    'pekerjaan' => $member->occupation ? $member->occupation->name : null,
                    'member_level' => $member->member_level,
                    'total_spending' => $member->total_spending,
                    'just_points' => $member->just_points,
                    'is_exclusive_member' => $member->is_exclusive_member,
                    'is_active' => $member->is_active,
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error getting member info: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data member'
            ], 500);
        }
    }

    /**
     * Get member transaction history
     */
    public function getMemberHistory(Request $request)
    {
        try {
            $request->validate([
                'member_id' => 'required|string',
                'limit' => 'nullable|integer|min:1|max:100',
                'offset' => 'nullable|integer|min:0',
            ]);

            $memberId = $request->member_id;
            $limit = $request->get('limit', 20);
            $offset = $request->get('offset', 0);

            // Verify member exists
            $member = MemberAppsMember::where('member_id', $memberId)->first();
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member tidak ditemukan'
                ], 404);
            }

            // Get transaction history from orders table
            $orders = DB::connection('db_justus')
                ->table('orders')
                ->leftJoin('tbl_data_outlet as o', 'orders.kode_outlet', '=', 'o.qr_code')
                ->where('orders.member_id', $memberId)
                ->where('orders.status', 'paid')
                ->select(
                    'orders.id',
                    'orders.nomor as order_id',
                    'orders.grand_total',
                    'orders.total as sub_total',
                    'orders.pb1 as tax',
                    'orders.service as service_charge',
                    'orders.discount',
                    'orders.cashback',
                    'orders.redeem_amount',
                    'orders.created_at',
                    'orders.kode_outlet',
                    'o.nama_outlet'
                )
                ->orderBy('orders.created_at', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get();

            // Get total count
            $totalCount = DB::connection('db_justus')
                ->table('orders')
                ->where('member_id', $memberId)
                ->where('status', 'paid')
                ->count();

            // Get total spending
            $totalSpending = DB::connection('db_justus')
                ->table('orders')
                ->where('member_id', $memberId)
                ->where('status', 'paid')
                ->sum('grand_total');

            // Format orders
            $formattedOrders = $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_id' => $order->order_id,
                    'grand_total' => (float) $order->grand_total,
                    'grand_total_formatted' => 'Rp ' . number_format($order->grand_total, 0, ',', '.'),
                    'sub_total' => (float) $order->sub_total,
                    'tax' => (float) $order->tax,
                    'service_charge' => (float) $order->service_charge,
                    'discount' => (float) $order->discount,
                    'points_earned' => (int) ($order->cashback ?? 0),
                    'points_redeemed' => (int) ($order->redeem_amount ?? 0),
                    'outlet_name' => $order->nama_outlet ?? 'Outlet Tidak Diketahui',
                    'kode_outlet' => $order->kode_outlet,
                    'created_at' => $order->created_at,
                    'created_at_formatted' => \Carbon\Carbon::parse($order->created_at)->format('d M Y, H:i'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'orders' => $formattedOrders,
                    'total_count' => $totalCount,
                    'total_spending' => (float) $totalSpending,
                    'total_spending_formatted' => 'Rp ' . number_format($totalSpending, 0, ',', '.'),
                    'limit' => $limit,
                    'offset' => $offset,
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error getting member history: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil history transaksi'
            ], 500);
        }
    }

    /**
     * Get member order detail including items
     */
    public function getOrderDetail(Request $request, $orderId)
    {
        try {
            // Get order detail
            $order = DB::connection('db_justus')
                ->table('orders')
                ->leftJoin('tbl_data_outlet as o', 'orders.kode_outlet', '=', 'o.qr_code')
                ->where('orders.id', $orderId)
                ->orWhere('orders.nomor', $orderId)
                ->select(
                    'orders.*',
                    'o.nama_outlet'
                )
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order tidak ditemukan'
                ], 404);
            }

            // Get order items
            $orderItems = DB::connection('db_justus')
                ->table('order_items')
                ->where('order_id', $order->id)
                ->select(
                    'id',
                    'item_id',
                    'item_name',
                    'qty',
                    'price',
                    'subtotal',
                    'notes',
                    'modifiers'
                )
                ->get();

            // Format response
            $formattedOrder = [
                'id' => $order->id,
                'order_id' => $order->nomor,
                'member_id' => $order->member_id,
                'grand_total' => (float) $order->grand_total,
                'grand_total_formatted' => 'Rp ' . number_format($order->grand_total, 0, ',', '.'),
                'sub_total' => (float) $order->total,
                'tax' => (float) $order->pb1,
                'service_charge' => (float) $order->service,
                'discount' => (float) $order->discount,
                'points_earned' => (int) ($order->cashback ?? 0),
                'points_redeemed' => (int) ($order->redeem_amount ?? 0),
                'outlet_name' => $order->nama_outlet ?? 'Outlet Tidak Diketahui',
                'kode_outlet' => $order->kode_outlet,
                'status' => $order->status,
                'payment_method' => $order->mode ?? null,
                'created_at' => $order->created_at,
                'created_at_formatted' => \Carbon\Carbon::parse($order->created_at)->format('d M Y, H:i'),
                'items' => $orderItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'item_id' => $item->item_id,
                        'item_name' => $item->item_name,
                        'quantity' => (int) $item->qty,
                        'price' => (float) $item->price,
                        'price_formatted' => 'Rp ' . number_format($item->price, 0, ',', '.'),
                        'sub_total' => (float) $item->subtotal,
                        'sub_total_formatted' => 'Rp ' . number_format($item->subtotal, 0, ',', '.'),
                        'notes' => $item->notes,
                        'modifiers' => $item->modifiers,
                    ];
                })
            ];

            return response()->json([
                'success' => true,
                'data' => $formattedOrder
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting order detail: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil detail order'
            ], 500);
        }
    }

    /**
     * Get member preferences (favorite menu/items)
     */
    public function getMemberPreferences(Request $request)
    {
        try {
            $request->validate([
                'member_id' => 'required|string',
                'limit' => 'nullable|integer|min:1|max:50',
            ]);

            $memberId = $request->member_id;
            $limit = $request->get('limit', 10);

            // Verify member exists
            $member = MemberAppsMember::where('member_id', $memberId)->first();
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member tidak ditemukan'
                ], 404);
            }

            // Get most ordered items (favorite items)
            $favoriteItems = DB::connection('db_justus')
                ->table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.member_id', $memberId)
                ->where('orders.status', 'paid')
                ->select(
                    'order_items.item_id',
                    'order_items.item_name',
                    DB::raw('COUNT(*) as order_count'),
                    DB::raw('SUM(order_items.qty) as total_quantity'),
                    DB::raw('AVG(order_items.price) as avg_price'),
                    DB::raw('MAX(orders.created_at) as last_ordered')
                )
                ->groupBy('order_items.item_id', 'order_items.item_name')
                ->orderBy('order_count', 'desc')
                ->limit($limit)
                ->get();

            // Format response with popular modifiers
            $formattedItems = $favoriteItems->map(function ($item) use ($memberId) {
                // Get all modifiers for this item
                $modifiersData = DB::connection('db_justus')
                    ->table('order_items')
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->where('orders.member_id', $memberId)
                    ->where('orders.status', 'paid')
                    ->where('order_items.item_id', $item->item_id)
                    ->whereNotNull('order_items.modifiers')
                    ->where('order_items.modifiers', '!=', '')
                    ->select('order_items.modifiers')
                    ->get();

                // Count modifier frequency
                $modifierCounts = [];
                foreach ($modifiersData as $data) {
                    try {
                        $modifiers = json_decode($data->modifiers, true);
                        if (is_array($modifiers)) {
                            foreach ($modifiers as $category => $choices) {
                                if (is_array($choices)) {
                                    foreach ($choices as $choice => $quantity) {
                                        if ($quantity > 0) {
                                            $key = "$category|$choice";
                                            $modifierCounts[$key] = ($modifierCounts[$key] ?? 0) + 1;
                                        }
                                    }
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // Skip invalid JSON
                    }
                }

                // Get top 5 most popular modifiers
                arsort($modifierCounts);
                $topModifiers = [];
                $count = 0;
                foreach ($modifierCounts as $key => $freq) {
                    if ($count >= 5) break;
                    list($category, $choice) = explode('|', $key);
                    $topModifiers[] = [
                        'category' => $category,
                        'choice' => $choice,
                        'frequency' => $freq
                    ];
                    $count++;
                }

                return [
                    'item_id' => $item->item_id,
                    'item_name' => $item->item_name,
                    'order_count' => (int) $item->order_count,
                    'total_quantity' => (int) $item->total_quantity,
                    'avg_price' => (float) $item->avg_price,
                    'avg_price_formatted' => 'Rp ' . number_format($item->avg_price, 0, ',', '.'),
                    'last_ordered' => $item->last_ordered,
                    'last_ordered_formatted' => \Carbon\Carbon::parse($item->last_ordered)->format('d M Y'),
                    'popular_modifiers' => $topModifiers,
                ];
            });

            // Get favorite outlet
            $favoriteOutlet = DB::connection('db_justus')
                ->table('orders')
                ->leftJoin('tbl_data_outlet as o', 'orders.kode_outlet', '=', 'o.qr_code')
                ->where('orders.member_id', $memberId)
                ->where('orders.status', 'paid')
                ->select(
                    'orders.kode_outlet',
                    'o.nama_outlet',
                    DB::raw('COUNT(*) as visit_count'),
                    DB::raw('SUM(orders.grand_total) as total_spent'),
                    DB::raw('MAX(orders.created_at) as last_visit')
                )
                ->groupBy('orders.kode_outlet', 'o.nama_outlet')
                ->orderBy('visit_count', 'desc')
                ->first();

            $formattedOutlet = null;
            if ($favoriteOutlet) {
                $formattedOutlet = [
                    'kode_outlet' => $favoriteOutlet->kode_outlet,
                    'nama_outlet' => $favoriteOutlet->nama_outlet ?? 'Outlet Tidak Diketahui',
                    'visit_count' => (int) $favoriteOutlet->visit_count,
                    'total_spent' => (float) $favoriteOutlet->total_spent,
                    'total_spent_formatted' => 'Rp ' . number_format($favoriteOutlet->total_spent, 0, ',', '.'),
                    'last_visit' => $favoriteOutlet->last_visit,
                    'last_visit_formatted' => \Carbon\Carbon::parse($favoriteOutlet->last_visit)->format('d M Y'),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'favorite_items' => $formattedItems,
                    'favorite_outlet' => $formattedOutlet,
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error getting member preferences: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data preferensi member'
            ], 500);
        }
    }
}
