<?php

namespace App\Http\Controllers;

use App\Models\FoodFloorOrder;
use App\Models\FoodFloorOrderItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\Item;
use Illuminate\Support\Facades\Mail;
use App\Services\FloorOrderService;
use Carbon\Carbon;

class FoodFloorOrderController extends Controller
{
    protected $floorOrderService;

    public function __construct(FloorOrderService $floorOrderService)
    {
        $this->floorOrderService = $floorOrderService;
    }

    // Tampilkan form edit draft
    public function edit($id)
    {
        $order = FoodFloorOrder::with('items')->findOrFail($id);
        return Inertia::render('FloorOrder/Form', [
            'order' => $order,
            'user' => Auth::user()->load('outlet'),
        ]);
    }

    // Tambahkan method baru untuk validasi dan pengelompokan item berdasarkan supplier
    private function validateAndGroupItemsBySupplier($items, $outletId)
    {
        $supplierItems = [];
        foreach ($items as $item) {
            $itemSupplier = \DB::table('item_supplier_outlet')
                ->join('item_supplier', 'item_supplier_outlet.item_supplier_id', '=', 'item_supplier.id')
                ->where('item_supplier_outlet.outlet_id', $outletId)
                ->where('item_supplier.item_id', $item['item_id'])
                ->select('item_supplier.supplier_id', 'item_supplier.id as item_supplier_id')
                ->first();
            if ($itemSupplier) {
                \Log::info('Item ditemukan di supplier', ['item' => $item, 'supplier' => $itemSupplier]);
                $supplierItems[] = [
                    'item_id' => $item['item_id'],
                    'item_name' => $item['item_name'],
                    'qty' => $item['qty'],
                    'unit' => $item['unit'],
                    'price' => $item['price'],
                    'subtotal' => $item['subtotal'],
                    'supplier_id' => $itemSupplier->supplier_id,
                    'item_supplier_id' => $itemSupplier->item_supplier_id,
                    'id_outlet' => $outletId
                ];
            } else {
                \Log::info('Item tidak ditemukan di supplier', ['item' => $item]);
            }
        }
        return $supplierItems;
    }

