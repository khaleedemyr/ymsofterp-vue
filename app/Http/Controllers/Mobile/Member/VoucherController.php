<?php

namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberAppsMemberVoucher;
use App\Models\MemberAppsVoucher;
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

            // Get active vouchers for this member
            // Relaxed filter: hanya cek status active, tidak cek expires_at karena bisa expired tapi masih perlu ditampilkan
            $memberVouchers = MemberAppsMemberVoucher::where('member_id', $member->id)
                ->where('status', 'active')
                ->with('voucher')
                ->orderBy('created_at', 'desc')
                ->get();

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

                // Check if voucher is still valid (relaxed - hanya cek valid_until, tidak cek valid_from)
                $today = Carbon::today();
                // Hanya filter jika voucher sudah expired (valid_until < today)
                // Biarkan voucher yang valid_from > today tetap muncul (akan expired nanti)
                if ($voucher->valid_until < $today) {
                    return null;
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
                        $discountText = 'FREE ' . ($voucher->free_item_name ?? 'ITEM');
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

                return [
                    'id' => $memberVoucher->id,
                    'voucher_id' => $voucher->id,
                    'voucher_code' => $memberVoucher->voucher_code,
                    'brand' => $brandName,
                    'subtitle' => $subtitle,
                    'type' => $voucherType,
                    'discount' => $voucherType === 'discount' ? $discountText : null,
                    'value' => $voucherType === 'value' ? $discountText : null,
                    'description' => $description,
                    'minTransaction' => $minTransaction,
                    'note' => $note,
                    'validDate' => $validDateText,
                    'validDateRange' => $validDateRange,
                    'valid_from' => $voucher->valid_from,
                    'valid_until' => $voucher->valid_until,
                    'applicableTime' => $applicableTimeText,
                    'maxDiscount' => $voucher->max_discount ? number_format($voucher->max_discount, 0, ',', '.') : null,
                    'expires_at' => $memberVoucher->expires_at,
                    'image' => $imageUrl,
                ];
            })->filter()->values();

            return response()->json([
                'success' => true,
                'data' => $vouchers
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
}

