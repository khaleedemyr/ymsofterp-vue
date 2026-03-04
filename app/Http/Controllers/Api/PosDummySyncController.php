<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PosDummySyncController extends Controller
{
    private function convertDateTime($dateTime): string
    {
        if (!$dateTime) {
            return now()->format('Y-m-d H:i:s');
        }

        if (is_string($dateTime) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dateTime)) {
            return $dateTime;
        }

        try {
            return Carbon::parse($dateTime)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return now()->format('Y-m-d H:i:s');
        }
    }

    /**
     * Sync order dari POS dummy ke tabel *_dummy di pusat.
     */
    public function syncOrder(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'order' => 'required|array',
                'order.id' => 'required|string',
                'order.nomor' => 'required|string',
                'kode_outlet' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $orderData = $request->input('order');
            $kodeOutlet = $request->input('kode_outlet');

            DB::beginTransaction();

            $orderInsert = [
                'id' => $orderData['id'],
                'nomor' => $orderData['nomor'],
                'table' => $orderData['table'] ?? '-',
                'paid_number' => $orderData['paid_number'] ?? null,
                'waiters' => $orderData['waiters'] ?? '-',
                'member_id' => $orderData['member_id'] ?? '',
                'member_name' => $orderData['member_name'] ?? '',
                'mode' => $orderData['mode'] ?? null,
                'pax' => $orderData['pax'] ?? 0,
                'total' => $orderData['total'] ?? 0,
                'discount' => $orderData['discount'] ?? 0,
                'cashback' => $orderData['cashback'] ?? 0,
                'dpp' => $orderData['dpp'] ?? 0,
                'pb1' => $orderData['pb1'] ?? 0,
                'service' => $orderData['service'] ?? 0,
                'grand_total' => $orderData['grand_total'] ?? 0,
                'status' => $orderData['status'] ?? 'paid',
                'created_at' => $this->convertDateTime($orderData['created_at'] ?? null),
                'updated_at' => $this->convertDateTime($orderData['updated_at'] ?? null),
                'joined_tables' => $orderData['joined_tables'] ?? null,
                'promo_ids' => $orderData['promo_ids'] ?? null,
                'commfee' => $orderData['commfee'] ?? 0,
                'rounding' => $orderData['rounding'] ?? 0,
                'sales_lead' => $orderData['sales_lead'] ?? null,
                'redeem_amount' => $orderData['redeem_amount'] ?? 0,
                'manual_discount_amount' => $orderData['manual_discount_amount'] ?? 0,
                'manual_discount_reason' => $orderData['manual_discount_reason'] ?? null,
                'voucher_info' => $orderData['voucher_info'] ?? null,
                'inactive_promo_items' => $orderData['inactive_promo_items'] ?? null,
                'promo_discount_info' => $orderData['promo_discount_info'] ?? null,
                'issync' => 1,
                'reservation_id' => $orderData['reservation_id'] ?? null,
                'kode_outlet' => $kodeOutlet,
            ];

            DB::table('orders_dummy')->upsert(
                [$orderInsert],
                ['id'],
                [
                    'nomor', 'table', 'paid_number', 'waiters', 'member_id', 'member_name',
                    'mode', 'pax', 'total', 'discount', 'cashback', 'dpp', 'pb1', 'service',
                    'grand_total', 'status', 'updated_at', 'joined_tables', 'promo_ids',
                    'commfee', 'rounding', 'sales_lead', 'redeem_amount',
                    'manual_discount_amount', 'manual_discount_reason',
                    'voucher_info', 'inactive_promo_items', 'promo_discount_info',
                    'issync', 'reservation_id', 'kode_outlet'
                ]
            );

            DB::table('order_items_dummy')->where('order_id', $orderData['id'])->delete();
            foreach (($orderData['items'] ?? []) as $item) {
                DB::table('order_items_dummy')->insert([
                    'id' => $item['id'] ?? null,
                    'order_id' => $orderData['id'],
                    'item_id' => $item['item_id'] ?? null,
                    'item_name' => $item['item_name'] ?? '',
                    'qty' => $item['qty'] ?? 0,
                    'price' => $item['price'] ?? 0,
                    'tally' => $item['tally'] ?? null,
                    'modifiers' => $item['modifiers'] ?? null,
                    'notes' => $item['notes'] ?? null,
                    'subtotal' => $item['subtotal'] ?? 0,
                    'created_at' => $this->convertDateTime($item['created_at'] ?? null),
                    'kode_outlet' => $kodeOutlet,
                ]);
            }

            DB::table('order_promos_dummy')->where('order_id', $orderData['id'])->delete();
            foreach (($orderData['promos'] ?? []) as $promo) {
                DB::table('order_promos_dummy')->insert([
                    'order_id' => $orderData['id'],
                    'promo_id' => $promo['promo_id'] ?? null,
                    'status' => $promo['status'] ?? 'active',
                    'created_at' => $this->convertDateTime($promo['created_at'] ?? null),
                    'kode_outlet' => $kodeOutlet,
                ]);
            }

            DB::table('order_payment_dummy')->where('order_id', $orderData['id'])->delete();
            foreach (($orderData['payments'] ?? []) as $payment) {
                DB::table('order_payment_dummy')->insert([
                    'id' => $payment['id'] ?? null,
                    'order_id' => $orderData['id'],
                    'paid_number' => $payment['paid_number'] ?? null,
                    'payment_type' => $payment['payment_type'] ?? null,
                    'payment_code' => $payment['payment_code'] ?? null,
                    'bank_id' => $payment['bank_id'] ?? null,
                    'amount' => $payment['amount'] ?? 0,
                    'card_first4' => $payment['card_first4'] ?? null,
                    'card_last4' => $payment['card_last4'] ?? null,
                    'approval_code' => $payment['approval_code'] ?? null,
                    'created_at' => $this->convertDateTime($payment['created_at'] ?? null),
                    'kasir' => $payment['kasir'] ?? '-',
                    'note' => $payment['note'] ?? '',
                    'change' => $payment['change'] ?? 0,
                    'kode_outlet' => $kodeOutlet,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order synced to dummy tables successfully',
                'data' => [
                    'order_id' => $orderData['id'],
                    'kode_outlet' => $kodeOutlet,
                    'items_count' => count($orderData['items'] ?? []),
                    'promos_count' => count($orderData['promos'] ?? []),
                    'payments_count' => count($orderData['payments'] ?? []),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('POS Dummy Order Sync Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync dummy order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cek order sudah ada di tabel orders_dummy.
     */
    public function checkOrderExists(Request $request)
    {
        $orderId = $request->query('order_id');
        $kodeOutlet = $request->query('kode_outlet');

        if (empty($orderId) || empty($kodeOutlet)) {
            return response()->json([
                'success' => false,
                'message' => 'order_id dan kode_outlet wajib diisi',
            ], 400);
        }

        $exists = DB::table('orders_dummy')
            ->where('id', $orderId)
            ->where('kode_outlet', $kodeOutlet)
            ->exists();

        return response()->json([
            'success' => true,
            'exists' => $exists,
        ]);
    }
}