    // Modifikasi method sendEmailToSupplier untuk mengirim email biasa tanpa PDF
    private function sendEmailToSupplier($supplierItems, $outletName, $floorOrderId)
    {
        try {
            // Kelompokkan item berdasarkan supplier_id
            $groupedItems = collect($supplierItems)->groupBy('supplier_id');
            \Log::info('Mulai proses kirim email supplier', [
                'floor_order_id' => $floorOrderId,
                'total_suppliers' => count($groupedItems),
                'supplier_ids' => array_keys($groupedItems->toArray())
            ]);

            // Ambil informasi order dan pembuat sekali saja
            $order = \DB::table('food_floor_orders')
                ->join('users', 'food_floor_orders.user_id', '=', 'users.id')
                ->where('food_floor_orders.id', $floorOrderId)
                ->select('food_floor_orders.*', 'users.nama_lengkap')
                ->first();

            if (!$order) {
                \Log::error('Order tidak ditemukan', ['floor_order_id' => $floorOrderId]);
                return;
            }

            \Log::info('Data order ditemukan', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'creator' => $order->nama_lengkap
            ]);

            $creatorName = $order->nama_lengkap ?? '-';
            $createdAt = Carbon::parse($order->created_at)->format('d/m/Y H:i');
            $emailData = [];

            foreach ($groupedItems as $supplierId => $items) {
                try {
                    $supplier = \DB::table('suppliers')->where('id', $supplierId)->first();
                    \Log::info('Proses supplier', [
                        'supplier_id' => $supplierId,
                        'supplier_email' => $supplier ? $supplier->email : 'tidak ada email',
                        'total_items' => count($items)
                    ]);

                    if ($supplier && $supplier->email) {
                        // Cek header supplier, jika belum ada baru insert
                        $header = \DB::table('food_floor_order_supplier_headers')
                            ->where('floor_order_id', $floorOrderId)
                            ->where('supplier_id', $supplierId)
                            ->first();
                        if (!$header) {
                            $supplierFoNumber = $this->floorOrderService->generateSupplierFONumber($supplierId);
                            \Log::info('Generated FO Number', ['supplier_fo_number' => $supplierFoNumber]);
                            \DB::beginTransaction();
                            try {
                                $headerData = [
                                    'floor_order_id' => $floorOrderId,
                                    'supplier_id' => $supplierId,
                                    'supplier_fo_number' => $supplierFoNumber,
                                    'created_at' => now(),
                                    'updated_at' => now()
                                ];
                                \Log::info('Mencoba insert ke food_floor_order_supplier_headers', $headerData);
                                $inserted = \DB::table('food_floor_order_supplier_headers')->insert($headerData);
                                \Log::info('Hasil insert header', ['success' => $inserted]);
                                if ($inserted) {
                                    $emailData[] = [
                                        'supplier' => $supplier,
                                        'supplierFoNumber' => $supplierFoNumber,
                                        'items' => $items
                                    ];
                                    \DB::commit();
                                } else {
                                    \Log::error('Gagal insert ke food_floor_order_supplier_headers', $headerData);
                                    \DB::rollBack();
                                }
                            } catch (\Exception $e) {
                                \DB::rollBack();
                                throw $e;
                            }
                        } else {
                            // Jika sudah ada, gunakan nomor yang sudah ada
                            $emailData[] = [
                                'supplier' => $supplier,
                                'supplierFoNumber' => $header->supplier_fo_number,
                                'items' => $items
                            ];
                        }
                    } else {
                        \Log::warning('Supplier tidak memiliki email', [
                            'supplier_id' => $supplierId
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::error('Error dalam loop supplier', [
                        'supplier_id' => $supplierId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            // Kirim email setelah semua proses insert selesai
            foreach ($emailData as $data) {
                try {
                    \Log::info('Mencoba kirim email ke supplier', [
                        'email' => $data['supplier']->email,
                        'supplier_fo_number' => $data['supplierFoNumber'],
                        'total_items' => count($data['items'])
                    ]);
                    $itemsList = '';
                    foreach ($data['items'] as $item) {
                        $itemsList .= "- {$item['item_name']} ({$item['qty']} {$item['unit']})\n";
                    }
                    $emailContent = "\nRequest Order Supplier\n\n- Nomor RO: {$order->order_number}\n- Nomor RO Supplier: {$data['supplierFoNumber']}\nOutlet: {$outletName}\nDibuat oleh: {$creatorName}\nWaktu pembuatan: {$createdAt}\n\nDetail Items:\n{$itemsList}\n\nTerima kasih,\nYMSoft ERP\n";
                    Mail::raw($emailContent, function($message) use ($data) {
                        $message->to($data['supplier']->email)
                               ->subject("Request Order Supplier - {$data['supplierFoNumber']}");
                    });
                    \Log::info('Email berhasil dikirim ke supplier', ['email' => $data['supplier']->email]);
                } catch (\Exception $e) {
                    \Log::error('Error saat kirim email', [
                        'supplier_email' => $data['supplier']->email,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error utama dalam sendEmailToSupplier', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    // Modifikasi method store untuk mengirim email dengan PDF
    public function store(Request $request)
    {
        try {
            \DB::beginTransaction();

            // Ambil id_outlet dari request atau user login
            $idOutlet = $request->outlet_id ?? (auth()->user()->id_outlet ?? null);
            $userId = auth()->id();
            $tanggal = $request->tanggal ?? now()->toDateString();

            // Cek apakah sudah ada draft untuk user, tanggal, outlet, status draft
            $existingOrder = \DB::table('food_floor_orders')
                ->where('user_id', $userId)
                ->where('id_outlet', $idOutlet)
                ->where('tanggal', $tanggal)
                ->where('status', 'draft')
                ->first();

            if ($existingOrder) {
                // Update header FO
                \DB::table('food_floor_orders')->where('id', $existingOrder->id)->update([
                    'description' => $request->description ?? '',
                    'fo_mode' => $request->fo_mode ?? 'RO Utama',
                    'input_mode' => $request->input_mode ?? 'pc',
                    'fo_schedule_id' => $request->fo_schedule_id ?? null,
                    'updated_at' => now()
                ]);
                $floorOrderId = $existingOrder->id;
            } else {
                // Insert header FO (DRAFT, bukan RO-...)
                $headerData = [
                    'order_number' => 'DRAFT-' . $userId . '-' . time(),
                    'tanggal' => $tanggal,
                    'description' => $request->description ?? '',
                    'fo_mode' => $request->fo_mode ?? 'RO Utama',
                    'input_mode' => $request->input_mode ?? 'pc',
                    'fo_schedule_id' => $request->fo_schedule_id ?? null,
                    'id_outlet' => $idOutlet,
                    'user_id' => $userId,
            'status' => 'draft',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                \Log::info('Mencoba insert ke food_floor_orders', $headerData);
                $inserted = \DB::table('food_floor_orders')->insert($headerData);
                \Log::info('Hasil insert header', ['success' => $inserted]);
                $floorOrderId = \DB::getPdo()->lastInsertId();
            }

            // Ambil data outlet
            $outlet = \DB::table('tbl_data_outlet')->where('id_outlet', $idOutlet)->first();
            $outletName = $outlet ? $outlet->nama_outlet : 'Unknown Outlet';

            $items = $request->items;
            $supplierItems = $this->validateAndGroupItemsBySupplier($items, $idOutlet);
            $supplierItemIds = collect($supplierItems)->pluck('item_id')->toArray();

            // Hapus item lama (hanya untuk draft ini)
            \DB::table('food_floor_order_items')->where('floor_order_id', $floorOrderId)->delete();
            \DB::table('food_floor_order_supplier_items')->where('floor_order_id', $floorOrderId)->delete();
            // Jangan hapus header supplier, biarkan tetap satu per kombinasi

            // Simpan item yang tidak kena validasi ke food_floor_order_items SAJA
            foreach ($items as $item) {
                if (!in_array($item['item_id'], $supplierItemIds)) {
                    $itemData = [
                        'floor_order_id' => $floorOrderId,
                'item_id' => $item['item_id'],
                'item_name' => $item['item_name'],
                'qty' => $item['qty'],
                'unit' => $item['unit'],
                'price' => $item['price'],
                'subtotal' => $item['subtotal'],
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                    \DB::table('food_floor_order_items')->insert($itemData);
                }
            }

            // Simpan item yang kena validasi HANYA ke food_floor_order_supplier_headers & food_floor_order_supplier_items
            $groupedSupplierItems = collect($supplierItems)->groupBy('supplier_id');
            foreach ($groupedSupplierItems as $supplierId => $itemsGroup) {
                // Cek/insert header supplier jika belum ada
                $header = \DB::table('food_floor_order_supplier_headers')
                    ->where('floor_order_id', $floorOrderId)
                    ->where('supplier_id', $supplierId)
                    ->first();
                if (!$header) {
                    \DB::table('food_floor_order_supplier_headers')->insert([
                        'floor_order_id' => $floorOrderId,
                        'supplier_id' => $supplierId,
                        'supplier_fo_number' => $this->floorOrderService->generateSupplierFONumber($supplierId),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
                // Insert items
                foreach ($itemsGroup as $supplierItem) {
                    \DB::table('food_floor_order_supplier_items')->insert([
                        'floor_order_id' => $floorOrderId,
                        'item_id' => $supplierItem['item_id'],
                        'item_name' => $supplierItem['item_name'],
                        'qty' => $supplierItem['qty'],
                        'unit' => $supplierItem['unit'],
                        'price' => $supplierItem['price'],
                        'subtotal' => $supplierItem['subtotal'],
                        'supplier_id' => $supplierItem['supplier_id'],
                        'item_supplier_id' => $supplierItem['item_supplier_id'],
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            \DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Floor Order berhasil dibuat',
                'data' => [
                    'floor_order_id' => $floorOrderId,
                    'order_number' => $existingOrder->order_number ?? ('DRAFT-' . $userId . '-' . time())
                ]
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Error dalam store', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    // Modifikasi method update untuk mengirim email dengan PDF
    public function update(Request $request, $id)
    {
        $order = FoodFloorOrder::findOrFail($id);
        $oldData = $order->toArray();
        $order->update($request->only(['tanggal', 'description', 'fo_mode', 'input_mode', 'fo_schedule_id']));

        // Validasi dan pisahkan item yang kena validasi
        $supplierItems = $this->validateAndGroupItemsBySupplier($request->items, $order->id_outlet);
        $supplierItemIds = collect($supplierItems)->pluck('item_id')->toArray();

        // Hapus data item lama
        $order->items()->delete();
        \DB::table('food_floor_order_supplier_items')->where('floor_order_id', $order->id)->delete();
        \DB::table('food_floor_order_supplier_headers')->where('floor_order_id', $order->id)->delete();

        // Simpan items yang tidak kena validasi ke food_floor_order_items
        foreach ($request->items as $item) {
            if (!in_array($item['item_id'], $supplierItemIds)) {
            $masterItem = Item::find($item['item_id']);
            $order->items()->create([
                'item_id' => $item['item_id'],
                'item_name' => $item['item_name'],
                'qty' => $item['qty'],
                'unit' => $item['unit'],
                'price' => $item['price'],
                'subtotal' => $item['subtotal'],
                'category_id' => $masterItem ? $masterItem->category_id : null,
                'warehouse_division_id' => $masterItem ? $masterItem->warehouse_division_id : null,
            ]);
        }
        }

        // Simpan item yang kena validasi ke food_floor_order_supplier_headers & food_floor_order_supplier_items
        $groupedSupplierItems = collect($supplierItems)->groupBy('supplier_id');
        foreach ($groupedSupplierItems as $supplierId => $itemsGroup) {
            // Cek/insert header supplier jika belum ada
            $header = \DB::table('food_floor_order_supplier_headers')
                ->where('floor_order_id', $order->id)
                ->where('supplier_id', $supplierId)
                ->first();
            if (!$header) {
                \DB::table('food_floor_order_supplier_headers')->insert([
                    'floor_order_id' => $order->id,
                    'supplier_id' => $supplierId,
                    'supplier_fo_number' => $this->floorOrderService->generateSupplierFONumber($supplierId),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            // Insert items
            foreach ($itemsGroup as $supplierItem) {
                \DB::table('food_floor_order_supplier_items')->insert([
                    'floor_order_id' => $order->id,
                    'item_id' => $supplierItem['item_id'],
                    'item_name' => $supplierItem['item_name'],
                    'qty' => $supplierItem['qty'],
                    'unit' => $supplierItem['unit'],
                    'price' => $supplierItem['price'],
                    'subtotal' => $supplierItem['subtotal'],
                    'supplier_id' => $supplierItem['supplier_id'],
                    'item_supplier_id' => $supplierItem['item_supplier_id'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }

        \App\Models\ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'food_floor_order',
            'description' => 'Update Floor Order: ' . $order->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $oldData,
            'new_data' => $order->fresh()->toArray(),
        ]);
        return response()->json(['success' => true]);
    }

    // Hapus draft
    public function destroy($id)
    {
        $order = FoodFloorOrder::findOrFail($id);
        $oldData = $order->toArray();
        if (!in_array($order->status, ['draft', 'approved', 'submitted'])) {
            return response()->json(['error' => 'Tidak bisa hapus selain draft, approved, atau submitted'], 422);
        }
        $order->items()->delete();
        $order->delete();
        \App\Models\ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'food_floor_order',
            'description' => 'Menghapus Floor Order: ' . $oldData['id'],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => $oldData,
            'new_data' => null,
        ]);
        return response()->json(['success' => true]);
    }

    // Submit draft
    public function submit(Request $request, $id)
    {
        \Log::info('SUBMIT FO DIPANGGIL', ['id' => $id]);
        $order = FoodFloorOrder::findOrFail($id);
        $date = now()->format('Ymd');
        $random = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        $order_number = 'RO-' . $date . '-' . $random;

        $order->update([
            'status' => $order->fo_mode === 'FO Khusus' ? 'submitted' : 'approved',
            'order_number' => $order_number,
        ]);
        \Log::info('FO status & nomor diupdate', ['id' => $order->id, 'status' => $order->status, 'order_number' => $order_number]);

        // Kirim email ke supplier jika ada item supplier
        $supplierItems = \DB::table('food_floor_order_supplier_items')
            ->where('floor_order_id', $order->id)
            ->get();
        \Log::info('Jumlah item supplier', ['count' => $supplierItems->count()]);
        if ($supplierItems->count() > 0) {
            $outlet = \DB::table('tbl_data_outlet')->where('id_outlet', $order->id_outlet)->first();
            $outletName = $outlet ? $outlet->nama_outlet : 'Unknown Outlet';
            // Ambil nama lengkap creator
            $creator = \DB::table('users')->where('id', $order->user_id)->first();
            $creatorName = $creator ? $creator->nama_lengkap : $order->user_id;
            // Group by supplier_id
            $grouped = $supplierItems->groupBy('supplier_id');
            foreach ($grouped as $supplierId => $items) {
                $supplier = \DB::table('suppliers')->where('id', $supplierId)->first();
                \Log::info('Proses email supplier', ['supplier_id' => $supplierId, 'email' => $supplier ? $supplier->email : null, 'jumlah_item' => count($items)]);
                if ($supplier && $supplier->email) {
                    $header = \DB::table('food_floor_order_supplier_headers')
                        ->where('floor_order_id', $order->id)
                        ->where('supplier_id', $supplierId)
                        ->first();
                    // Ubah format nomor supplier FO menjadi RO-SUPP-...
                    $supplierFoNumber = $header ? preg_replace('/^(FO|ROHEN|FO-SUP|ROHEN|ROHEN2)/', 'RO-SUPP', $header->supplier_fo_number) : '';
                    $itemsList = '';
                    foreach ($items as $item) {
                        $itemsList .= "- {$item->item_name} ({$item->qty} {$item->unit})\n";
                    }
                    $emailContent = "\nRequest Order Supplier\n\n- Nomor RO: {$order->order_number}\n- Nomor RO Supplier: {$supplierFoNumber}\nOutlet: {$outletName}\nDibuat oleh: {$creatorName}\nWaktu pembuatan: {$order->created_at}\n\nDetail Items:\n{$itemsList}\n\nTerima kasih,\nYMSoft ERP\n";
                    \Mail::raw($emailContent, function($message) use ($supplier, $supplierFoNumber) {
                        $message->to($supplier->email)
                                ->subject("Request Order Supplier - {$supplierFoNumber}");
                    });
                    \Log::info('Email dikirim ke supplier', ['supplier_id' => $supplierId, 'email' => $supplier->email]);
                }
            }
        }
        return response()->json(['success' => true]);
    }

    // Cek apakah sudah ada FO Utama/Tambahan di hari dan outlet yang sama
    public function checkExists(Request $request)
    {
        $tanggal = $request->tanggal;
        $id_outlet = $request->id_outlet;
        $fo_mode = $request->fo_mode;
        $exclude_id = $request->exclude_id;

        $query = \App\Models\FoodFloorOrder::where('tanggal', $tanggal)
            ->where('id_outlet', $id_outlet)
            ->where('fo_mode', $fo_mode)
            ->whereNotIn('status', ['rejected']);

        if ($exclude_id) {
            $query->where('id', '!=', $exclude_id);
        }

        $exists = $query->exists();
        return response()->json(['exists' => $exists]);
    }

    public function show($id)
    {
        $order = FoodFloorOrder::with(['items.category', 'outlet', 'requester', 'foSchedule', 'approver'])->findOrFail($id);
        return Inertia::render('FloorOrder/Show', [
            'order' => $order,
            'user' => Auth::user()->load('outlet'),
        ]);
    }

    // Tambahkan method sendNotification
    private function sendNotification($userIds, $type, $title, $message, $url) {
        $now = now();
        $data = [];
        foreach ($userIds as $uid) {
            $data[] = [
                'user_id' => $uid,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'url' => $url,
                'is_read' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        \DB::table('notifications')->insert($data);
    }

    public function approve(Request $request, $id)
    {
        $user = Auth::user();
        $order = \App\Models\FoodFloorOrder::findOrFail($id);

        // Cek hak akses
        $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';
        $isExecutiveChef = $user->id_jabatan == 163 && $user->status === 'A';
        if (!($isSuperadmin || $isExecutiveChef)) {
            abort(403, 'Unauthorized');
        }

        if ($order->fo_mode !== 'FO Khusus' || $order->status !== 'submitted') {
            abort(400, 'Tidak bisa approve order ini');
        }

        $order->update([
            'status' => 'approved',
            'approval_by' => $user->id,
            'approval_at' => now(),
            'approval_notes' => $request->notes,
        ]);
        \App\Models\ActivityLog::create([
            'user_id' => $user->id,
            'activity_type' => 'approve',
            'module' => 'food_floor_order',
            'description' => 'Approve Floor Order: ' . $order->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $order->fresh()->toArray(),
        ]);
        return redirect()->back()->with('success', 'Floor Order berhasil di-approve');
    }
} 