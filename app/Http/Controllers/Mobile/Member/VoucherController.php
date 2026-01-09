<?php

namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberAppsMemberVoucher;
use App\Models\MemberAppsVoucher;
use App\Models\MemberAppsMember;
use App\Models\MemberAppsPointTransaction;
use App\Models\MemberAppsNotification;
use App\Services\PointEarningService;
use App\Services\FCMService;
use App\Events\VoucherReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VoucherController extends Controller
{
    /**
     * Get member's vouchers (active vouchers that belong to the authenticated member)
     */
    public function index(Request $request)
    {
        try {
            $member = $request->user();
            
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            \Log::info('Get Vouchers - Member ID: ' . $member->id);

            // Get pagination parameters
            $limit = $request->input('limit', 5); // Default 5 vouchers per page
            $offset = $request->input('offset', 0); // Default start from 0
            
            // Get vouchers for this member (both active and used)
            // Include used vouchers so they remain visible in the app
            $memberVouchers = MemberAppsMemberVoucher::where('member_id', $member->id)
                ->whereIn('status', ['active', 'used'])
                ->with(['voucher.outlets'])
                ->orderBy('created_at', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get();
            
            // Get total count for pagination info
            $totalCount = MemberAppsMemberVoucher::where('member_id', $member->id)
                ->whereIn('status', ['active', 'used'])
                ->count();

            \Log::info('Get Vouchers - Found member vouchers: ' . $memberVouchers->count());

            // Debug: Check if there are any member vouchers at all for this member
            $allMemberVouchers = MemberAppsMemberVoucher::where('member_id', $member->id)->get();
            \Log::info('Get Vouchers - All member vouchers (any status): ' . $allMemberVouchers->count());

            // Debug: Check distributions for this member
            // Query JSON array dengan cara yang benar
            $distributions = DB::table('member_apps_voucher_distributions')
                ->where('distribution_type', 'specific')
                ->whereRaw('JSON_CONTAINS(member_ids, ?)', [json_encode($member->id)])
                ->get();
            \Log::info('Get Vouchers - Distributions for member: ' . $distributions->count());
            
            // Jika tidak ada di member_vouchers tapi ada di distributions, mungkin perlu create
            if ($memberVouchers->count() == 0 && $distributions->count() > 0) {
                \Log::warning('Get Vouchers - Member has distributions but no member_vouchers records. Member ID: ' . $member->id);
            }

            $vouchers = $memberVouchers->map(function ($memberVoucher) {
                $voucher = $memberVoucher->voucher;
                
                if (!$voucher || !$voucher->is_active) {
                    return null;
                }

                // Get outlets where voucher can be redeemed
                // Outlets are already loaded via eager loading
                $outlets = $voucher->outlets ?? collect([]);
                $allOutlets = $outlets->isEmpty(); // If no outlets in pivot table, means all outlets
                $outletList = $outlets->map(function ($outlet) {
                    return [
                        'id' => $outlet->id_outlet,
                        'name' => $outlet->nama_outlet ?? 'Unknown Outlet',
                        'address' => $outlet->alamat_outlet ?? '',
                    ];
                })->toArray();

                // Check if voucher is expired
                // Don't filter out expired vouchers - they should remain visible with expired badge
                $today = Carbon::today();
                $isExpired = false;
                if ($memberVoucher->expires_at) {
                    $expiresAt = is_string($memberVoucher->expires_at) 
                        ? Carbon::parse($memberVoucher->expires_at)
                        : $memberVoucher->expires_at;
                    $isExpired = $expiresAt->isPast();
                } else if ($voucher->valid_until < $today) {
                    $isExpired = true;
                }

                // Format applicable days
                $applicableDays = $voucher->applicable_days ?? [];
                $dayNames = [
                    'monday' => 'MONDAY',
                    'tuesday' => 'TUESDAY',
                    'wednesday' => 'WEDNESDAY',
                    'thursday' => 'THURSDAY',
                    'friday' => 'FRIDAY',
                    'saturday' => 'SATURDAY',
                    'sunday' => 'SUNDAY',
                ];
                $validDays = [];
                foreach ($applicableDays as $day) {
                    if (isset($dayNames[$day])) {
                        $validDays[] = $dayNames[$day];
                    }
                }
                $validDateText = !empty($validDays) ? 'VALID ON ' . implode(' & ', $validDays) : 'VALID EVERYDAY';

                // Get brand name from voucher name or default
                // Split voucher name to get brand and subtitle if format is "BRAND - SUBTITLE"
                $voucherName = $voucher->name ?? 'JUSTUS GROUP';
                $nameParts = explode(' - ', $voucherName, 2);
                $brandName = $nameParts[0] ?? 'JUSTUS GROUP';
                $subtitle = $nameParts[1] ?? '';
                
                // Determine voucher type and format display
                $voucherType = 'discount';
                $discountText = '';
                $description = $voucher->description ?? '';
                $minTransaction = null;
                $note = null;
                $freeItemDetails = []; // Initialize for free-item vouchers

                switch ($voucher->voucher_type) {
                    case 'discount-percentage':
                        $voucherType = 'discount';
                        $discountText = ($voucher->discount_percentage ?? 0) . '% OFF';
                        if ($voucher->min_purchase) {
                            $minTransaction = 'Minimum Transaction ' . number_format($voucher->min_purchase, 0, ',', '.');
                        }
                        break;
                    case 'discount-fixed':
                        $voucherType = 'value';
                        $discountText = 'Rp. ' . number_format($voucher->discount_amount ?? 0, 0, ',', '.');
                        if ($voucher->min_purchase) {
                            $minTransaction = 'Minimum Transaction Rp ' . number_format($voucher->min_purchase, 0, ',', '.') . '++';
                            $note = 'Before Tax & Service';
                        }
                        break;
                    case 'free-item':
                        $voucherType = 'free';
                        // Get free item names from free_item_ids if available
                        $freeItemIds = $voucher->free_item_ids ? json_decode($voucher->free_item_ids, true) : null;
                        if (is_array($freeItemIds) && !empty($freeItemIds)) {
                            // Get item details from database
                            $items = DB::table('items')
                                ->whereIn('id', $freeItemIds)
                                ->select('id', 'name')
                                ->get();
                            if ($items->isNotEmpty()) {
                                $itemNames = $items->pluck('name')->toArray();
                                $discountText = 'FREE ' . implode(', ', $itemNames);
                                // Store item details for frontend
                                foreach ($items as $item) {
                                    $freeItemDetails[] = [
                                        'id' => $item->id,
                                        'name' => $item->name,
                                    ];
                                }
                            } else {
                                $discountText = 'FREE ' . ($voucher->free_item_name ?? 'ITEM');
                            }
                        } else {
                            $discountText = 'FREE ' . ($voucher->free_item_name ?? 'ITEM');
                        }
                        break;
                    case 'cashback':
                        $voucherType = 'cashback';
                        if ($voucher->cashback_amount) {
                            $discountText = 'Rp. ' . number_format($voucher->cashback_amount, 0, ',', '.') . ' CASHBACK';
                        } else if ($voucher->cashback_percentage) {
                            $discountText = ($voucher->cashback_percentage ?? 0) . '% CASHBACK';
                        }
                        break;
                }

                // Build image URL
                $imageUrl = null;
                if ($voucher->image) {
                    $imageUrl = 'https://ymsofterp.com/storage/' . ltrim($voucher->image, '/');
                }

                // Format applicable time
                $applicableTimeText = '';
                if ($voucher->applicable_time_start && $voucher->applicable_time_end) {
                    $startTime = Carbon::parse($voucher->applicable_time_start)->format('H:i');
                    $endTime = Carbon::parse($voucher->applicable_time_end)->format('H:i');
                    $applicableTimeText = "{$startTime} - {$endTime}";
                }

                // Format valid date range
                $validFromFormatted = Carbon::parse($voucher->valid_from)->format('d F Y');
                $validUntilFormatted = Carbon::parse($voucher->valid_until)->format('d F Y');
                $validDateRange = "{$validFromFormatted} - {$validUntilFormatted}";

                // Get outlet name if voucher was used
                $usedOutletName = null;
                if ($memberVoucher->status === 'used' && $memberVoucher->used_in_outlet_id) {
                    $outlet = DB::table('tbl_data_outlet')
                        ->where('id_outlet', $memberVoucher->used_in_outlet_id)
                        ->first();
                    if ($outlet) {
                        $usedOutletName = $outlet->nama_outlet ?? null;
                    }
                }

                return [
                    'id' => $memberVoucher->id,
                    'voucher_id' => $voucher->id,
                    'voucher_code' => $memberVoucher->voucher_code,
                    'serial_code' => $memberVoucher->serial_code,
                    'status' => $memberVoucher->status, // 'active' or 'used'
                    'brand' => $brandName,
                    'subtitle' => $subtitle,
                    'type' => $voucherType,
                    'discount' => $voucherType === 'discount' ? $discountText : null,
                    'value' => $voucherType === 'value' ? $discountText : null,
                    'free' => $voucherType === 'free' ? $discountText : null,
                    'cashback' => $voucherType === 'cashback' ? $discountText : null,
                    'free_item_ids' => $voucherType === 'free' && $voucher->free_item_ids ? json_decode($voucher->free_item_ids, true) : null,
                    'free_item_selection' => $voucherType === 'free' ? ($voucher->free_item_selection ?? 'all') : null,
                    'free_item_name' => $voucherType === 'free' ? ($voucher->free_item_name ?? null) : null,
                    'free_item_details' => $voucherType === 'free' && !empty($freeItemDetails) ? $freeItemDetails : null,
                    'description' => $description,
                    'minTransaction' => $minTransaction,
                    'note' => $note,
                    'validDate' => $validDateText,
                    'validDateRange' => $validDateRange,
                    'valid_from' => $voucher->valid_from,
                    'valid_until' => $voucher->valid_until,
                    'applicableTime' => $applicableTimeText,
                    'maxDiscount' => $voucher->max_discount ? number_format($voucher->max_discount, 0, ',', '.') : null,
                    'expires_at' => $memberVoucher->expires_at ? (is_string($memberVoucher->expires_at) ? $memberVoucher->expires_at : $memberVoucher->expires_at->format('Y-m-d H:i:s')) : null,
                    'is_expired' => $isExpired,
                    'image' => $imageUrl,
                    'all_outlets' => $allOutlets,
                    'outlets' => $outletList,
                    // Redemption information (only for used vouchers)
                    'used_at' => $memberVoucher->used_at ? $memberVoucher->used_at->format('Y-m-d H:i:s') : null,
                    'used_in_transaction_id' => $memberVoucher->used_in_transaction_id,
                    'used_in_outlet_id' => $memberVoucher->used_in_outlet_id,
                    'used_in_outlet_name' => $usedOutletName,
                ];
            })->filter()->values();

            return response()->json([
                'success' => true,
                'data' => $vouchers,
                'pagination' => [
                    'total' => $totalCount,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $totalCount
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Get Vouchers Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get vouchers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Redeem voucher by serial code (for POS)
     * Validates: member_id, serial_code, outlet_id
     * Returns voucher details and discount calculation
     */
    public function redeemBySerialCode(Request $request)
    {
        try {
            $validator = \Validator::make($request->all(), [
                'serial_code' => 'required|string',
                'member_id' => 'required|string',
                'outlet_id' => 'nullable|integer',
                'outlet_code' => 'nullable|string',
                'transaction_total' => 'nullable|numeric|min:0', // Total transaction untuk validasi min_purchase
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $serialCode = $request->input('serial_code');
            $memberId = $request->input('member_id');
            $outletId = $request->input('outlet_id');
            $outletCode = $request->input('outlet_code');
            $transactionTotal = $request->input('transaction_total', 0);

            \Log::info('Redeem Voucher - Request:', [
                'serial_code' => $serialCode,
                'member_id' => $memberId,
                'outlet_id' => $outletId,
                'outlet_code' => $outletCode,
                'transaction_total' => $transactionTotal
            ]);

            // Find member by member_id (string) to get numeric ID
            $member = \App\Models\MemberAppsMember::where('member_id', $memberId)->first();
            
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member tidak ditemukan'
                ], 404);
            }
            
            $memberNumericId = $member->id;

            // Find member voucher by serial code
            $memberVoucher = MemberAppsMemberVoucher::where('serial_code', $serialCode)
                ->where('status', 'active')
                ->with('voucher.outlets')
                ->first();

            if (!$memberVoucher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Voucher tidak ditemukan atau sudah tidak aktif'
                ], 404);
            }

            // Validate member_id matches (compare numeric IDs)
            if ($memberVoucher->member_id != $memberNumericId) {
                \Log::warning('Voucher member_id mismatch', [
                    'serial_code' => $serialCode,
                    'requested_member_id' => $memberId,
                    'requested_member_numeric_id' => $memberNumericId,
                    'voucher_member_id' => $memberVoucher->member_id,
                    'voucher_member' => $memberVoucher->member ? $memberVoucher->member->member_id : null
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Voucher ini tidak dimiliki oleh member ini'
                ], 403);
            }

            $voucher = $memberVoucher->voucher;
            if (!$voucher || !$voucher->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Voucher tidak aktif'
                ], 400);
            }

            // Validate voucher validity dates (from voucher template)
            $today = Carbon::today();
            if ($voucher->valid_from > $today || $voucher->valid_until < $today) {
                return response()->json([
                    'success' => false,
                    'message' => 'Voucher sudah tidak berlaku'
                ], 400);
            }
            
            // Validate member voucher expires_at (if set)
            if ($memberVoucher->expires_at) {
                $expiresAt = is_string($memberVoucher->expires_at) 
                    ? Carbon::parse($memberVoucher->expires_at)
                    : $memberVoucher->expires_at;
                if ($expiresAt->isPast()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Voucher sudah expired',
                        'data' => [
                            'expires_at' => $expiresAt->format('Y-m-d H:i:s')
                        ]
                    ], 400);
                }
            }

            // Validate outlet
            $outlets = $voucher->outlets;
            $allOutlets = $outlets->isEmpty();
            
            if (!$allOutlets) {
                // Voucher hanya bisa digunakan di outlet tertentu
                $outletValid = false;
                
                if ($outletId) {
                    $outletValid = $outlets->contains('id_outlet', $outletId);
                } elseif ($outletCode) {
                    // Find outlet by qr_code
                    $outlet = DB::table('tbl_data_outlet')
                        ->where('qr_code', $outletCode)
                        ->first();
                    
                    if ($outlet) {
                        $outletValid = $outlets->contains('id_outlet', $outlet->id_outlet);
                    }
                }
                
                if (!$outletValid) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Voucher ini tidak bisa digunakan di outlet ini'
                    ], 403);
                }
            }

            // Validate applicable days
            $applicableDays = $voucher->applicable_days ?? [];
            if (!empty($applicableDays)) {
                $dayNames = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                $currentDay = strtolower(Carbon::now()->format('l'));
                $currentDayIndex = array_search($currentDay, $dayNames);
                $currentDayName = $dayNames[$currentDayIndex] ?? null;
                
                if ($currentDayName && !in_array($currentDayName, $applicableDays)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Voucher tidak berlaku pada hari ini'
                    ], 400);
                }
            }

            // Validate applicable time
            if ($voucher->applicable_time_start && $voucher->applicable_time_end) {
                $now = Carbon::now();
                
                // Parse time strings (HH:MM:SS or HH:MM format)
                $startTimeStr = $voucher->applicable_time_start;
                $endTimeStr = $voucher->applicable_time_end;
                
                // If it's a datetime string, extract time part
                if (strpos($startTimeStr, ' ') !== false) {
                    $startTimeStr = explode(' ', $startTimeStr)[1]; // Get time part
                }
                if (strpos($endTimeStr, ' ') !== false) {
                    $endTimeStr = explode(' ', $endTimeStr)[1]; // Get time part
                }
                
                // Parse time strings to Carbon time objects (using today's date for comparison)
                $startTime = Carbon::createFromTimeString($now->format('Y-m-d') . ' ' . $startTimeStr);
                $endTime = Carbon::createFromTimeString($now->format('Y-m-d') . ' ' . $endTimeStr);
                $currentTime = Carbon::createFromTime($now->hour, $now->minute, $now->second);
                
                \Log::info('Voucher time validation', [
                    'start_time_str' => $startTimeStr,
                    'end_time_str' => $endTimeStr,
                    'current_time' => $currentTime->format('H:i:s'),
                    'start_time' => $startTime->format('H:i:s'),
                    'end_time' => $endTime->format('H:i:s'),
                    'is_valid' => ($currentTime >= $startTime && $currentTime <= $endTime)
                ]);
                
                if ($currentTime < $startTime || $currentTime > $endTime) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Voucher tidak berlaku pada jam ini. Waktu berlaku: ' . $startTimeStr . ' - ' . $endTimeStr
                    ], 400);
                }
            }

            // Validate min_purchase
            if ($voucher->min_purchase && $transactionTotal < $voucher->min_purchase) {
                return response()->json([
                    'success' => false,
                    'message' => 'Minimum transaksi tidak terpenuhi. Minimum: Rp ' . number_format($voucher->min_purchase, 0, ',', '.')
                ], 400);
            }

            // Calculate discount based on voucher type
            $discountAmount = 0;
            $discountType = null;
            $freeItemName = null;
            $cashbackAmount = 0;

            switch ($voucher->voucher_type) {
                case 'discount-percentage':
                    $discountType = 'percentage';
                    $discountAmount = ($transactionTotal * $voucher->discount_percentage) / 100;
                    
                    // Apply max_discount if exists
                    if ($voucher->max_discount && $discountAmount > $voucher->max_discount) {
                        $discountAmount = $voucher->max_discount;
                    }
                    break;
                    
                case 'discount-fixed':
                    $discountType = 'fixed';
                    $discountAmount = $voucher->discount_amount;
                    break;
                    
                case 'free-item':
                    $discountType = 'free-item';
                    $freeItemName = $voucher->free_item_name;
                    // Free item discount = price of the item (will be calculated in POS)
                    break;
                    
                case 'cashback':
                    $discountType = 'cashback';
                    if ($voucher->cashback_amount) {
                        $cashbackAmount = $voucher->cashback_amount;
                    } elseif ($voucher->cashback_percentage) {
                        $cashbackAmount = ($transactionTotal * $voucher->cashback_percentage) / 100;
                    }
                    break;
            }

            // Mark voucher as used (but don't save yet, wait for order completion)
            // We'll update this when order is saved

            return response()->json([
                'success' => true,
                'message' => 'Voucher berhasil divalidasi',
                'data' => [
                    'voucher_id' => $voucher->id,
                    'member_voucher_id' => $memberVoucher->id,
                    'voucher_name' => $voucher->name,
                    'voucher_type' => $voucher->voucher_type,
                    'discount_type' => $discountType,
                    'discount_amount' => $discountAmount,
                    'discount_percentage' => $voucher->discount_percentage ?? null,
                    'max_discount' => $voucher->max_discount ?? null,
                    'free_item_name' => $freeItemName,
                    'free_item_ids' => $voucher->free_item_ids ? json_decode($voucher->free_item_ids, true) : null,
                    'free_item_selection' => $voucher->free_item_selection ?? null,
                    'cashback_amount' => $cashbackAmount,
                    'cashback_percentage' => $voucher->cashback_percentage ?? null,
                    'min_purchase' => $voucher->min_purchase ?? null,
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Redeem Voucher Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal redeem voucher: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark voucher as used after order is completed
     */
    public function markAsUsed(Request $request)
    {
        try {
            $validator = \Validator::make($request->all(), [
                'member_voucher_id' => 'required|integer',
                'transaction_id' => 'nullable|string', // Order ID or transaction number
                'outlet_id' => 'nullable|integer',
                'outlet_code' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $memberVoucherId = $request->input('member_voucher_id');
            $transactionId = $request->input('transaction_id');
            $outletId = $request->input('outlet_id');
            $outletCode = $request->input('outlet_code');

            $memberVoucher = MemberAppsMemberVoucher::find($memberVoucherId);

            if (!$memberVoucher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Voucher tidak ditemukan'
                ], 404);
            }

            if ($memberVoucher->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Voucher sudah tidak aktif'
                ], 400);
            }

            // Resolve outlet_id if outlet_code is provided
            if (!$outletId && $outletCode) {
                $outlet = DB::table('tbl_data_outlet')
                    ->where('qr_code', $outletCode)
                    ->first();
                
                if ($outlet) {
                    $outletId = $outlet->id_outlet;
                }
            }

            // Mark as used
            $memberVoucher->status = 'used';
            $memberVoucher->used_at = Carbon::now();
            $memberVoucher->used_in_transaction_id = $transactionId;
            if ($outletId) {
                $memberVoucher->used_in_outlet_id = $outletId;
            }
            
            \Log::info('Before saving voucher as used', [
                'member_voucher_id' => $memberVoucherId,
                'status' => $memberVoucher->status,
                'used_at' => $memberVoucher->used_at,
                'used_in_transaction_id' => $memberVoucher->used_in_transaction_id,
                'used_in_outlet_id' => $memberVoucher->used_in_outlet_id,
                'transaction_id' => $transactionId,
                'outlet_id' => $outletId,
                'outlet_code' => $outletCode
            ]);
            
            $saved = $memberVoucher->save();
            
            // Reload to verify data was saved
            $memberVoucher->refresh();
            
            \Log::info('Voucher marked as used', [
                'member_voucher_id' => $memberVoucherId,
                'saved' => $saved,
                'status' => $memberVoucher->status,
                'used_at' => $memberVoucher->used_at,
                'used_in_transaction_id' => $memberVoucher->used_in_transaction_id,
                'used_in_outlet_id' => $memberVoucher->used_in_outlet_id,
                'transaction_id' => $transactionId,
                'outlet_id' => $outletId,
                'outlet_code' => $outletCode
            ]);

            // Send notification to member about voucher redemption
            try {
                // Reload with relationships
                $memberVoucher->load(['member', 'voucher']);
                $member = $memberVoucher->member;
                $voucher = $memberVoucher->voucher;
                
                if ($member && $voucher) {
                    // Refresh member to get latest data
                    $member->refresh();
                    
                    // Skip if member has disabled notifications
                    if ($member->allow_notification) {
                        // Get outlet name if available
                        $outletName = 'Outlet';
                        if ($outletId) {
                            $outlet = DB::table('tbl_data_outlet')
                                ->where('id_outlet', $outletId)
                                ->first();
                            if ($outlet) {
                                $outletName = $outlet->nama_outlet ?? 'Outlet';
                            }
                        } elseif ($outletCode) {
                            $outlet = DB::table('tbl_data_outlet')
                                ->where('qr_code', $outletCode)
                                ->first();
                            if ($outlet) {
                                $outletName = $outlet->nama_outlet ?? 'Outlet';
                            }
                        }
                        
                        // Build notification message
                        $title = 'Voucher Redeemed! ✅';
                        $voucherName = $voucher->name ?? 'Voucher';
                        if ($transactionId) {
                            $message = "Your voucher '{$voucherName}' has been successfully redeemed at {$outletName} (Order: {$transactionId}). Thank you for your purchase!";
                        } else {
                            $message = "Your voucher '{$voucherName}' has been successfully redeemed at {$outletName}. Thank you for your purchase!";
                        }
                        
                        $data = [
                            'type' => 'voucher_redeemed',
                            'member_id' => $member->id,
                            'voucher_id' => $voucher->id ?? null,
                            'voucher_name' => $voucherName,
                            'member_voucher_id' => $memberVoucher->id,
                            'voucher_code' => $memberVoucher->voucher_code ?? null,
                            'serial_code' => $memberVoucher->serial_code ?? null,
                            'transaction_id' => $transactionId,
                            'outlet_id' => $outletId,
                            'outlet_name' => $outletName,
                            'used_at' => $memberVoucher->used_at ? $memberVoucher->used_at->format('Y-m-d H:i:s') : null,
                            'action' => 'view_vouchers',
                        ];
                        
                        \Log::info('Sending voucher redeemed notification', [
                            'member_id' => $member->id,
                            'voucher_id' => $voucher->id ?? null,
                            'voucher_name' => $voucherName,
                            'title' => $title,
                            'message' => $message,
                        ]);
                        
                        // Send push notification
                        $fcmService = app(FCMService::class);
                        $result = $fcmService->sendToMember(
                            $member,
                            $title,
                            $message,
                            $data
                        );
                        
                        \Log::info('Voucher redeemed notification result', [
                            'member_id' => $member->id,
                            'voucher_id' => $voucher->id ?? null,
                            'success_count' => $result['success_count'] ?? 0,
                            'failed_count' => $result['failed_count'] ?? 0,
                        ]);
                        
                        // Save notification to database
                        try {
                            MemberAppsNotification::create([
                                'member_id' => $member->id,
                                'type' => 'voucher_redeemed',
                                'title' => $title,
                                'message' => $message,
                                'url' => '/vouchers',
                                'data' => $data,
                                'is_read' => false,
                            ]);
                            
                            \Log::info('Voucher redeemed notification saved to database', [
                                'member_id' => $member->id,
                            ]);
                        } catch (\Exception $dbError) {
                            \Log::error('Error saving voucher redeemed notification to database', [
                                'member_id' => $member->id,
                                'error' => $dbError->getMessage(),
                            ]);
                            // Continue even if database save fails
                        }
                    } else {
                        \Log::info('Skipping voucher redeemed notification - member has disabled notifications', [
                            'member_id' => $member->id,
                        ]);
                    }
                }
            } catch (\Exception $notifError) {
                \Log::error('Error sending voucher redeemed notification', [
                    'error' => $notifError->getMessage(),
                    'trace' => $notifError->getTraceAsString(),
                    'member_voucher_id' => $memberVoucherId,
                ]);
                // Continue even if notification fails
            }

            return response()->json([
                'success' => true,
                'message' => 'Voucher berhasil ditandai sebagai used'
            ]);

        } catch (\Exception $e) {
            \Log::error('Mark Voucher as Used Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menandai voucher sebagai used: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rollback voucher used status (for void transaction)
     */
    public function rollbackUsed(Request $request)
    {
        try {
            $validator = \Validator::make($request->all(), [
                'member_voucher_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $memberVoucherId = $request->input('member_voucher_id');

            \Log::info('Rollback Voucher Used - Request:', [
                'member_voucher_id' => $memberVoucherId
            ]);

            // Find member voucher
            $memberVoucher = MemberAppsMemberVoucher::find($memberVoucherId);

            if (!$memberVoucher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member voucher tidak ditemukan'
                ], 404);
            }

            // Check if voucher is actually used
            if ($memberVoucher->status !== 'used') {
                \Log::warning('Rollback Voucher Used - Voucher is not used', [
                    'member_voucher_id' => $memberVoucherId,
                    'current_status' => $memberVoucher->status
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Voucher tidak dalam status used, tidak perlu di-rollback'
                ], 400);
            }

            // Save original data before rollback (for notification)
            $originalTransactionId = $memberVoucher->used_in_transaction_id;
            $originalOutletId = $memberVoucher->used_in_outlet_id;

            // Rollback: set status back to 'active' and clear used fields
            $memberVoucher->status = 'active';
            $memberVoucher->used_at = null;
            $memberVoucher->used_in_transaction_id = null;
            $memberVoucher->used_in_outlet_id = null;
            // Note: used_in_outlet_name column doesn't exist in member_apps_member_vouchers table
            
            $saved = $memberVoucher->save();

            \Log::info('Voucher rolled back', [
                'member_voucher_id' => $memberVoucherId,
                'saved' => $saved,
                'status' => $memberVoucher->status,
                'used_at' => $memberVoucher->used_at,
                'used_in_transaction_id' => $memberVoucher->used_in_transaction_id,
                'used_in_outlet_id' => $memberVoucher->used_in_outlet_id
            ]);

            // Send notification to member about voucher rollback
            try {
                // Reload with relationships
                $memberVoucher->load(['member', 'voucher']);
                $member = $memberVoucher->member;
                $voucher = $memberVoucher->voucher;
                
                if ($member && $voucher) {
                    // Refresh member to get latest data
                    $member->refresh();
                    
                    // Skip if member has disabled notifications
                    if ($member->allow_notification) {
                        // Get outlet name from original data (before rollback)
                        $outletName = 'Outlet';
                        $outletId = $originalOutletId;
                        if ($outletId) {
                            $outlet = DB::table('tbl_data_outlet')
                                ->where('id_outlet', $outletId)
                                ->first();
                            if ($outlet) {
                                $outletName = $outlet->nama_outlet ?? 'Outlet';
                            }
                        }
                        
                        // Get transaction ID from original data (before rollback)
                        $transactionId = $originalTransactionId;
                        
                        // Build notification message
                        $title = 'Voucher Restored! 🔄';
                        $voucherName = $voucher->name ?? 'Voucher';
                        if ($transactionId) {
                            $message = "Your voucher '{$voucherName}' has been restored and is now available again. The transaction (Order: {$transactionId}) has been voided.";
                        } else {
                            $message = "Your voucher '{$voucherName}' has been restored and is now available again.";
                        }
                        
                        $data = [
                            'type' => 'voucher_restored',
                            'member_id' => $member->id,
                            'voucher_id' => $voucher->id ?? null,
                            'voucher_name' => $voucherName,
                            'member_voucher_id' => $memberVoucher->id,
                            'voucher_code' => $memberVoucher->voucher_code ?? null,
                            'serial_code' => $memberVoucher->serial_code ?? null,
                            'transaction_id' => $transactionId,
                            'outlet_id' => $outletId,
                            'outlet_name' => $outletName,
                            'action' => 'view_vouchers',
                        ];
                        
                        \Log::info('Sending voucher restored notification', [
                            'member_id' => $member->id,
                            'voucher_id' => $voucher->id ?? null,
                            'voucher_name' => $voucherName,
                            'title' => $title,
                            'message' => $message,
                        ]);
                        
                        // Send push notification
                        $fcmService = app(FCMService::class);
                        $result = $fcmService->sendToMember(
                            $member,
                            $title,
                            $message,
                            $data
                        );
                        
                        \Log::info('Voucher restored notification result', [
                            'member_id' => $member->id,
                            'voucher_id' => $voucher->id ?? null,
                            'success_count' => $result['success_count'] ?? 0,
                            'failed_count' => $result['failed_count'] ?? 0,
                        ]);
                        
                        // Save notification to database
                        try {
                            MemberAppsNotification::create([
                                'member_id' => $member->id,
                                'type' => 'voucher_restored',
                                'title' => $title,
                                'message' => $message,
                                'url' => '/vouchers',
                                'data' => $data,
                                'is_read' => false,
                            ]);
                            
                            \Log::info('Voucher restored notification saved to database', [
                                'member_id' => $member->id,
                            ]);
                        } catch (\Exception $dbError) {
                            \Log::error('Error saving voucher restored notification to database', [
                                'member_id' => $member->id,
                                'error' => $dbError->getMessage(),
                            ]);
                            // Continue even if database save fails
                        }
                    } else {
                        \Log::info('Skipping voucher restored notification - member has disabled notifications', [
                            'member_id' => $member->id,
                        ]);
                    }
                }
            } catch (\Exception $notifError) {
                \Log::error('Error sending voucher restored notification', [
                    'error' => $notifError->getMessage(),
                    'trace' => $notifError->getTraceAsString(),
                    'member_voucher_id' => $memberVoucherId,
                ]);
                // Continue even if notification fails
            }

            return response()->json([
                'success' => true,
                'message' => 'Voucher berhasil di-rollback',
                'data' => [
                    'member_voucher_id' => $memberVoucher->id,
                    'status' => $memberVoucher->status
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Rollback Voucher Used Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal rollback voucher: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get voucher store (vouchers that are for sale)
     */
    public function store(Request $request)
    {
        try {
            $member = $request->user();
            
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Get pagination parameters
            $limit = $request->input('limit', 10);
            $offset = $request->input('offset', 0);

            // Get vouchers that are for sale, active, and not expired
            $today = Carbon::today();
            $vouchers = MemberAppsVoucher::where('is_for_sale', true)
                ->where('is_active', true)
                ->where('valid_from', '<=', $today)
                ->where('valid_until', '>=', $today)
                ->whereNotNull('points_required')
                ->with(['outlets'])
                ->orderBy('created_at', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get();

            // Get total count
            $totalCount = MemberAppsVoucher::where('is_for_sale', true)
                ->where('is_active', true)
                ->where('valid_from', '<=', $today)
                ->where('valid_until', '>=', $today)
                ->whereNotNull('points_required')
                ->count();

            // Get member's current points
            $memberPoints = $member->just_points ?? 0;

            // Format vouchers
            $formattedVouchers = $vouchers->map(function ($voucher) use ($member, $memberPoints) {
                // Check if member already has this voucher
                $hasActiveVoucher = MemberAppsMemberVoucher::where('member_id', $member->id)
                    ->where('voucher_id', $voucher->id)
                    ->where('status', 'active')
                    ->exists();
                
                // Check if member ever bought this voucher (for one-time purchase)
                $hasEverBought = MemberAppsMemberVoucher::where('member_id', $member->id)
                    ->where('voucher_id', $voucher->id)
                    ->exists();
                
                $hasVoucher = $hasActiveVoucher;
                $canPurchase = false;
                
                if ($voucher->one_time_purchase) {
                    // One-time purchase: can only buy if never bought before
                    $canPurchase = !$hasEverBought && $memberPoints >= $voucher->points_required;
                } else {
                    // Can buy multiple times: can always buy if has enough points (can have multiple active vouchers)
                    $canPurchase = $memberPoints >= $voucher->points_required;
                }

                // Get outlets
                $outlets = $voucher->outlets ?? collect([]);
                $allOutlets = $outlets->isEmpty();
                $outletList = $outlets->map(function ($outlet) {
                    return [
                        'id' => $outlet->id_outlet,
                        'name' => $outlet->nama_outlet ?? 'Unknown Outlet',
                    ];
                })->toArray();

                // Parse free_item_ids if exists
                $freeItemIds = null;
                if ($voucher->free_item_ids) {
                    $freeItemIds = json_decode($voucher->free_item_ids, true);
                }

                return [
                    'id' => $voucher->id,
                    'name' => $voucher->name,
                    'description' => $voucher->description,
                    'voucher_type' => $voucher->voucher_type,
                    'discount_percentage' => $voucher->discount_percentage,
                    'discount_amount' => $voucher->discount_amount,
                    'max_discount' => $voucher->max_discount,
                    'min_purchase' => $voucher->min_purchase,
                    'free_item_name' => $voucher->free_item_name,
                    'free_item_ids' => $freeItemIds,
                    'free_item_selection' => $voucher->free_item_selection,
                    'points_required' => $voucher->points_required,
                    'valid_from' => $voucher->valid_from->format('Y-m-d'),
                    'valid_until' => $voucher->valid_until->format('Y-m-d'),
                    'image' => $voucher->image ? 'https://ymsofterp.com/storage/' . $voucher->image : null,
                    'all_outlets' => $allOutlets,
                    'outlets' => $outletList,
                    'can_purchase' => $canPurchase,
                    'has_voucher' => $hasVoucher,
                    'has_ever_bought' => $hasEverBought,
                    'one_time_purchase' => $voucher->one_time_purchase ?? false,
                    'member_points' => $memberPoints,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedVouchers,
                'pagination' => [
                    'total' => $totalCount,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $totalCount
                ],
                'member_points' => $memberPoints
            ]);
        } catch (\Exception $e) {
            \Log::error('Get Voucher Store Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get voucher store: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Purchase voucher with points
     */
    public function purchase(Request $request)
    {
        try {
            $member = $request->user();
            
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $validator = \Validator::make($request->all(), [
                'voucher_id' => 'required|integer|exists:member_apps_vouchers,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Get voucher
            $voucher = MemberAppsVoucher::findOrFail($request->voucher_id);

            // Validate voucher is for sale
            if (!$voucher->is_for_sale || !$voucher->is_active) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Voucher tidak tersedia untuk dibeli'
                ], 400);
            }

            // Validate voucher not expired
            $today = Carbon::today();
            if ($voucher->valid_from > $today || $voucher->valid_until < $today) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Voucher sudah tidak berlaku'
                ], 400);
            }

            // Check if voucher is one-time purchase and member already bought it
            if ($voucher->one_time_purchase) {
                $existingVoucher = MemberAppsMemberVoucher::where('member_id', $member->id)
                    ->where('voucher_id', $voucher->id)
                    ->first(); // Check any status, not just 'active'
                
                if ($existingVoucher) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Voucher ini hanya bisa dibeli 1 kali. Anda sudah pernah membeli voucher ini.'
                    ], 400);
                }
            } else {
                // For non one-time purchase, member can buy multiple times (can have multiple active vouchers)
                // No need to check for existing active voucher
            }

            // Check member points (considering point_remainder)
            // We'll check in deductPoints function, but do a quick check here first
            $memberPoints = $member->just_points ?? 0;
            $memberRemainder = $member->point_remainder ?? 0;
            $availablePoints = $memberPoints + floor($memberRemainder);
            
            if ($availablePoints < $voucher->points_required) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Point tidak cukup. Diperlukan: ' . number_format($voucher->points_required) . ' points'
                ], 400);
            }

            // Generate unique voucher code (same format as distribution)
            $voucherCode = $this->generateVoucherCode($voucher->id, $member->id);
            
            // Generate unique serial code (same format as distribution)
            $serialCode = $this->generateVoucherSerialCode($voucher->id, $member->id);

            // Calculate expiration date for purchased voucher
            // Vouchers purchased from store expire 1 year from purchase date
            $purchaseDate = Carbon::now();
            $expiresAt = $purchaseDate->copy()->addYear();

            // Create member voucher
            $memberVoucher = MemberAppsMemberVoucher::create([
                'voucher_id' => $voucher->id,
                'member_id' => $member->id,
                'voucher_code' => $voucherCode,
                'serial_code' => $serialCode, // Add serial_code for POS redemption
                'status' => 'active',
                'expires_at' => $expiresAt,
            ]);

            // Refresh member and voucher relationships
            $memberVoucher->load(['member', 'voucher']);
            
            // Dispatch event for push notification
            // Note: Member already knows they got the voucher since they redeemed it themselves,
            // but we send notification for consistency and in case they want to be reminded
            try {
                event(new VoucherReceived(
                    $memberVoucher->member,
                    $memberVoucher
                ));
            } catch (\Exception $e) {
                \Log::error('Error dispatching VoucherReceived event for redeemed voucher', [
                    'member_id' => $member->id,
                    'voucher_id' => $voucher->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Deduct points from member balance (considering point_remainder)
            $pointEarningService = new \App\Services\PointEarningService();
            $deductResult = $pointEarningService->deductPoints($member, $voucher->points_required);
            
            if (!$deductResult['success']) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => $deductResult['message'],
                ], 400);
            }

            // Create point transaction record
            $pointTransaction = MemberAppsPointTransaction::create([
                'member_id' => $member->id,
                'transaction_type' => 'redeem',
                'transaction_date' => $today,
                'point_amount' => -$voucher->points_required,
                'transaction_amount' => 0,
                'channel' => 'voucher-purchase',
                'reference_id' => 'VOUCHER-PURCHASE-' . $memberVoucher->id,
                'description' => 'Purchase voucher: ' . $voucher->name,
            ]);

            // Use PointEarningService to deduct points from earnings (FIFO)
            // This updates remaining_points and is_fully_redeemed in member_apps_point_earnings
            $redemptionResult = $pointEarningService->redeemPointsFromEarnings(
                $member->id,
                $pointTransaction->id,
                $voucher->points_required,
                'voucher-purchase', // redemption_type for voucher purchase
                [
                    'product_id' => null,
                    'product_name' => 'Voucher: ' . $voucher->name,
                    'product_price' => 0,
                ]
            );

            if (!$redemptionResult) {
                \Log::warning('Failed to redeem points from earnings for voucher purchase', [
                    'member_id' => $member->id,
                    'voucher_id' => $voucher->id,
                    'points_required' => $voucher->points_required,
                ]);
                // Continue anyway since point already deducted from member balance
            }

            DB::commit();

            \Log::info('Voucher purchased', [
                'member_id' => $member->id,
                'voucher_id' => $voucher->id,
                'points_deducted' => $voucher->points_required,
                'voucher_code' => $voucherCode,
                'point_transaction_id' => $pointTransaction->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Voucher berhasil dibeli',
                'data' => [
                    'voucher_code' => $voucherCode,
                    'serial_code' => $serialCode,
                    'member_points' => $member->just_points,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Purchase Voucher Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to purchase voucher: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate unique voucher code (same format as distribution)
     */
    private function generateVoucherCode($voucherId, $memberId)
    {
        // Generate unique voucher code: VOUCHER_ID-MEMBER_ID-RANDOM
        $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        return "V{$voucherId}-M{$memberId}-{$random}";
    }

    /**
     * Generate unique 8-character alphanumeric serial code (same format as distribution)
     */
    private function generateVoucherSerialCode($voucherId, $memberId)
    {
        // Generate unique 8-character alphanumeric serial code
        // Characters: A-Z, 0-9 (36 possible characters)
        // Total possible combinations: 36^8 = 2,821,109,907,456 (very large, collision unlikely)
        
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $maxAttempts = 20; // Maximum attempts to generate unique code
        
        $attempts = 0;
        do {
            $serialCode = '';
            for ($i = 0; $i < 8; $i++) {
                $serialCode .= $characters[random_int(0, strlen($characters) - 1)];
            }
            
            // Check if serial code already exists
            $exists = MemberAppsMemberVoucher::where('serial_code', $serialCode)->exists();
            $attempts++;
            
            // If doesn't exist, we found a unique code
            if (!$exists) {
                return $serialCode;
            }
            
        } while ($attempts < $maxAttempts);
        
        // If we exhausted all attempts, throw exception
        throw new \Exception("Failed to generate unique serial code for voucher {$voucherId} and member {$memberId} after {$maxAttempts} attempts");
    }
}

